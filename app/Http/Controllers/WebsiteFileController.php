<?php

	namespace App\Http\Controllers;

	use App\Models\Website;
	use App\Models\WebsiteFile;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\DB; // Import DB facade
	use Illuminate\Support\Facades\Log; // Import Log facade
	use Illuminate\Support\Facades\Validator; // Import Validator

	class WebsiteFileController extends Controller
	{
		/**
		 * Display a listing of the latest versions of files for the website.
		 */
		public function index(Website $website)
		{
			// Authorize: Ensure the user owns this website
			$this->authorize('view', $website); // Use WebsitePolicy

			// Query to get the latest version of each *active* file
			$latestFiles = WebsiteFile::select('id', 'website_id', 'filename', 'folder', 'filetype', 'version', 'content', 'created_at', 'updated_at')
				->where('website_id', $website->id)
				->whereIn('id', function ($query) use ($website) {
					// Subquery to find the ID of the latest version for each file path
					$query->select(DB::raw('MAX(id)'))
						->from('website_files')
						->where('website_id', $website->id)
						->groupBy('website_id', 'folder', 'filename');
				})
				->where('is_deleted', false) // <-- Ensure the latest version is not marked as deleted
				->orderBy('folder')
				->orderBy('filename')
				->get();

			// Structure the files for a potential tree view (optional)
			// Note: buildFileTree might need adjustment if it relies on specific structures
			// $fileTree = $this->buildFileTree($latestFiles);

			return response()->json([
				'files' => $latestFiles, // Return flat list of active files
				// 'tree' => $fileTree // Return tree structure (optional)
			]);
		}

		/**
		 * Update the specified file by creating a new version.
		 */
		public function update(Request $request, Website $website)
		{
			// Authorize: Ensure the user can update this website's files
			$this->authorize('update', $website); // Assumes 'update' permission in WebsitePolicy

			$validated = $request->validate([
				'folder' => 'required|string|max:255',
				'filename' => 'required|string|max:255',
				'content' => 'present|string|nullable', // Allow empty content, 'present' ensures it's sent
				// 'base_version_id' => 'required|integer|exists:website_files,id' // Optional: Add if you want to prevent concurrent edits
			]);

			// --- Normalize folder and filename ---
			$folder = $this->normalizeFolderPath($validated['folder']);
			$filename = trim($validated['filename']);

			// Basic filename validation (prevent path traversal etc.)
			if (str_contains($filename, '/') || str_contains($filename, '\\') || $filename === '.' || $filename === '..') {
				return response()->json(['error' => 'Invalid filename provided.'], 400);
			}
			// --- End Normalization ---

			DB::beginTransaction();
			try {
				// Find the *absolute latest* record for this path to get filetype and increment version
				$latestVersionRecord = WebsiteFile::where('website_id', $website->id)
					->where('folder', $folder)
					->where('filename', $filename)
					->orderByDesc('version')
					->lockForUpdate() // Prevent race conditions when determining next version
					->first();

				if (!$latestVersionRecord) {
					// This shouldn't happen if editing a file from the list, but handle defensively
					DB::rollBack();
					return response()->json(['error' => 'Original file path not found.'], 404);
				}

				// Optional: Concurrency Check - uncomment if you add 'base_version_id' to request
				// if ($latestVersionRecord->id !== $validated['base_version_id']) {
				//     DB::rollBack();
				//     return response()->json(['error' => 'File has been modified since you started editing. Please refresh and try again.'], 409); // 409 Conflict
				// }

				$newVersionNumber = $latestVersionRecord->version + 1;

				// Create the new version
				$newFile = WebsiteFile::create([
					'website_id' => $website->id,
					'filename' => $filename,
					'folder' => $folder,
					'filetype' => $latestVersionRecord->filetype, // Keep original filetype
					'version' => $newVersionNumber,
					'content' => $validated['content'] ?? '', // Use empty string if null
					'is_deleted' => false, // Saving makes it active
				]);

				DB::commit();

				Log::info("Website ID {$website->id}: Updated file {$folder}/{$filename} to version {$newVersionNumber} (New ID: {$newFile->id})");

				// Return the newly created file record
				return response()->json($newFile, 201); // 201 Created (as we made a new version resource)

			} catch (\Exception $e) {
				DB::rollBack();
				Log::error("Error updating file for Website ID {$website->id} ({$folder}/{$filename}): " . $e->getMessage());
				return response()->json(['error' => 'Failed to save file changes. Please try again.'], 500);
			}
		}


		// Helper function to sanitize and normalize folder paths (copied from ChatMessageController - consider moving to a Trait or Helper class)
		private function normalizeFolderPath(string $folder): string
		{
			$folder = trim(str_replace('..', '', $folder)); // Remove '..'
			if ($folder === '' || $folder === '/') {
				return '/'; // Root folder
			}
			// Ensure leading slash, remove trailing
			return '/' . trim($folder, '/');
		}


		// Helper function to build a nested tree structure (optional)
		// Keep or remove based on frontend needs
		private function buildFileTree($files)
		{
			// ... (implementation remains the same, but only receives active files)
			$tree = [];
			foreach ($files as $file) {
				$pathParts = array_filter(explode('/', trim($file->folder, '/')));
				$currentLevel = &$tree;
				foreach ($pathParts as $part) {
					if (!isset($currentLevel[$part])) {
						$currentLevel[$part] = ['type' => 'folder', 'children' => []];
					}
					$currentLevel = &$currentLevel[$part]['children'];
				}
				$currentLevel[$file->filename] = [
					'type' => 'file',
					'id' => $file->id,
					'filename' => $file->filename,
					'folder' => $file->folder,
					'version' => $file->version,
					'content' => $file->content, // Consider fetching on demand for large files
				];
			}
			return $tree;
		}
	}

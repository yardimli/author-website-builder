<?php

	namespace App\Http\Controllers;

	use App\Models\Website;
	use App\Models\WebsiteFile;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\DB; // Import DB facade

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

		// Helper function to build a nested tree structure (optional)
		// Keep or remove based on frontend needs
		private function buildFileTree($files) {
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

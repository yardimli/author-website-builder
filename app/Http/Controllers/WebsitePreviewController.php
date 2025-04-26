<?php

	namespace App\Http\Controllers;

	use App\Models\Website;
	use App\Models\WebsiteFile;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\File;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Str;
	use Symfony\Component\HttpFoundation\Response;
	use Throwable;

	class WebsitePreviewController extends Controller
	{
		/**
		 * Handle the incoming request to serve a file for a website preview.
		 * Executes PHP files or serves static files.
		 */
		public function serve(Request $request, Website $website, $path = null): Response
		{
			// --- Determine filename and folder (No change here) ---
			$requestedPath = $path ?: 'index.php'; // Default to index.php
			$folder = dirname($requestedPath);
			$filename = basename($requestedPath);

			if ($folder === '.') {
				$folder = '/'; // Root folder
			} else {
				$folder = '/' . trim($folder, '/'); // Ensure leading slash, remove trailing
			}

			// --- Find the latest version of the requested file (No change here) ---
			$latestRequestedFile = WebsiteFile::where('website_id', $website->id)
				->where('folder', $folder)
				->where('filename', $filename)
				->orderByDesc('version')
				->first();

			// --- Handle File Not Found (No change here) ---
			if (!$latestRequestedFile || $latestRequestedFile->is_deleted) {
				if ($requestedPath === 'index.php' && $folder === '/') {
					abort(404, "index.php not found in the root of this website's generated files.");
				}
				abort(404, "File not found ('{$requestedPath}') or has been deleted in this website's generated files.");
			}

			// --- Determine if it's PHP ---
			$extension = strtolower(pathinfo($latestRequestedFile->filename, PATHINFO_EXTENSION));

			// --- Execute PHP or Serve Static File ---
			if ($extension === 'php' || $extension === 'html' || $extension === 'htm') { // Also handle direct HTML files
				// --- Execute PHP File / Serve HTML with Base Tag ---
				$tempDir = null; // Initialize outside try
				try {
					// 1. Fetch all latest *active* files for the website
					$allLatestFiles = WebsiteFile::select('id', 'filename', 'folder', 'content', 'filetype') // Added filetype
					->where('website_id', $website->id)
						->whereIn('id', function ($query) use ($website) {
							$query->select(DB::raw('MAX(id)'))
								->from('website_files')
								->where('website_id', $website->id)
								->groupBy('website_id', 'folder', 'filename');
						})
						->where('is_deleted', false)
						->get();

					if ($allLatestFiles->isEmpty()) {
						return response("Error: No active files found for this website to execute.", 500)
							->header('Content-Type', 'text/plain');
					}

					// 2. Create a unique temporary directory
					$tempDir = storage_path('app/preview_temp/' . $website->id . '_' . Str::random(10));
					if (!File::makeDirectory($tempDir, 0755, true, true)) {
						Log::error("Failed to create temporary directory: {$tempDir}");
						return response("Server error: Could not create temporary directory for preview.", 500)
							->header('Content-Type', 'text/plain');
					}

					// *** START: Calculate Base URL ***
					// The base URL should point to the root of the preview for this specific website
					$baseUrl = route('website.preview.serve', ['website' => $website->id, 'path' => '']);
					// Ensure it has a trailing slash, crucial for <base href>
					$baseUrl = rtrim($baseUrl, '/') . '/';
					$baseTag = "<base href=\"" . htmlspecialchars($baseUrl) . "\">";
					// *** END: Calculate Base URL ***

					// 3. Write all files to the temporary directory, injecting <base> tag if needed
					foreach ($allLatestFiles as $file) {
						$filePath = $tempDir . rtrim($file->folder, '/') . '/' . $file->filename;
						$fileDir = dirname($filePath);
						if (!File::exists($fileDir)) {
							File::makeDirectory($fileDir, 0755, true, true);
						}

						$contentToWrite = $file->content;
						$fileExtension = strtolower(pathinfo($file->filename, PATHINFO_EXTENSION));

						// *** START: Inject Base Tag ***
						// Inject only into PHP or HTML files that likely contain the <head>
						if ($fileExtension === 'php' || $fileExtension === 'html' || $fileExtension === 'htm') {
							// Use regex to insert the base tag right after the opening <head> tag
							// This handles <head>, <head >, <head attr="val"> etc. Case-insensitive. Replaces only first match.
							$modifiedContent = preg_replace('/(<head[^>]*>)/i', '$1' . $baseTag, $contentToWrite, 1, $count);
							if ($count > 0) { // Check if replacement happened
								$contentToWrite = $modifiedContent;
								Log::debug("Injected base tag into {$file->filename} for Website ID {$website->id}");
							} else {
								Log::debug("Could not find <head> tag to inject base tag into {$file->filename} for Website ID {$website->id}");
							}
						}
						// *** END: Inject Base Tag ***


						if (File::put($filePath, $contentToWrite) === false) {
							Log::error("Failed to write temporary file: {$filePath}");
							if ($tempDir && File::exists($tempDir)) File::deleteDirectory($tempDir);
							return response("Server error: Could not write temporary file for preview.", 500)
								->header('Content-Type', 'text/plain');
						}
					}

					// 4. Determine the full path to the *requested* file in the temp dir
					$targetTempFilePath = $tempDir . rtrim($latestRequestedFile->folder, '/') . '/' . $latestRequestedFile->filename;

					if (!File::exists($targetTempFilePath)) {
						Log::error("Target temporary file does not exist after writing: {$targetTempFilePath}");
						if ($tempDir && File::exists($tempDir)) File::deleteDirectory($tempDir);
						return response("Server error: Target file missing in temporary structure.", 500)
							->header('Content-Type', 'text/plain');
					}

					// 5. Execute the target PHP file using output buffering (if PHP) or read HTML
					if ($extension === 'php') {
						ob_start();
						$executionError = null;
						$originalDir = getcwd();
						try {
							chdir(dirname($targetTempFilePath));
							include basename($targetTempFilePath);
						} catch (Throwable $e) {
							$executionError = "PHP Execution Error in '{$requestedPath}': " . $e->getMessage() . "\nFile: " . $e->getFile() . "\nLine: " . $e->getLine();
							Log::error("PHP Preview Execution Error (Website ID: {$website->id}, Path: {$requestedPath}): " . $e->getMessage(), ['exception' => $e]);
							if (ob_get_level() > 0) {
								ob_end_clean();
							}
							ob_start(); // Start new buffer for error message
							echo "<!DOCTYPE html><html><head><title>Execution Error</title>{$baseTag}</head><body>"; // Include base tag even in error page
							echo "<h1>PHP Execution Error</h1>";
							echo "<pre style='color: red; background-color: #fdd; padding: 10px; border: 1px solid red; white-space: pre-wrap; word-wrap: break-word;'>";
							echo htmlspecialchars($executionError);
							echo "</pre>";
							echo "</body></html>";
						} finally {
							chdir($originalDir);
						}
						$output = ob_get_clean();
						// 6. Return the captured output
						return response($output)->header('Content-Type', 'text/html');

					} else { // Serve HTML/HTM file directly (already has base tag injected)
						$output = File::get($targetTempFilePath);
						return response($output)->header('Content-Type', $this->guessMimeType($extension));
					}

				} catch (\Exception $e) {
					Log::error("General Preview Error (Website ID: {$website->id}, Path: {$requestedPath}): " . $e->getMessage(), ['exception' => $e]);
					return response("Server error during preview generation: " . $e->getMessage(), 500)
						->header('Content-Type', 'text/plain');
				} finally {
					// 7. Clean up the temporary directory
					if ($tempDir && File::exists($tempDir)) {
						File::deleteDirectory($tempDir);
					}
				}
			} else {
				// --- Serve Static File (Original Logic - No changes needed here) ---
				$mimeType = $this->guessMimeType($extension);
				return response($latestRequestedFile->content)
					->header('Content-Type', $mimeType);
			}
		}

		/**
		 * Guess MIME type based on file extension.
		 */
		private function guessMimeType(string $extension): string
		{
			$extension = strtolower($extension);
			$mimeTypes = [
				'txt' => 'text/plain',
				'html' => 'text/html',
				'htm' => 'text/html',
				'css' => 'text/css',
				'js' => 'application/javascript',
				'json' => 'application/json',
				'xml' => 'application/xml',
				'jpg' => 'image/jpeg',
				'jpeg' => 'image/jpeg',
				'png' => 'image/png',
				'gif' => 'image/gif',
				'svg' => 'image/svg+xml',
				'ico' => 'image/x-icon',
				'webp' => 'image/webp',
				'woff' => 'font/woff',
				'woff2' => 'font/woff2',
				'ttf' => 'font/ttf',
				'otf' => 'font/otf',
				'eot' => 'application/vnd.ms-fontobject',
				// Add more as needed
			];
			return $mimeTypes[$extension] ?? 'application/octet-stream'; // Default binary stream
		}
	}

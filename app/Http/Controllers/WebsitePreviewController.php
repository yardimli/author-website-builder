<?php

	namespace App\Http\Controllers;

	use App\Models\Website;
	use App\Models\WebsiteFile;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\DB;
	use Symfony\Component\HttpFoundation\Response; // Use Symfony Response
	use Illuminate\Support\Facades\File; // For MIME type guessing

	class WebsitePreviewController extends Controller
	{
		/**
		 * Handle the incoming request to serve a file for a website preview.
		 */
		public function serve(Request $request, Website $website, $path = null): Response
		{
			// Determine filename and folder from path
			$path = $path ?: 'index.html'; // Default to index.html if no path
			$folder = dirname($path);
			$filename = basename($path);

			if ($folder === '.') {
				$folder = '/'; // Root folder
			} else {
				$folder = '/' . trim($folder, '/'); // Ensure leading slash, remove trailing
			}


			// Find the latest version of the requested file
			$latestFile = WebsiteFile::where('website_id', $website->id)
				->where('folder', $folder)
				->where('filename', $filename)
				->orderByDesc('version')
				->first();

			if (!$latestFile) {
				// If index.html wasn't found at root, maybe try without path? (optional)
				if ($path === 'index.html' && $folder === '/') {
					$latestFile = WebsiteFile::where('website_id', $website->id)
						->where('filename', 'index.html')
						->orderByDesc('version')
						->first(); // Try finding index.html anywhere? Risky. Stick to specific path.
				}

				if(!$latestFile) {
					// Still not found, return 404
					abort(404, "File not found in this website's generated files.");
				}
			}

			// Determine MIME type
			$extension = pathinfo($latestFile->filename, PATHINFO_EXTENSION);
			$mimeType = $this->guessMimeType($extension);

			// Return the file content with the correct Content-Type header
			return response($latestFile->content)
				->header('Content-Type', $mimeType);
		}

		/**
		 * Guess MIME type based on file extension.
		 * (Simple version, consider a more robust library/method if needed)
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
				// Add more as needed
			];

			return $mimeTypes[$extension] ?? 'application/octet-stream'; // Default binary stream
		}
	}

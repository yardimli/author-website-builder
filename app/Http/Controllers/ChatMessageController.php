<?php

	namespace App\Http\Controllers;

	use App\Helper\CodeSanitizerHelper; // MODIFIED: Import the new sanitizer helper.
	use App\Helper\LlmHelper;
	use App\Models\Website;
	use App\Models\ChatMessage;
	use App\Models\WebsiteFile;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\File;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Auth;

// Keep if needed elsewhere

	class ChatMessageController extends Controller
	{
		private string $systemPrompt;

		public function __construct()
		{
			$promptFilePath = public_path('elooi_system_prompt.txt'); // <-- Path to your file in public/

			if (File::exists($promptFilePath)) {
				$this->systemPrompt = File::get($promptFilePath);
			} else {
				// Fallback or error handling if the file doesn't exist
				$errorMessage = "Error: System prompt file not found at {$promptFilePath}";
				Log::error($errorMessage);
				// Provide a default minimal prompt or throw an exception
				$this->systemPrompt = "<role>Error: System prompt could not be loaded. Please contact support.</role>";
				// Or potentially: throw new \Exception($errorMessage);
			}
		}


		// Helper function to sanitize and normalize folder paths
		private function normalizeFolderPath(string $folder): string
		{
			$folder = trim(str_replace('..', '', $folder)); // Remove '..'
			if ($folder === '' || $folder === '/') {
				return '/'; // Root folder
			}
			// Ensure leading slash, remove trailing
			return '/' . trim($folder, '/');
		}

		// Helper function to validate filenames
		private function isValidFilename(?string $filename): bool
		{
			if ($filename === null || $filename === '' || $filename === '.' || $filename === '..') {
				return false;
			}
			// Check for directory separators
			if (str_contains($filename, '/') || str_contains($filename, '\\')) {
				return false;
			}
			// Add any other invalid character checks if needed
			// Example: Check for characters not allowed in filenames on common systems
			// if (preg_match('/[\x00-\x1F\x7F<>:"\/\\|?*]/', $filename)) {
			//     return false;
			// }
			return true;
		}


		public function store(Request $request, Website $website)
		{
			$this->authorize('update', $website);

			$validated = $request->validate([
				'message' => 'required|string|max:4000',
			]);

			DB::beginTransaction();

			try {
				// 1. Save user message
				$userMessage = $website->chatMessages()->create([
					'role' => 'user',
					'content' => $validated['message'],
				]);

				// 2. Prepare LLM input (fetch active files - no change here)
				$latestFiles = WebsiteFile::select('id', 'website_id', 'filename', 'folder', 'filetype', 'version', 'content', 'created_at', 'updated_at', 'is_deleted')
					->where('website_id', $website->id)
					->whereIn('id', function ($query) use ($website) {
						$query->select(DB::raw('MAX(id)'))
							->from('website_files')
							->where('website_id', $website->id)
							->groupBy('website_id', 'folder', 'filename');
					})
					->where('is_deleted', false)
					->orderBy('folder')
					->orderBy('filename')
					->get();

				$fileContext = $latestFiles->map(function ($file) {
					$path = rtrim($file->folder, '/') . '/' . $file->filename;
					if ($file->folder === '/') {
						$path = '/' . $file->filename;
					}
					$trimmedContent = trim($file->content);
					return $path . "\n" . $trimmedContent;
				})->implode("\n\n");

				$llmUserInput = $fileContext . "\n\n---\n\nUser Request:\n" . $validated['message'];
				$chat_messages = [['role' => 'user', 'content' => $llmUserInput]];
				$llmModel = $website->llm_model ?? env('DEFAULT_LLM', 'mistralai/mixtral-8x7b-instruct');

				// 3. Call LLM (no change here)
				Log::info("Calling LLM for Website ID: {$website->id} with model: {$llmModel}");
				$llmResponse = LlmHelper::call_llm($llmModel, $this->systemPrompt, $chat_messages);

				// 4. Handle LLM Errors (no change here)
				if (str_starts_with($llmResponse['content'], 'Error:')) {
					// ... (error handling as before) ...
					Log::error("LLM Call Error for Website ID {$website->id}: " . $llmResponse['content']);
					$assistantMessage = $website->chatMessages()->create([
						'role' => 'assistant',
						'content' => "Sorry, I encountered an internal error. Please try again. (Details: " . $llmResponse['content'] . ")",
					]);
					DB::commit();
					return response()->json([
						'userMessage' => $userMessage,
						'assistantMessage' => $assistantMessage,
						'files_updated' => false,
					], 500);
				}

				// 5. Parse LLM Response and Apply Changes
				$rawLlmOutput = $llmResponse['content'];
				$aiTextResponse = $rawLlmOutput;
				$filesUpdated = false;

				// --- Process Renames ---
				// Updated Regex to capture new attributes
				preg_match_all('/<elooi-rename\s+from_folder="([^"]*)"\s+from_filename="([^"]+)"\s+to_folder="([^"]*)"\s+to_filename="([^"]+)"\s*\/?>/si', $rawLlmOutput, $renameMatches, PREG_SET_ORDER);

				foreach ($renameMatches as $match) {
					$fromFolder = $this->normalizeFolderPath($match[1]);
					$fromFilename = trim($match[2]);
					$toFolder = $this->normalizeFolderPath($match[3]);
					$toFilename = trim($match[4]);

					// Validate filenames
					if (!$this->isValidFilename($fromFilename) || !$this->isValidFilename($toFilename)) {
						Log::warning("Website ID {$website->id}: Invalid filename provided by LLM for rename. From: '{$fromFolder}/{$fromFilename}', To: '{$toFolder}/{$toFilename}'");
						continue; // Skip invalid rename
					}

					// Find the latest active version of the 'from' file
					$latestFromFile = WebsiteFile::findLatestActive($website->id, $fromFolder, $fromFilename);

					if ($latestFromFile) {
						// Step A: Mark the old file path as deleted
						WebsiteFile::create([
							'website_id' => $website->id,
							'filename' => $latestFromFile->filename,
							'folder' => $latestFromFile->folder,
							'filetype' => $latestFromFile->filetype,
							'version' => $latestFromFile->version + 1,
							'content' => $latestFromFile->content,
							'is_deleted' => true,
						]);

						// Step B: Create the new file at the 'to' path (version 1)
						WebsiteFile::create([
							'website_id' => $website->id,
							'filename' => $toFilename, // Use new filename
							'folder' => $toFolder,     // Use new folder
							'filetype' => pathinfo($toFilename, PATHINFO_EXTENSION),
							'version' => 1,
							'content' => $latestFromFile->content,
							'is_deleted' => false,
						]);

						Log::info("Website ID {$website->id}: Renamed '{$fromFolder}/{$fromFilename}' (v{$latestFromFile->version}) to '{$toFolder}/{$toFilename}' (v1). Marked old path deleted (v" . ($latestFromFile->version + 1) . ").");
						$filesUpdated = true;
					} else {
						Log::warning("Website ID {$website->id}: LLM tried to rename non-existent or already deleted file: '{$fromFolder}/{$fromFilename}'");
					}
				}
				// Remove rename tags from user response
				$aiTextResponse = preg_replace('/<elooi-rename[^>]*\/?>/si', '', $aiTextResponse);


				// --- Process Deletes ---
				// Updated Regex to capture new attributes
				preg_match_all('/<elooi-delete\s+folder="([^"]*)"\s+filename="([^"]+)"\s*\/?>/si', $rawLlmOutput, $deleteMatches, PREG_SET_ORDER);
				foreach ($deleteMatches as $match) {
					$deleteFolder = $this->normalizeFolderPath($match[1]);
					$deleteFilename = trim($match[2]);

					// Validate filename
					if (!$this->isValidFilename($deleteFilename)) {
						Log::warning("Website ID {$website->id}: Invalid filename provided by LLM for delete: '{$deleteFolder}/{$deleteFilename}'");
						continue; // Skip invalid delete
					}

					// Find the latest active version of the file to delete
					$latestFileToDelete = WebsiteFile::findLatestActive($website->id, $deleteFolder, $deleteFilename);

					if ($latestFileToDelete) {
						// Mark the file path as deleted
						WebsiteFile::create([
							'website_id' => $website->id,
							'filename' => $latestFileToDelete->filename,
							'folder' => $latestFileToDelete->folder,
							'filetype' => $latestFileToDelete->filetype,
							'version' => $latestFileToDelete->version + 1,
							'content' => $latestFileToDelete->content,
							'is_deleted' => true,
						]);
						Log::info("Website ID {$website->id}: Marked file '{$deleteFolder}/{$deleteFilename}' deleted (created v" . ($latestFileToDelete->version + 1) . ").");
						$filesUpdated = true;
					} else {
						Log::warning("Website ID {$website->id}: LLM tried to delete non-existent or already deleted file: '{$deleteFolder}/{$deleteFilename}'");
					}
				}
				// Remove delete tags from user response
				$aiTextResponse = preg_replace('/<elooi-delete[^>]*\/?>/si', '', $aiTextResponse);


				// --- Process Writes --- (No change in logic, just ensure folder normalization)
				preg_match_all('/<elooi-write folder="([^"]*)" filename="([^"]+)" description="([^"]*)">\s*(.*?)\s*<\/elooi-write>/s', $rawLlmOutput, $writeMatches, PREG_SET_ORDER);
				foreach ($writeMatches as $match) {
					$folder = $this->normalizeFolderPath($match[1]); // Use helper
					$filename = trim($match[2]);
					$description = trim($match[3]);
					$content = trim($match[4]);
					$filetype = pathinfo($filename, PATHINFO_EXTENSION); // MODIFIED: Get filetype for check.

					if (!$this->isValidFilename($filename)) {
						Log::warning("Website ID {$website->id}: LLM tried to write invalid filename: {$filename}");
						continue;
					}

					// --- MODIFIED: START PHP SANITIZATION ---
					// If the file is a PHP file, run it through the sanitizer before saving.
					if ($filetype === 'php') {
						$sanitizationResult = CodeSanitizerHelper::sanitizePhp($content);
						if (!$sanitizationResult['success']) {
							// Log the security issue and skip writing this file.
							Log::warning("Website ID {$website->id}: LLM tried to write forbidden PHP code to {$folder}/{$filename}. Reason: " . $sanitizationResult['message']);
							// Optionally, inform the user in the chat that a file was rejected for security reasons.
							continue; // Skip this file write operation.
						}
					}
					// --- MODIFIED: END PHP SANITIZATION ---

					// Find latest version (deleted or not)
					$latestVersion = WebsiteFile::where('website_id', $website->id)
						->where('folder', $folder)
						->where('filename', $filename)
						->max('version');

					$newVersion = $latestVersion ? $latestVersion + 1 : 1;

					WebsiteFile::create([
						'website_id' => $website->id,
						'filename' => $filename,
						'folder' => $folder,
						'filetype' => $filetype, // MODIFIED: Use determined filetype
						'version' => $newVersion,
						'content' => $content,
						'is_deleted' => false, // Ensure written files are active
					]);
					Log::info("Website ID {$website->id}: Wrote file {$folder}/{$filename} version {$newVersion}");
					$filesUpdated = true;
				}
				// Remove write tags from user response
				$aiTextResponse = preg_replace('/<elooi-write[^>]*>.*?<\/elooi-write>/s', '', $aiTextResponse);


				// --- Extract Chat Summary (Optional - no change here) ---
				// ... (summary extraction as before) ...
				if (preg_match('/<elooi-chat-summary>\s*(.*?)\s*<\/elooi-chat-summary>/s', $aiTextResponse, $summaryMatch)) {
					$chatSummary = trim($summaryMatch[1]);
					$aiTextResponse = preg_replace('/<elooi-chat-summary>.*?<\/elooi-chat-summary>/s', '', $aiTextResponse);
				}


				// --- Final Cleanup and Save Assistant Message (no change here) ---
				$aiTextResponse = trim($aiTextResponse);
				//remove empty lines
				$aiTextResponse = preg_replace('/^\s*$/m', '', $aiTextResponse);
				$assistantMessage = $website->chatMessages()->create([
					'role' => 'assistant',
					'content' => $aiTextResponse ?: 'Okay, I have made the requested changes.',
				]);

				// --- Commit and Respond (no change here) ---
				DB::commit();

				return response()->json([
					'userMessage' => $userMessage,
					'assistantMessage' => $assistantMessage,
					'files_updated' => $filesUpdated,
					'prompt_tokens' => $llmResponse['prompt_tokens'] ?? 0,
					'completion_tokens' => $llmResponse['completion_tokens'] ?? 0,
				]);
			} catch (\Illuminate\Auth\Access\AuthorizationException $e) {
				// ... (exception handling as before) ...
				DB::rollBack();
				Log::warning("Authorization failed in ChatMessageController: " . $e->getMessage());
				return response()->json(['error' => 'Unauthorized.'], 403);
			} catch (\Exception $e) {
				// ... (exception handling as before) ...
				DB::rollBack();
				Log::error("Error processing chat message for Website ID {$website->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
				$errorMessageForUser = 'Sorry, an unexpected error occurred. Please try again later.';
				try {
					$userMsgForResponse = isset($userMessage) ? $userMessage : null;
					$assistantMessage = $website->chatMessages()->create([
						'role' => 'assistant',
						'content' => $errorMessageForUser . "\n(Error ref: " . now()->timestamp . ")"
					]);
					DB::commit();
					return response()->json([
						'userMessage' => $userMsgForResponse,
						'assistantMessage' => $assistantMessage,
						'files_updated' => false,
					], 500);
				} catch (\Exception $saveError) {
					Log::error("Could not save error message to chat: " . $saveError->getMessage());
					return response()->json(['error' => $errorMessageForUser], 500);
				}
			}
		}
	}

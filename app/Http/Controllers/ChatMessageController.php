<?php

	namespace App\Http\Controllers;

	use App\Helper\CodeSanitizerHelper; // Import the sanitizer helper
	use App\Helper\LlmHelper;
	use App\Models\Website;
	use App\Models\ChatMessage;
	use App\Models\WebsiteFile;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\File;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Auth;

	class ChatMessageController extends Controller
	{
		private string $systemPrompt;

		public function __construct()
		{
			$promptFilePath = public_path('elooi_system_prompt.txt');

			if (File::exists($promptFilePath)) {
				$this->systemPrompt = File::get($promptFilePath);
			} else {
				$errorMessage = "Error: System prompt file not found at {$promptFilePath}";
				Log::error($errorMessage);
				$this->systemPrompt = "<role>Error: System prompt could not be loaded. Please contact support.</role>";
			}
		}

		private function normalizeFolderPath(string $folder): string
		{
			$folder = trim(str_replace('..', '', $folder));
			if ($folder === '' || $folder === '/') {
				return '/';
			}
			return '/' . trim($folder, '/');
		}

		private function isValidFilename(?string $filename): bool
		{
			if ($filename === null || $filename === '' || $filename === '.' || $filename === '..') {
				return false;
			}
			if (str_contains($filename, '/') || str_contains($filename, '\\')) {
				return false;
			}
			return true;
		}

		public function store(Request $request, Website $website)
		{
			$this->authorize('update', $website);

			$validated = $request->validate([
				'message' => 'required|string|max:10000',
			]);

			// The entire process is wrapped in a single transaction.
			// This ensures that if a security check fails, even the user message can be rolled back
			// if we decide to, though here we commit it along with the error message.
			DB::beginTransaction();

			try {
				// 1. Save user message immediately
				$userMessage = $website->chatMessages()->create([
					'role' => 'user',
					'content' => $validated['message'],
				]);

				// 2. Prepare LLM input
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
					return $path . "\n" . trim($file->content);
				})->implode("\n\n");

				// --- MODIFIED: START ---
				// Add author bio, book details, and image URLs as a reference for every message.
				// This provides the LLM with consistent, rich context about the author's work,
				// while instructing it to prioritize the user's immediate request.
				$user = $website->user()->with('books')->first();
				$authorContext = "--- Author & Book Reference (for context only, user's request takes precedence) ---\n\n";
				$authorContext .= "Author Name: " . ($user->name ?? 'N/A') . "\n";
				$authorContext .= "Author Bio:\n" . ($user->bio ?? 'N/A') . "\n";
				// MODIFIED: Include the author's profile photo URL if it exists.
				if ($user->profile_photo_url) {
					$authorContext .= "Author Photo URL: " . $user->profile_photo_url . "\n";
				}
				$authorContext .= "\n";


				if ($user && $user->books->isNotEmpty()) {
					$authorContext .= "Books:\n";
					foreach ($user->books as $book) {
						// MODIFIED: Check if the current book is the website's primary book.
						$isPrimary = ($book->id === $website->primary_book_id);
						$authorContext .= "- Title: " . $book->title . ($isPrimary ? " (Primary Book)" : "") . "\n";

						if ($book->subtitle) {
							$authorContext .= "  Subtitle: " . $book->subtitle . "\n";
						}
						// MODIFIED: Include the book's cover image URL if it exists.
						if ($book->cover_image_url) {
							$authorContext .= "  Cover Image URL: " . $book->cover_image_url . "\n";
						}
						if ($book->hook) {
							$authorContext .= "  Hook: " . $book->hook . "\n";
						}
						if ($book->about) {
							$authorContext .= "  About: " . $book->about . "\n";
						}
						if ($book->amazon_link) {
							$authorContext .= "  Amazon Link: " . $book->amazon_link . "\n";
						}
						if ($book->other_link) {
							$authorContext .= "  Other Link: " . $book->other_link . "\n";
						}
						$authorContext .= "\n"; // Add a blank line for separation between books.
					}
				}
				$authorContext .= "--- End of Reference ---\n\n";

				$llmUserInput = $authorContext . $fileContext . "\n\n---\n\nUser Request:\n" . $validated['message'];
				// --- MODIFIED: END ---

				$chat_messages = [['role' => 'user', 'content' => $llmUserInput]];
				$llmModel = $website->llm_model ?? env('DEFAULT_LLM', 'google/gemini-2.5-flash-preview-09-2025');

				// 3. Call LLM
				Log::info("Calling LLM for Website ID: {$website->id} with model: {$llmModel}");
				$llmResponse = LlmHelper::call_llm($llmModel, $this->systemPrompt, $chat_messages);

				// 4. Handle LLM Errors
				if (str_starts_with($llmResponse['content'], 'Error:')) {
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

				// --- PRE-PROCESSING AND VALIDATION STAGE ---
				$rawLlmOutput = $llmResponse['content'];
				$pendingOperations = [];
				$securityViolationMessage = null;

				// Step 5A: Parse all proposed operations without applying them
				preg_match_all('/<elooi-rename\s+from_folder="([^"]*)"\s+from_filename="([^"]+)"\s+to_folder="([^"]*)"\s+to_filename="([^"]+)"\s*\/?>/si', $rawLlmOutput, $renameMatches, PREG_SET_ORDER);
				foreach ($renameMatches as $match) {
					$pendingOperations[] = ['type' => 'rename', 'data' => $match];
				}

				preg_match_all('/<elooi-delete\s+folder="([^"]*)"\s+filename="([^"]+)"\s*\/?>/si', $rawLlmOutput, $deleteMatches, PREG_SET_ORDER);
				foreach ($deleteMatches as $match) {
					$pendingOperations[] = ['type' => 'delete', 'data' => $match];
				}

				preg_match_all('/<elooi-write folder="([^"]*)" filename="([^"]+)" description="([^"]*)">\s*(.*?)\s*<\/elooi-write>/s', $rawLlmOutput, $writeMatches, PREG_SET_ORDER);
				foreach ($writeMatches as $match) {
					$pendingOperations[] = ['type' => 'write', 'data' => $match];
				}

				// Step 5B: Validate all operations, especially for forbidden file types
				foreach ($pendingOperations as $operation) {
					if ($operation['type'] === 'write') {
						$filename = trim($operation['data'][2]);
						$content = trim($operation['data'][4]);
						$filetype = pathinfo($filename, PATHINFO_EXTENSION);

						// MODIFIED: Explicitly block PHP file creation as per new guidelines
						if ($filetype === 'php') {
							$securityViolationMessage = "Security error: Creation of PHP files is not allowed. Only HTML, CSS, and JavaScript files can be created.";
							Log::warning("SECURITY BLOCK on Website ID {$website->id}: Attempted to write a PHP file '{$filename}'.");
							break; // Exit the validation loop immediately
						}
					}
				}

				// --- CONDITIONAL EXECUTION STAGE ---
				$filesUpdated = false;
				if ($securityViolationMessage === null) {
					// Step 6: If validation passed, apply all pending changes
					foreach ($pendingOperations as $operation) {
						$filesUpdated = true; // Mark as updated if there are any operations
						$match = $operation['data'];

						if ($operation['type'] === 'rename') {
							$fromFolder = $this->normalizeFolderPath($match[1]);
							$fromFilename = trim($match[2]);
							$toFolder = $this->normalizeFolderPath($match[3]);
							$toFilename = trim($match[4]);

							if (!$this->isValidFilename($fromFilename) || !$this->isValidFilename($toFilename)) {
								Log::warning("Skipping invalid rename: {$fromFilename} to {$toFilename}");
								continue;
							}

							$latestFromFile = WebsiteFile::findLatestActive($website->id, $fromFolder, $fromFilename);
							if ($latestFromFile) {
								WebsiteFile::create([
									'website_id' => $website->id, 'filename' => $latestFromFile->filename, 'folder' => $latestFromFile->folder,
									'filetype' => $latestFromFile->filetype, 'version' => $latestFromFile->version + 1, 'content' => $latestFromFile->content, 'is_deleted' => true,
								]);
								WebsiteFile::create([
									'website_id' => $website->id, 'filename' => $toFilename, 'folder' => $toFolder,
									'filetype' => pathinfo($toFilename, PATHINFO_EXTENSION), 'version' => 1, 'content' => $latestFromFile->content, 'is_deleted' => false,
								]);
								Log::info("Website ID {$website->id}: Renamed '{$fromFolder}/{$fromFilename}' to '{$toFolder}/{$toFilename}'.");
							}
						} elseif ($operation['type'] === 'delete') {
							$deleteFolder = $this->normalizeFolderPath($match[1]);
							$deleteFilename = trim($match[2]);

							if (!$this->isValidFilename($deleteFilename)) {
								Log::warning("Skipping invalid delete: {$deleteFilename}");
								continue;
							}

							$latestFileToDelete = WebsiteFile::findLatestActive($website->id, $deleteFolder, $deleteFilename);
							if ($latestFileToDelete) {
								WebsiteFile::create([
									'website_id' => $website->id, 'filename' => $latestFileToDelete->filename, 'folder' => $latestFileToDelete->folder,
									'filetype' => $latestFileToDelete->filetype, 'version' => $latestFileToDelete->version + 1, 'content' => $latestFileToDelete->content, 'is_deleted' => true,
								]);
								Log::info("Website ID {$website->id}: Marked file '{$deleteFolder}/{$deleteFilename}' deleted.");
							}
						} elseif ($operation['type'] === 'write') {
							$folder = $this->normalizeFolderPath($match[1]);
							$filename = trim($match[2]);
							$content = trim($match[4]);

							if (!$this->isValidFilename($filename)) {
								Log::warning("Skipping invalid write: {$filename}");
								continue;
							}

							$latestVersion = WebsiteFile::where('website_id', $website->id)->where('folder', $folder)->where('filename', $filename)->max('version');
							$newVersion = $latestVersion ? $latestVersion + 1 : 1;

							WebsiteFile::create([
								'website_id' => $website->id, 'filename' => $filename, 'folder' => $folder,
								'filetype' => pathinfo($filename, PATHINFO_EXTENSION), 'version' => $newVersion, 'content' => $content, 'is_deleted' => false,
							]);
							Log::info("Website ID {$website->id}: Wrote file {$folder}/{$filename} version {$newVersion}");
						}
					}
				} // If security check failed, this entire block is skipped, and $filesUpdated remains false.

				// 7. Finalize and Save Assistant Message
				$aiTextResponse = $rawLlmOutput;
				$aiTextResponse = preg_replace('/<elooi-rename[^>]*\/?>/si', '', $aiTextResponse);
				$aiTextResponse = preg_replace('/<elooi-delete[^>]*\/?>/si', '', $aiTextResponse);
				$aiTextResponse = preg_replace('/<elooi-write[^>]*>.*?<\/elooi-write>/s', '', $aiTextResponse);

				if (preg_match('/<elooi-chat-summary>\s*(.*?)\s*<\/elooi-chat-summary>/s', $aiTextResponse, $summaryMatch)) {
					$chatSummary = trim($summaryMatch[1]);
					$aiTextResponse = preg_replace('/<elooi-chat-summary>.*?<\/elooi-chat-summary>/s', '', $aiTextResponse);
				}

				$aiTextResponse = trim(preg_replace('/^\s*$/m', '', $aiTextResponse));

				// Logic to construct the final assistant message is now more robust.
				$finalContent = '';
				if ($securityViolationMessage !== null) {
					// If a security block occurred, create a specific message.
					$finalContent = $aiTextResponse;
					if (empty($finalContent)) {
						$finalContent = "I have reviewed your request.";
					}
					$finalContent .= "\n\n**SYSTEM NOTICE:** The requested changes were blocked due to a security concern. **No files have been modified.**\n(Reason: " . htmlspecialchars($securityViolationMessage) . ")";
				} else {
					// Otherwise, use the AI's response or a default success message.
					$finalContent = $aiTextResponse ?: 'Okay, I have made the requested changes.';
				}

				// MODIFIED: START - Strip HTML tags from the final content to prevent formatting issues in the chat UI.
				// This ensures that only plain text and Markdown-like text are passed to the frontend,
				// avoiding potential layout breaks from raw HTML in the chat bubble.
				$finalContent = strip_tags($finalContent);
				// MODIFIED: END

				$assistantMessage = $website->chatMessages()->create([
					'role' => 'assistant',
					'content' => $finalContent,
				]);

				// 8. Commit and Respond
				DB::commit();

				return response()->json([
					'userMessage' => $userMessage,
					'assistantMessage' => $assistantMessage,
					'files_updated' => $filesUpdated, // This will be false if changes were blocked
					'prompt_tokens' => $llmResponse['prompt_tokens'] ?? 0,
					'completion_tokens' => $llmResponse['completion_tokens'] ?? 0,
				]);
			} catch (\Illuminate\Auth\Access\AuthorizationException $e) {
				DB::rollBack();
				Log::warning("Authorization failed in ChatMessageController: " . $e->getMessage());
				return response()->json(['error' => 'Unauthorized.'], 403);
			} catch (\Exception $e) {
				DB::rollBack();
				Log::error("Error processing chat message for Website ID {$website->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
				$errorMessageForUser = 'Sorry, an unexpected error occurred. Please try again later.';
				// Attempt to save an error message to the chat for user feedback
				try {
					if (!isset($userMessage)) {
						// Create a dummy user message if the failure happened before it was created
						$userMessage = new ChatMessage(['role' => 'user', 'content' => $validated['message']]);
					}
					$assistantMessage = $website->chatMessages()->create([
						'role' => 'assistant',
						'content' => $errorMessageForUser . "\n(Error ref: " . now()->timestamp . ")"
					]);
					DB::commit(); // Commit the error message to the chat
					return response()->json([
						'userMessage' => $userMessage,
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

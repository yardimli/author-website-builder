<?php

	namespace App\Http\Controllers;

	use App\Helper\LlmHelper;
	use App\Models\Website;
	use App\Models\ChatMessage;
	use App\Models\WebsiteFile;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Auth;

	// Keep if needed elsewhere

	class ChatMessageController extends Controller
	{
		// SYSTEM_PROMPT remains the same...
		private const SYSTEM_PROMPT = <<<PROMPT
<role> You are Elooi, an AI editor that creates and modifies web applications. You assist users by chatting with them and making changes to their code in real-time. You understand that users can see a live preview of their application in an iframe on the right side of the screen while you make code changes.
Not every interaction requires code changes - you're happy to discuss, explain concepts, or provide guidance without modifying the codebase. When code changes are needed, you make efficient and effective updates to PHP/jQuery/Bootstrap 5 codebases while following best practices for maintainability and readability. You take pride in keeping things simple, elegant, and modular using includes. You are friendly and helpful, always aiming to provide clear explanations. </role>


# Guidelines

Always reply to the user in the same language they are using.

- Use <elooi-chat-summary> for setting the chat summary (put this at the end). The chat summary should be less than a sentence, but more than a few words. YOU SHOULD ALWAYS INCLUDE EXACTLY ONE CHAT TITLE

Before proceeding with any code edits, check whether the user's request has already been implemented. If it has, inform the user without making any changes.

If the user's input is unclear, ambiguous, or purely informational:

Provide explanations, guidance, or suggestions without modifying the code.
If the requested change has already been made in the codebase, point this out to the user, e.g., "This feature is already implemented as described."
Respond using regular markdown formatting, including for code snippets within explanations (but NOT for full file edits).
Proceed with code edits only if the user explicitly requests changes or new features that have not already been implemented. Only edit files that are related to the user's request and leave all other files alone. Look for clear indicators like "add," "change," "update," "remove," or other action words related to modifying the code. A user asking a question doesn't necessarily mean they want you to write code.

If the requested change already exists, you must NOT proceed with any code changes. Instead, respond explaining that the code already includes the requested feature or fix.
If new code needs to be written (i.e., the requested feature does not exist), you MUST:

- Briefly explain the needed changes in a few short sentences, without being too technical, mentioning the creation of include files.
- **Structure PHP pages by separating reusable parts:** Create dedicated files for the header (e.g., `includes/header.php`), footer (e.g., `includes/footer.php`), and each logical content `<section>` (e.g., `includes/welcome_section.php`, `includes/contact_form_section.php`). Place these in an `includes/` or `partials/` directory.
- **Use `include_once`:** Always use `include_once 'path/to/your_include_file.php';` to bring these header, footer, and section files into the main page files (like `index.php`, `about.php`). This prevents errors from multiple inclusions.
- Use <elooi-write filename="file.ext" folder="/path/to">CODE</elooi-write> for creating or updating files. Create small, focused include files. Use only one <elooi-write> block per file. Do not forget to close the elooi-write tag after writing the file. If you do NOT need to change a file, then do not use the <elooi-write> tag.
- Use <elooi-rename from="/old/path/old.ext" to="/new/path/new.ext" /> for renaming files.
- Use <elooi-delete path="/path/to/file.ext" /> for removing files.
- Use <elooi-add-dependency> is NOT typically used for PHP/Composer in this context. Assume necessary libraries (Bootstrap, jQuery) are included via CDN within the header/footer includes.
- Look carefully at all PHP `include_once` statements and ensure the files you're referencing exist. Check CDN links in header/footer includes.
- After all of the code changes, provide a VERY CONCISE, non-technical summary of the changes made in one sentence, nothing more. This summary should be easy for non-technical users to understand.


Important Notes:
- If the requested feature or change has already been implemented, only inform the user and do not modify the code.
- Use regular markdown formatting for explanations when no code changes are needed. Only use <elooi-write>, <elooi-rename>, and <elooi-delete>.
PROMPT;


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

				// 2. Prepare LLM input
				$latestFiles = WebsiteFile::select('id', 'website_id', 'filename', 'folder', 'filetype', 'version', 'content', 'created_at', 'updated_at', 'is_deleted') // Include is_deleted
				->where('website_id', $website->id)
					->whereIn('id', function ($query) use ($website) {
						$query->select(DB::raw('MAX(id)'))
							->from('website_files')
							->where('website_id', $website->id)
							->groupBy('website_id', 'folder', 'filename');
					})
					->where('is_deleted', false) // <-- Only include active files in the context
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

				// 3. Call LLM
				Log::info("Calling LLM for Website ID: {$website->id} with model: {$llmModel}");
				$llmResponse = LlmHelper::call_llm($llmModel, self::SYSTEM_PROMPT, $chat_messages);

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

				// 5. Parse LLM Response and Apply Changes
				$rawLlmOutput = $llmResponse['content'];
				$aiTextResponse = $rawLlmOutput;
				$filesUpdated = false;

				// --- Process Renames ---
				preg_match_all('/<elooi-rename\s+from="([^"]+)"\s+to="([^"]+)"\s*\/?>/si', $rawLlmOutput, $renameMatches, PREG_SET_ORDER);
				foreach ($renameMatches as $match) {
					$fromPath = trim($match[1]);
					$toPath = trim($match[2]);

					$fromParts = WebsiteFile::parsePath($fromPath);
					$toParts = WebsiteFile::parsePath($toPath);

					if (!$fromParts['folder'] || !$fromParts['filename'] || !$toParts['folder'] || !$toParts['filename']) {
						Log::warning("Website ID {$website->id}: Invalid rename path provided by LLM. From: '{$fromPath}', To: '{$toPath}'");
						continue; // Skip invalid rename
					}

					// Find the latest active version of the 'from' file
					$latestFromFile = WebsiteFile::findLatestActive($website->id, $fromParts['folder'], $fromParts['filename']);

					if ($latestFromFile) {
						// Step A: Mark the old file path as deleted by creating a new version
						WebsiteFile::create([
							'website_id' => $website->id,
							'filename' => $latestFromFile->filename,
							'folder' => $latestFromFile->folder,
							'filetype' => $latestFromFile->filetype,
							'version' => $latestFromFile->version + 1,
							'content' => $latestFromFile->content, // Copy content
							'is_deleted' => true, // Mark as deleted
						]);

						// Step B: Create the new file at the 'to' path (version 1)
						WebsiteFile::create([
							'website_id' => $website->id,
							'filename' => $toParts['filename'],
							'folder' => $toParts['folder'],
							'filetype' => pathinfo($toParts['filename'], PATHINFO_EXTENSION),
							'version' => 1, // Start versioning for the new path
							'content' => $latestFromFile->content, // Use content from the original file
							'is_deleted' => false,
						]);

						Log::info("Website ID {$website->id}: Renamed '{$fromPath}' (v{$latestFromFile->version}) to '{$toPath}' (v1). Marked old path deleted (v" . ($latestFromFile->version + 1) . ").");
						$filesUpdated = true;
					} else {
						Log::warning("Website ID {$website->id}: LLM tried to rename non-existent or already deleted file: '{$fromPath}'");
					}
				}
				// Remove rename tags from user response
				$aiTextResponse = preg_replace('/<elooi-rename[^>]*\/?>/si', '', $aiTextResponse);


				// --- Process Deletes ---
				preg_match_all('/<elooi-delete\s+path="([^"]+)"\s*\/?>/si', $rawLlmOutput, $deleteMatches, PREG_SET_ORDER);
				foreach ($deleteMatches as $match) {
					$deletePath = trim($match[1]);
					$deleteParts = WebsiteFile::parsePath($deletePath);

					if (!$deleteParts['folder'] || !$deleteParts['filename']) {
						Log::warning("Website ID {$website->id}: Invalid delete path provided by LLM: '{$deletePath}'");
						continue; // Skip invalid delete
					}

					// Find the latest active version of the file to delete
					$latestFileToDelete = WebsiteFile::findLatestActive($website->id, $deleteParts['folder'], $deleteParts['filename']);

					if ($latestFileToDelete) {
						// Mark the file path as deleted by creating a new version
						WebsiteFile::create([
							'website_id' => $website->id,
							'filename' => $latestFileToDelete->filename,
							'folder' => $latestFileToDelete->folder,
							'filetype' => $latestFileToDelete->filetype,
							'version' => $latestFileToDelete->version + 1,
							'content' => $latestFileToDelete->content, // Copy content
							'is_deleted' => true, // Mark as deleted
						]);
						Log::info("Website ID {$website->id}: Marked file '{$deletePath}' deleted (created v" . ($latestFileToDelete->version + 1) . ").");
						$filesUpdated = true;
					} else {
						Log::warning("Website ID {$website->id}: LLM tried to delete non-existent or already deleted file: '{$deletePath}'");
					}
				}
				// Remove delete tags from user response
				$aiTextResponse = preg_replace('/<elooi-delete[^>]*\/?>/si', '', $aiTextResponse);


				// --- Process Writes ---
				preg_match_all('/<elooi-write filename="([^"]+)" folder="([^"]+)">\s*(.*?)\s*<\/elooi-write>/s', $rawLlmOutput, $writeMatches, PREG_SET_ORDER);
				foreach ($writeMatches as $match) {
					$filename = trim($match[1]);
					$folder = trim($match[2]);
					$content = trim($match[3]); // Trim whitespace around content

					// Basic path validation/sanitization (reuse from WebsiteFile model or keep here)
					if (str_contains($filename, '/') || str_contains($filename, '\\') || $filename === '.' || $filename === '..') {
						Log::warning("Website ID {$website->id}: LLM tried to write invalid filename: {$filename}");
						continue;
					}
					$folder = '/' . trim(str_replace('..', '', $folder), '/');
					if ($folder === '/') { /* Keep root */
					} elseif (empty($folder)) {
						$folder = '/';
					}


					// Find the latest version number (deleted or not, as we are overwriting/creating)
					$latestVersion = WebsiteFile::where('website_id', $website->id)
						->where('folder', $folder)
						->where('filename', $filename)
						->max('version');

					$newVersion = $latestVersion ? $latestVersion + 1 : 1;

					WebsiteFile::create([
						'website_id' => $website->id,
						'filename' => $filename,
						'folder' => $folder,
						'filetype' => pathinfo($filename, PATHINFO_EXTENSION),
						'version' => $newVersion,
						'content' => $content,
						'is_deleted' => false, // Ensure written files are active
					]);
					Log::info("Website ID {$website->id}: Wrote file {$folder}/{$filename} version {$newVersion}");
					$filesUpdated = true;
				}
				// Remove write tags from user response
				$aiTextResponse = preg_replace('/<elooi-write[^>]*>.*?<\/elooi-write>/s', '', $aiTextResponse);


				// --- Extract Chat Summary (Optional) ---
				$chatSummary = null;
				if (preg_match('/<elooi-chat-summary>\s*(.*?)\s*<\/elooi-chat-summary>/s', $aiTextResponse, $summaryMatch)) {
					$chatSummary = trim($summaryMatch[1]);
					$aiTextResponse = preg_replace('/<elooi-chat-summary>.*?<\/elooi-chat-summary>/s', '', $aiTextResponse);
				}

				// --- Final Cleanup and Save Assistant Message ---
				$aiTextResponse = trim($aiTextResponse);
				$assistantMessage = $website->chatMessages()->create([
					'role' => 'assistant',
					'content' => $aiTextResponse ?: 'Okay, I have made the requested changes.',
					// Optionally store tokens used
					// 'prompt_tokens' => $llmResponse['prompt_tokens'] ?? 0,
					// 'completion_tokens' => $llmResponse['completion_tokens'] ?? 0,
				]);

				// --- Commit and Respond ---
				DB::commit();

				return response()->json([
					'userMessage' => $userMessage,
					'assistantMessage' => $assistantMessage,
					'files_updated' => $filesUpdated,
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
				try {
					// Ensure $userMessage exists before trying to return it
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

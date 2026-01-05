<?php

namespace App\Http\Controllers;

use App\Helper\CodeSanitizerHelper;
use App\Helper\LlmHelper;
use App\Models\Website;
use App\Models\ChatMessage;
use App\Models\WebsiteFile;
use App\Models\WebsiteUserImage; // Import the new model
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage; // Import Storage
use Illuminate\Support\Str; // Import Str

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
            'prompt_image' => 'nullable|image|max:10240', // Validate image (max 10MB)
        ]);

        DB::beginTransaction();

        $promptImagesContext = "";
        try {
            // --- Handle Image Upload ---
            $promptImageIds = null;
            $base64Image = null;
            $mimeType = null;

            if ($request->hasFile('prompt_image')) {
                $file = $request->file('prompt_image');
                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $file->getClientOriginalExtension();

                // Sanitize filename and fallback if empty
                $slugName = Str::slug($originalName);
                if (empty($slugName)) {
                    $slugName = 'image-' . Str::random(4);
                }

                $folderPath = 'website-user-images/';
                $finalFilename = $slugName . '.' . $extension;

                // Standard filename deduplication logic (filename(1).ext, filename(2).ext)
                if (Storage::disk('public')->exists($folderPath . $finalFilename)) {
                    $counter = 1;
                    while (true) {
                        // Construct the candidate filename: name(1).ext
                        $candidateFilename = $slugName . '(' . $counter . ').' . $extension;

                        // Check if this specific version exists
                        if (!Storage::disk('public')->exists($folderPath . $candidateFilename)) {
                            $finalFilename = $candidateFilename;
                            break; // Found a unique name
                        }
                        $counter++;
                    }
                }

                $imagePath = $folderPath . $finalFilename;

                // Save to storage
                Storage::disk('public')->put($imagePath, file_get_contents($file));

                // Create Record
                $websiteUserImage = WebsiteUserImage::create([
                    'website_id' => $website->id,
                    'image_file_path' => $imagePath,
                ]);

                $promptImageIds = [$websiteUserImage->id];

                // Prepare for LLM (Base64 Encode)
                $mimeType = $file->getMimeType();
                $base64Image = base64_encode(file_get_contents($file));

                $promptImagesContext  = "\n\n--- PROMPT IMAGE ASSET (MUST USE EXACTLY) ---\n";
                $promptImagesContext .= "PROMPT_IMAGE_1_URL = " . asset('storage/' . $imagePath) . "\n";
                $promptImagesContext .= "\nRULES:\n";
                $promptImagesContext .= "1) If the user asks to include an image, show an image, add a picture, use the uploaded image, or references using an image/photograph/screenshot, you MUST embed PROMPT_IMAGE_1_URL in the HTML output.\n";
                $promptImagesContext .= "2) When embedding the image, you MUST use this exact HTML format (only alt text may change):\n";
                $promptImagesContext .= "   <img src=\"PROMPT_IMAGE_1_URL\" alt=\"ALT_TEXT\" />\n";
                $promptImagesContext .= "3) You are NOT allowed to use placeholder images, example.com, lorem picsum, unsplash, or any other image URL. Use PROMPT_IMAGE_1_URL only.\n";
                $promptImagesContext .= "4) ALT_TEXT must be a short descriptive title derived from the user message below (5-12 words). Do not leave alt empty.\n";
                $promptImagesContext .= "5) If the user does NOT request an image, do NOT output any <img> tag.\n";
                $promptImagesContext .= "6) You can add style or class attribute accordingly in the <img> tag.\n";
                $promptImagesContext .= "\nUSER MESSAGE (source for ALT_TEXT):\n";
                $promptImagesContext .= $validated['message'] . "\n";

                $promptImagesContext .= "\nFINAL CHECK BEFORE YOU RESPOND:\n";
                $promptImagesContext .= "- If you included an <img> tag, confirm its src equals PROMPT_IMAGE_1_URL exactly and no other image URLs exist.\n";

                /*$promptImagesContext = "\n\n--- Prompt Images Reference ---";
                $promptImagesContext .= "Prompt Image1 URL:".asset('storage/' . $imagePath)."\n";
                $promptImagesContext .= "Use Prompt Image1 when the user mentions of image usage in the text prompt. Use the Prompt Image1 URL as the value of the \"src\" attribute in the image tag for the Prompt Image1.\n";
                $promptImagesContext .= "Get a title from texts \" ".$validated['message']." \", and set it as the value of the \"alt\" attribute in the image tag for the Prompt Image1 \n";*/
                $promptImagesContext .= "\n";
            }

            // 1. Save user message immediately
            $userMessage = $website->chatMessages()->create([
                'role' => 'user',
                'content' => $validated['message'],
                'prompt_images_ids' => $promptImageIds, // Save the array of IDs
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

            // Add author bio, book details, and image URLs as a reference
            $user = $website->user()->with('books')->first();
            $authorContext = "--- Author & Book Reference (for context only, user's request takes precedence) ---\n\n";
            $authorContext .= "Author Name: " . ($user->name ?? 'N/A') . "\n";
            $authorContext .= "Author Bio:\n" . ($user->bio ?? 'N/A') . "\n";

            if (!empty($user->profile_photo_path)){
                $photoUrl = $user->profile_photo_url;
                if (!str_starts_with($user->profile_photo_url, 'http://') && !str_starts_with($user->profile_photo_url, 'https://')) {
                    $photoUrl = asset('storage/' . $user->profile_photo_url);
                }
                $authorContext .= "Author Photo URL(Profile Photo URL): " . $photoUrl . "\n";
            }
            $authorContext .= "\n";


            if ($user && $user->books->isNotEmpty()) {
                $authorContext .= "Books:\n";
                foreach ($user->books as $book) {
                    $isPrimary = ($book->id === $website->primary_book_id);
                    $authorContext .= "- Title: " . $book->title . ($isPrimary ? " (Primary Book)" : "") . "\n";

                    if ($book->subtitle) {
                        $authorContext .= "  Subtitle: " . $book->subtitle . "\n";
                    }
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
                    $authorContext .= "\n";
                }
            }
            $authorContext .= "--- End of Reference ---\n\n";

            $llmUserInput = $authorContext . $promptImagesContext . $fileContext . "\n\n---\n\nUser Request:\n" . $validated['message'];

            // Fetch history
            $history = ChatMessage::where('website_id', $website->id)
                ->where('id', '<', $userMessage->id)
                ->where('deleted', false)
                ->orderBy('id', 'desc')
                ->take(12)
                ->get()
                ->reverse();

            $chat_messages = [];

            foreach ($history as $msg) {
                $chat_messages[] = [
                    'role' => $msg->role,
                    'content' => $msg->content
                ];
            }

            // Prepare Current Message Payload (Multi-modal if image exists)
            if ($base64Image) {
                $currentMessageContent = [
                    [
                        'type' => 'text',
                        'text' => $llmUserInput
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => "data:{$mimeType};base64,{$base64Image}"
                        ]
                    ]
                ];
                $chat_messages[] = ['role' => 'user', 'content' => $currentMessageContent];
            } else {
                $chat_messages[] = ['role' => 'user', 'content' => $llmUserInput];
            }

            $llmModel = $website->llm_model ?? env('DEFAULT_LLM', 'google/gemini-2.5-flash-preview-09-2025');

            // 3. Call LLM
            Log::info("Calling LLM for Website ID: {$website->id} with model: {$llmModel}" . ($base64Image ? " (with image)" : ""));
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

            foreach ($pendingOperations as $operation) {
                if ($operation['type'] === 'write') {
                    $filename = trim($operation['data'][2]);
                    $filetype = pathinfo($filename, PATHINFO_EXTENSION);
                    if ($filetype === 'php') {
                        $securityViolationMessage = "Security error: Creation of PHP files is not allowed. Only HTML, CSS, and JavaScript files can be created.";
                        Log::warning("SECURITY BLOCK on Website ID {$website->id}: Attempted to write a PHP file '{$filename}'.");
                        break;
                    }
                }
            }

            // --- CONDITIONAL EXECUTION STAGE ---
            $filesUpdated = false;

            // --- Initialize an array to track IDs of files created in this transaction ---
            $newlyCreatedFileIds = [];

            if ($securityViolationMessage === null) {
                foreach ($pendingOperations as $operation) {
                    $filesUpdated = true;
                    $match = $operation['data'];

                    if ($operation['type'] === 'rename') {
                        $fromFolder = $this->normalizeFolderPath($match[1]);
                        $fromFilename = trim($match[2]);
                        $toFolder = $this->normalizeFolderPath($match[3]);
                        $toFilename = trim($match[4]);

                        if (!$this->isValidFilename($fromFilename) || !$this->isValidFilename($toFilename)) {
                            continue;
                        }

                        $latestFromFile = WebsiteFile::findLatestActive($website->id, $fromFolder, $fromFilename);
                        if ($latestFromFile) {
                            $f1 = WebsiteFile::create([
                                'website_id' => $website->id, 'filename' => $latestFromFile->filename, 'folder' => $latestFromFile->folder,
                                'filetype' => $latestFromFile->filetype, 'version' => $latestFromFile->version + 1, 'content' => $latestFromFile->content, 'is_deleted' => true,
                            ]);
                            $newlyCreatedFileIds[] = $f1->id;

                            $f2 = WebsiteFile::create([
                                'website_id' => $website->id, 'filename' => $toFilename, 'folder' => $toFolder,
                                'filetype' => pathinfo($toFilename, PATHINFO_EXTENSION), 'version' => 1, 'content' => $latestFromFile->content, 'is_deleted' => false,
                            ]);
                            $newlyCreatedFileIds[] = $f2->id;
                        }
                    } elseif ($operation['type'] === 'delete') {
                        $deleteFolder = $this->normalizeFolderPath($match[1]);
                        $deleteFilename = trim($match[2]);

                        if (!$this->isValidFilename($deleteFilename)) {
                            continue;
                        }

                        $latestFileToDelete = WebsiteFile::findLatestActive($website->id, $deleteFolder, $deleteFilename);
                        if ($latestFileToDelete) {
                            $f1 = WebsiteFile::create([
                                'website_id' => $website->id, 'filename' => $latestFileToDelete->filename, 'folder' => $latestFileToDelete->folder,
                                'filetype' => $latestFileToDelete->filetype, 'version' => $latestFileToDelete->version + 1, 'content' => $latestFileToDelete->content, 'is_deleted' => true,
                            ]);
                            $newlyCreatedFileIds[] = $f1->id;
                        }
                    } elseif ($operation['type'] === 'write') {
                        $folder = $this->normalizeFolderPath($match[1]);
                        $filename = trim($match[2]);
                        $content = trim($match[4]);

                        if (!$this->isValidFilename($filename)) {
                            continue;
                        }

                        $latestVersion = WebsiteFile::where('website_id', $website->id)->where('folder', $folder)->where('filename', $filename)->max('version');
                        $newVersion = $latestVersion ? $latestVersion + 1 : 1;

                        $f1 = WebsiteFile::create([
                            'website_id' => $website->id, 'filename' => $filename, 'folder' => $folder,
                            'filetype' => pathinfo($filename, PATHINFO_EXTENSION), 'version' => $newVersion, 'content' => $content, 'is_deleted' => false,
                        ]);
                        $newlyCreatedFileIds[] = $f1->id;
                    }
                }
            }

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

            $finalContent = '';
            if ($securityViolationMessage !== null) {
                $finalContent = $aiTextResponse;
                if (empty($finalContent)) {
                    $finalContent = "I have reviewed your request.";
                }
                $finalContent .= "\n\n**SYSTEM NOTICE:** The requested changes were blocked due to a security concern. **No files have been modified.**\n(Reason: " . htmlspecialchars($securityViolationMessage) . ")";
            } else {
                $finalContent = $aiTextResponse ?: 'Okay, I have made the requested changes.';
            }

            $finalContent = str_replace(['<br>', '<br/>', '<br />'], "\n", $finalContent);
            $finalContent = strip_tags($finalContent);
            $finalContent = str_replace("\n", '<br>', $finalContent);

            $assistantMessage = $website->chatMessages()->create([
                'role' => 'assistant',
                'content' => $finalContent,
            ]);

            // --- LINK FILES TO CHAT MESSAGES ---
            // Now that we have the assistant message ID, we can update all files created in this loop.
            // We use the $newlyCreatedFileIds array to perform a single update query.
            if (!empty($newlyCreatedFileIds)) {
                WebsiteFile::whereIn('id', $newlyCreatedFileIds)->update([
                    'chat_messages_ids' => json_encode([$userMessage->id, $assistantMessage->id])
                ]);
            }

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
                if (!isset($userMessage)) {
                    $userMessage = new ChatMessage(['role' => 'user', 'content' => $validated['message'] ?? '']);
                }
                $assistantMessage = $website->chatMessages()->create([
                    'role' => 'assistant',
                    'content' => $errorMessageForUser . "\n(Error ref: " . now()->timestamp . ")"
                ]);
                DB::commit();
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

<?php

	namespace App\Http\Controllers;

	use App\Helper\LlmHelper;
	use App\Models\Book;
	use Illuminate\Http\RedirectResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Redirect;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\View\View;

	/**
	 * NEW: This controller handles all book management functionality,
	 * including CRUD operations and AI-powered content generation.
	 */
	class BookController extends Controller
	{
		/**
		 * Display the user's book management page.
		 */
		public function index(Request $request): View
		{
			$user = $request->user()->load('books');
			return view('profile.books', [
				'user' => $user,
				'books' => $user->books,
			]);
		}

		/**
		 * Store a newly created book in storage.
		 */
		public function store(Request $request): RedirectResponse
		{
			$validated = $request->validate([
				'title' => ['required', 'string', 'max:255'],
				'subtitle' => ['nullable', 'string', 'max:255'],
				'hook' => ['nullable', 'string', 'max:1000'],
				'about' => ['nullable', 'string', 'max:5000'],
				'extract' => ['nullable', 'string', 'max:65000'],
				'amazon_link' => ['nullable', 'url', 'max:500'],
				'other_link' => ['nullable', 'url', 'max:500'],
				'published_at' => ['nullable', 'date'],
				'is_series' => ['boolean'],
				'series_name' => ['nullable', 'required_if:is_series,true', 'string', 'max:255'],
				'series_number' => ['nullable', 'required_if:is_series,true', 'integer', 'min:1'],
				'cover_image' => ['nullable', 'image', 'max:2048'],
			]);

			$user = $request->user();
			$bookData = $validated;
			$bookData['user_id'] = $user->id;

			if ($request->hasFile('cover_image')) {
				$path = $request->file('cover_image')->store('book-covers', 'public');
				$bookData['cover_image_path'] = $path;
			}

			unset($bookData['is_series']);
			if (!($validated['is_series'] ?? false)) {
				$bookData['series_name'] = null;
				$bookData['series_number'] = null;
			}

			Book::create($bookData);

			return Redirect::route('profile.books.edit')->with('status', 'book-created');
		}

		/**
		 * Update the specified book in storage.
		 */
		public function update(Request $request, Book $book): RedirectResponse
		{
			$this->authorize('update', $book); // Assuming a BookPolicy exists

			$validated = $request->validate([
				'title' => ['required', 'string', 'max:255'],
				'subtitle' => ['nullable', 'string', 'max:255'],
				'hook' => ['nullable', 'string', 'max:1000'],
				'about' => ['nullable', 'string', 'max:5000'],
				'extract' => ['nullable', 'string', 'max:65000'],
				'amazon_link' => ['nullable', 'url', 'max:500'],
				'other_link' => ['nullable', 'url', 'max:500'],
				'published_at' => ['nullable', 'date'],
				'is_series' => ['boolean'],
				'series_name' => ['nullable', 'required_if:is_series,true', 'string', 'max:255'],
				'series_number' => ['nullable', 'required_if:is_series,true', 'integer', 'min:1'],
				'cover_image' => ['nullable', 'image', 'max:2048'],
			]);

			$bookData = $validated;

			if ($request->hasFile('cover_image')) {
				if ($book->cover_image_path && Storage::disk('public')->exists($book->cover_image_path)) {
					Storage::disk('public')->delete($book->cover_image_path);
				}
				$path = $request->file('cover_image')->store('book-covers', 'public');
				$bookData['cover_image_path'] = $path;
			} elseif ($request->has('remove_cover_image') && $request->input('remove_cover_image')) {
				if ($book->cover_image_path && Storage::disk('public')->exists($book->cover_image_path)) {
					Storage::disk('public')->delete($book->cover_image_path);
				}
				$bookData['cover_image_path'] = null;
			}

			unset($bookData['is_series']);
			unset($bookData['remove_cover_image']);

			if (!($validated['is_series'] ?? false)) {
				$bookData['series_name'] = null;
				$bookData['series_number'] = null;
			}

			$book->update($bookData);

			return Redirect::route('profile.books.edit')->with('status', 'book-updated');
		}

		/**
		 * Remove the specified book from storage.
		 */
		public function destroy(Request $request, Book $book): RedirectResponse
		{
			$this->authorize('delete', $book); // Assuming a BookPolicy exists

			if ($book->cover_image_path && Storage::disk('public')->exists($book->cover_image_path)) {
				Storage::disk('public')->delete($book->cover_image_path);
			}

			$book->delete();

			return Redirect::route('profile.books.edit')->with('status', 'book-deleted');
		}

		/**
		 * Generate AI placeholder for the book hook.
		 */
		public function generateBookHookPlaceholder(Request $request)
		{
			$validated = $request->validate([
				'title' => ['required', 'string', 'max:255'],
				'subtitle' => ['nullable', 'string', 'max:255'],
			]);

			$title = $validated['title'];
			$subtitle = $validated['subtitle'] ?? '';

			$system_prompt = "You are an assistant helping an author write marketing copy for their book. Generate a short, compelling hook or tagline (1-2 sentences max) suitable for a book back cover or online description.";
			$user_message = "Generate a hook/tagline for a book titled: \"{$title}\"";
			if ($subtitle) {
				$user_message .= "\nSubtitle: \"{$subtitle}\"";
			}

			return $this->callBookAiGenerator($request->user(), $system_prompt, $user_message, 'Hook');
		}

		/**
		 * Generate AI placeholder for the book about section.
		 */
		public function generateBookAboutPlaceholder(Request $request)
		{
			$validated = $request->validate([
				'title' => ['required', 'string', 'max:255'],
				'subtitle' => ['nullable', 'string', 'max:255'],
			]);

			$title = $validated['title'];
			$subtitle = $validated['subtitle'] ?? '';

			$system_prompt = "You are an assistant helping an author write marketing copy for their book. Generate an engaging 'About the Book' section (around 2-3 short paragraphs) suitable for a book back cover or online description. Focus on introducing the premise, main conflict/characters, and hinting at the stakes or themes, without giving away major spoilers.";
			$user_message = "Generate an 'About the Book' section for a book titled: \"{$title}\"";
			if ($subtitle) {
				$user_message .= "\nSubtitle: \"{$subtitle}\"";
			}

			return $this->callBookAiGenerator($request->user(), $system_prompt, $user_message, 'About');
		}

		/**
		 * Private helper to call the LLM for book content generation.
		 */
		private function callBookAiGenerator($user, $system_prompt, $user_message, $fieldType)
		{
			$chat_messages = [['role' => 'user', 'content' => $user_message]];
			$llmModel = env('DEFAULT_LLM', 'mistralai/mixtral-8x7b-instruct');

			Log::info("Requesting AI book {$fieldType} generation for user {$user->id}");
			$llmResponse = LlmHelper::call_llm($llmModel, $system_prompt, $chat_messages);

			if (str_starts_with($llmResponse['content'], 'Error:')) {
				Log::error("AI Book {$fieldType} Generation Error for user {$user->id}: " . $llmResponse['content']);
				return response()->json(['error' => "Failed to generate {$fieldType}. " . $llmResponse['content']], 500);
			}

			Log::info("AI book {$fieldType} generated successfully for user {$user->id}");
			return response()->json(['generated_text' => trim($llmResponse['content'])]);
		}
	}

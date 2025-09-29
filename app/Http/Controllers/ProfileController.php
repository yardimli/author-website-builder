<?php

	namespace App\Http\Controllers;

	use App\Helper\LlmHelper;
	use App\Http\Requests\ProfileUpdateRequest;
	use App\Models\Book;
	use Illuminate\Contracts\Auth\MustVerifyEmail;
	use Illuminate\Http\RedirectResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Illuminate\Support\Facades\Redirect;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Validation\Rule;
	use Illuminate\Support\Str;
	use Illuminate\Support\Facades\Log;
	use Illuminate\View\View;

	class ProfileController extends Controller
	{
		/**
		 * Display the user's profile form.
		 * MODIFIED: This method now returns a Blade View for just the core profile info.
		 */
		public function edit(Request $request): View
		{
			// MODIFIED: Render the profile.edit Blade view for the main profile page.
			// Book data is no longer needed here and is handled by editBooks().
			return view('profile.edit', [
				'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
				'status' => session('status'),
				'user' => $request->user(),
			]);
		}

		/**
		 * NEW: Display the user's book management page.
		 */
		public function editBooks(Request $request): View
		{
			$user = $request->user()->load('books');
			return view('profile.books', [
				'user' => $user,
				'books' => $user->books,
			]);
		}

		/**
		 * NEW: Display the user's security settings page.
		 */
		public function editSecurity(Request $request): View
		{
			return view('profile.security', [
				'user' => $request->user(),
			]);
		}

		/**
		 * NEW: Display the user's account management page.
		 */
		public function editAccount(Request $request): View
		{
			return view('profile.account', [
				'user' => $request->user(),
			]);
		}


		/**
		 * Update the user's core profile information (name, email).
		 * NOTE: No changes needed here, RedirectResponse is compatible with Blade.
		 */
		public function update(ProfileUpdateRequest $request): RedirectResponse
		{
			$request->user()->fill($request->validated());

			if ($request->user()->isDirty('email')) {
				$request->user()->email_verified_at = null;
			}

			$request->user()->save();

			return Redirect::route('profile.edit')->with('status', 'profile-information-updated');
		}

		/**
		 * Update the user's profile photo.
		 * NOTE: No changes needed here.
		 */
		public function updateProfilePhoto(Request $request): RedirectResponse
		{
			$request->validate([
				'photo' => ['required', 'image', 'max:2048'], // Max 2MB
			]);

			$user = $request->user();
			$photo = $request->file('photo');

			// Delete old photo if exists
			if ($user->profile_photo_path && Storage::disk('public')->exists($user->profile_photo_path)) {
				Storage::disk('public')->delete($user->profile_photo_path);
			}

			// Store new photo
			$path = $photo->store('profile-photos', 'public');

			$user->forceFill([
				'profile_photo_path' => $path,
			])->save();

			return Redirect::route('profile.edit')->with('status', 'profile-photo-updated');
		}

		/**
		 * Delete the user's profile photo.
		 * NOTE: No changes needed here.
		 */
		public function deleteProfilePhoto(Request $request): RedirectResponse
		{
			$user = $request->user();

			if ($user->profile_photo_path) {
				Storage::disk('public')->delete($user->profile_photo_path);
				$user->forceFill(['profile_photo_path' => null])->save();
			}

			return Redirect::route('profile.edit')->with('status', 'profile-photo-deleted');
		}


		/**
		 * Update the user's bio.
		 * NOTE: No changes needed here.
		 */
		public function updateBio(Request $request): RedirectResponse
		{
			$request->validate([
				'bio' => ['nullable', 'string', 'max:5000'], // Adjust max length as needed
			]);

			$request->user()->forceFill([
				'bio' => $request->input('bio'),
			])->save();

			return Redirect::route('profile.edit')->with('status', 'profile-bio-updated');
		}

		/**
		 * Generate AI placeholder for the bio.
		 * MODIFIED: This method now uses the user's name and book data for a richer context.
		 */
		public function generateBioPlaceholder(Request $request)
		{
			$request->validate([
				'current_bio' => ['nullable', 'string', 'max:3000'], // Limit input to AI
			]);

			$currentBio = $request->input('current_bio', '');
			$user = $request->user()->load('books'); // Eager load books

			// --- MODIFIED: Build a detailed context string from user's books ---
			$bookContext = "Here is a list of my books:\n";
			if ($user->books->isEmpty()) {
				$bookContext .= "- I have not added any books yet.\n";
			} else {
				foreach ($user->books as $book) {
					$bookContext .= "- Title: " . $book->title . "\n";
					if ($book->subtitle) $bookContext .= "  Subtitle: " . $book->subtitle . "\n";
					if ($book->series_name) $bookContext .= "  Series: " . $book->series_name . " #" . $book->series_number . "\n";
					if ($book->hook) $bookContext .= "  Hook: " . $book->hook . "\n";
					if ($book->about) $bookContext .= "  About: " . $book->about . "\n\n";
				}
			}

			// --- MODIFIED: Updated prompts for better context ---
			$system_prompt = "You are an assistant helping an author write their website bio. Your task is to generate a compelling, fictional author bio of about 2-3 short paragraphs. Use the author's name and the list of their books to infer a plausible genre, style, and persona. The bio should sound authentic and engaging. Focus on common author bio elements: hint at their genre, common themes in their work, a touch of personality or a fictional background, and a call to action (e.g., 'explore their books').";

			$user_message = "My name is " . $user->name . ".\n\n" . $bookContext . "\n\nHere's the current draft of my bio (it might be empty):\n---\n" . $currentBio . "\n---\n\nPlease generate a new, creative placeholder bio for me based on my name and book details.";


			$chat_messages = [['role' => 'user', 'content' => $user_message]];
			$llmModel = env('DEFAULT_LLM', 'mistralai/mixtral-8x7b-instruct'); // Or use a user-specific setting if available

			Log::info("Requesting AI bio generation for user {$user->id}");
			$llmResponse = LlmHelper::call_llm($llmModel, $system_prompt, $chat_messages);

			if (str_starts_with($llmResponse['content'], 'Error:')) {
				Log::error("AI Bio Generation Error for user {$user->id}: " . $llmResponse['content']);
				return response()->json(['error' => 'Failed to generate bio. ' . $llmResponse['content']], 500);
			}

			Log::info("AI bio generated successfully for user {$user->id}");
			return response()->json(['generated_bio' => trim($llmResponse['content'])]);
		}

		/**
		 * NOTE: No changes needed for AI helper methods.
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

		private function callBookAiGenerator($user, $system_prompt, $user_message, $fieldType)
		{
			$chat_messages = [['role' => 'user', 'content' => $user_message]];
			$llmModel = env('DEFAULT_LLM', 'mistralai/mixtral-8x7b-instruct'); // Or use a user-specific setting

			Log::info("Requesting AI book {$fieldType} generation for user {$user->id}");
			$llmResponse = LlmHelper::call_llm($llmModel, $system_prompt, $chat_messages);

			if (str_starts_with($llmResponse['content'], 'Error:')) {
				Log::error("AI Book {$fieldType} Generation Error for user {$user->id}: " . $llmResponse['content']);
				return response()->json(['error' => "Failed to generate {$fieldType}. " . $llmResponse['content']], 500);
			}

			Log::info("AI book {$fieldType} generated successfully for user {$user->id}");
			return response()->json(['generated_text' => trim($llmResponse['content'])]);
		}



		// --- BOOK MANAGEMENT ---

		/**
		 * Store a newly created book in storage.
		 */
		public function storeBook(Request $request): RedirectResponse
		{
			$validated = $request->validate([
				'title' => ['required', 'string', 'max:255'],
				'subtitle' => ['nullable', 'string', 'max:255'],
				'hook' => ['nullable', 'string', 'max:1000'],
				'about' => ['nullable', 'string', 'max:5000'],
				'extract' => ['nullable', 'string', 'max:65000'], // Long text
				'amazon_link' => ['nullable', 'url', 'max:500'],
				'other_link' => ['nullable', 'url', 'max:500'],
				'published_at' => ['nullable', 'date'],
				'is_series' => ['boolean'], // Use boolean for checkbox
				'series_name' => ['nullable', 'required_if:is_series,true', 'string', 'max:255'],
				'series_number' => ['nullable', 'required_if:is_series,true', 'integer', 'min:1'],
				'cover_image' => ['nullable', 'image', 'max:2048'], // Max 2MB
			]);

			$user = $request->user();
			$bookData = $validated;
			$bookData['user_id'] = $user->id;

			// Handle cover image upload
			if ($request->hasFile('cover_image')) {
				$path = $request->file('cover_image')->store('book-covers', 'public');
				$bookData['cover_image_path'] = $path;
			}

			// Unset the helper boolean field if it exists
			unset($bookData['is_series']);
			// Clear series info if not part of a series
			if (!($validated['is_series'] ?? false)) {
				$bookData['series_name'] = null;
				$bookData['series_number'] = null;
			}


			Book::create($bookData);

			// MODIFIED: Redirect back to the books management page.
			return Redirect::route('profile.books.edit')->with('status', 'book-created');
		}

		/**
		 * Update the specified book in storage.
		 */
		public function updateBook(Request $request, Book $book): RedirectResponse
		{
			// Authorize: Ensure the user owns this book
			if ($request->user()->id !== $book->user_id) {
				abort(403);
			}

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
				'cover_image' => ['nullable', 'image', 'max:2048'], // For updating cover
			]);

			$bookData = $validated;

			// Handle cover image update
			if ($request->hasFile('cover_image')) {
				// Delete old cover if exists
				if ($book->cover_image_path && Storage::disk('public')->exists($book->cover_image_path)) {
					Storage::disk('public')->delete($book->cover_image_path);
				}
				// Store new cover
				$path = $request->file('cover_image')->store('book-covers', 'public');
				$bookData['cover_image_path'] = $path;
			} elseif ($request->has('remove_cover_image') && $request->input('remove_cover_image')) {
				// Optional: Add a checkbox/flag in the form to remove the cover
				if ($book->cover_image_path && Storage::disk('public')->exists($book->cover_image_path)) {
					Storage::disk('public')->delete($book->cover_image_path);
				}
				$bookData['cover_image_path'] = null;
			}


			// Unset the helper boolean field if it exists
			unset($bookData['is_series']);
			unset($bookData['remove_cover_image']); // Clean up if using this flag

			// Clear series info if not part of a series
			if (!($validated['is_series'] ?? false)) {
				$bookData['series_name'] = null;
				$bookData['series_number'] = null;
			}

			$book->update($bookData);

			// MODIFIED: Redirect back to the books management page.
			return Redirect::route('profile.books.edit')->with('status', 'book-updated');
		}

		/**
		 * Remove the specified book from storage.
		 */
		public function destroyBook(Request $request, Book $book): RedirectResponse
		{
			// Authorize: Ensure the user owns this book
			if ($request->user()->id !== $book->user_id) {
				abort(403);
			}

			// Delete cover image if exists
			if ($book->cover_image_path && Storage::disk('public')->exists($book->cover_image_path)) {
				Storage::disk('public')->delete($book->cover_image_path);
			}

			$book->delete();

			// MODIFIED: Redirect back to the books management page.
			return Redirect::route('profile.books.edit')->with('status', 'book-deleted');
		}


		/**
		 * Delete the user's account.
		 * NOTE: No changes needed here.
		 */
		public function destroy(Request $request): RedirectResponse
		{
			$request->validate([
				'password' => ['required', 'current_password'],
			]);

			$user = $request->user();

			// Optional: Delete associated storage (profile photos, book covers)
			// Be careful with this - maybe make it optional or background job
			if ($user->profile_photo_path && Storage::disk('public')->exists($user->profile_photo_path)) {
				Storage::disk('public')->delete($user->profile_photo_path);
			}
			foreach($user->books as $book) {
				if ($book->cover_image_path && Storage::disk('public')->exists($book->cover_image_path)) {
					Storage::disk('public')->delete($book->cover_image_path);
				}
				// Note: Books themselves will be deleted by cascade constraint if set up correctly,
				// otherwise delete them manually here before deleting the user.
				// $book->delete(); // Not needed if cascade is on
			}


			Auth::logout();

			$user->delete(); // This should trigger cascade delete for books if set

			$request->session()->invalidate();
			$request->session()->regenerateToken();

			return Redirect::to('/');
		}
	}

<?php

	namespace App\Http\Controllers;

	use App\Helper\LlmHelper;
	use App\Http\Requests\ProfileUpdateRequest;
	use App\Models\Book;
	use Illuminate\Contracts\Auth\MustVerifyEmail;
	use Illuminate\Http\RedirectResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Redirect;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\View\View;

	/**
	 * MODIFIED: This controller now handles only the user's core profile,
	 * security, and account management. Book and import functionalities
	 * have been moved to their own dedicated controllers.
	 */
	class ProfileController extends Controller
	{
		/**
		 * Display the user's core profile form.
		 * MODIFIED: Now handles the wizard flow.
		 */
		public function edit(Request $request): View
		{
			// NEW: Check if this is part of the wizard flow.
			$isWizard = $request->has('wizard');
			$wizardStep = $isWizard ? 2 : 0;

			return view('profile.edit', [
				'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
				'status' => session('status'),
				'user' => $request->user(),
				'isWizard' => $isWizard, // NEW
				'wizardStep' => $wizardStep, // NEW
			]);
		}

		/**
		 * Display the user's security settings page.
		 */
		public function editSecurity(Request $request): View
		{
			return view('profile.security', [
				'user' => $request->user(),
			]);
		}

		/**
		 * Display the user's account management page.
		 */
		public function editAccount(Request $request): View
		{
			return view('profile.account', [
				'user' => $request->user(),
			]);
		}

		/**
		 * Update the user's core profile information (name, email).
		 * MODIFIED: Redirects to the next wizard step if applicable.
		 */
		public function update(ProfileUpdateRequest $request): RedirectResponse
		{
			$request->user()->fill($request->validated());

			if ($request->user()->isDirty('email')) {
				$request->user()->email_verified_at = null;
			}

			$request->user()->save();

			// NEW: If in wizard mode, redirect to the next step.
			if ($request->has('is_wizard')) {
				return Redirect::route('websites.create', ['wizard' => '3']);
			}

			return Redirect::route('profile.edit')->with('status', 'profile-information-updated');
		}

		/**
		 * Update the user's profile photo.
		 * MODIFIED: Redirects to the next wizard step if applicable.
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

			// NEW: If in wizard mode, redirect to the next step.
			if ($request->has('is_wizard')) {
				return Redirect::route('websites.create', ['wizard' => '3']);
			}

			return Redirect::route('profile.edit')->with('status', 'profile-photo-updated');
		}

		/**
		 * Delete the user's profile photo.
		 */
		public function deleteProfilePhoto(Request $request): RedirectResponse
		{
			$user = $request->user();

			if ($user->profile_photo_path) {
				Storage::disk('public')->delete($user->profile_photo_path);
				$user->forceFill(['profile_photo_path' => null])->save();
			}

			// NEW: If in wizard mode, redirect to the next step.
			if ($request->has('is_wizard')) {
				return Redirect::route('websites.create', ['wizard' => '3']);
			}

			return Redirect::route('profile.edit')->with('status', 'profile-photo-deleted');
		}

		/**
		 * Update the user's bio.
		 * MODIFIED: Redirects to the next wizard step if applicable.
		 */
		public function updateBio(Request $request): RedirectResponse
		{
			$request->validate([
				'bio' => ['nullable', 'string', 'max:5000'],
			]);

			$request->user()->forceFill([
				'bio' => $request->input('bio'),
			])->save();

			// NEW: If in wizard mode, redirect to the next step.
			if ($request->has('is_wizard')) {
				return Redirect::route('websites.create', ['wizard' => '3']);
			}

			return Redirect::route('profile.edit')->with('status', 'profile-bio-updated');
		}

		/**
		 * Generate AI placeholder for the bio.
		 */
		public function generateBioPlaceholder(Request $request)
		{
			$request->validate([
				'current_bio' => ['nullable', 'string', 'max:3000'],
			]);

			$currentBio = $request->input('current_bio', '');
			$user = $request->user()->load('books');

			// Build a detailed context string from user's books
			$bookContext = "Here is a list of my books:\n";
			if ($user->books->isEmpty()) {
				$bookContext .= "- I have not added any books yet.\n";
			} else {
				foreach ($user->books as $book) {
					$bookContext .= "- Title: " . $book->title . "\n";
					if ($book->subtitle) {
						$bookContext .= "  Subtitle: " . $book->subtitle . "\n";
					}
					if ($book->series_name) {
						$bookContext .= "  Series: " . $book->series_name . " #" . $book->series_number . "\n";
					}
					if ($book->hook) {
						$bookContext .= "  Hook: " . $book->hook . "\n";
					}
					if ($book->about) {
						$bookContext .= "  About: " . $book->about . "\n\n";
					}
				}
			}

			// Updated prompts for better context
			$system_prompt = "You are an assistant helping an author write their website bio. Your task is to generate a compelling, fictional author bio of about 2-3 short paragraphs. Use the author's name and the list of their books to infer a plausible genre, style, and persona. The bio should sound authentic and engaging. Focus on common author bio elements: hint at their genre, common themes in their work, a touch of personality or a fictional background, and a call to action (e.g., 'explore their books').";
			$user_message = "My name is " . $user->name . ".\n\n" . $bookContext . "\n\nHere's the current draft of my bio (it might be empty):\n---\n" . $currentBio . "\n---\n\nPlease generate a new, creative placeholder bio for me based on my name and book details.";

			$chat_messages = [['role' => 'user', 'content' => $user_message]];
			$llmModel = env('DEFAULT_LLM', 'mistralai/mixtral-8x7b-instruct');

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
		 * Delete the user's account.
		 */
		public function destroy(Request $request): RedirectResponse
		{
			$request->validate([
				'password' => ['required', 'current_password'],
			]);

			$user = $request->user();

			// Optional: Delete associated storage
			if ($user->profile_photo_path && Storage::disk('public')->exists($user->profile_photo_path)) {
				Storage::disk('public')->delete($user->profile_photo_path);
			}
			foreach ($user->books as $book) {
				if ($book->cover_image_path && Storage::disk('public')->exists($book->cover_image_path)) {
					Storage::disk('public')->delete($book->cover_image_path);
				}
			}

			Auth::logout();
			$user->delete();
			$request->session()->invalidate();
			$request->session()->regenerateToken();

			return Redirect::to('/');
		}
	}

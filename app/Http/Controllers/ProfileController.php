<?php

	namespace App\Http\Controllers;

	use App\Helper\LlmHelper;
	use App\Http\Requests\ProfileUpdateRequest;
	use App\Models\Book;
	use Illuminate\Contracts\Auth\MustVerifyEmail;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\RedirectResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Illuminate\Support\Facades\DB; // MODIFIED: Import DB facade
	use Illuminate\Support\Facades\Http;
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
			$llmModel = env('DEFAULT_LLM', 'google/gemini-2.5-flash-preview-09-2025'); // Or use a user-specific setting if available

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
			$llmModel = env('DEFAULT_LLM', 'google/gemini-2.5-flash-preview-09-2025'); // Or use a user-specific setting

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


		// --- NEW: BOOK IMPORT ---

		/**
		 * NEW: Display the book import page.
		 */
		public function showImportForm(): View
		{
			return view('profile.import');
		}

		/**
		 * NEW: Fetch book data from the BookCoverZone API.
		 */
		public function fetchBookcoverzoneBooks(Request $request): JsonResponse
		{
			$user = $request->user();
			if (!$user->bookcoverzone_user_id) {
				return response()->json(['success' => false, 'message' => 'Your account is not linked to a BookCoverZone user ID.'], 400);
			}

			$userId = $user->bookcoverzone_user_id;
			$bczSiteUrl = env('BOOKCOVERZONE_SITE_URL', 'https://bookcoverzone.com');
			$userLayersUrl = env('BOOKCOVERZONE_USER_LAYERS_URL', 'https://user-layers.bookcoverzone.com');

			try {
				// MODIFIED: The entire SQL query has been updated to use explicit table aliases
				// in the innermost subqueries to prevent "Unknown column" errors.
				$sql = "
            SELECT 
                front.id AS front_history_id,
                front.create_time AS render_date,
                front.fields AS front_fields,
                back.id AS back_history_id,
                back.fields AS back_fields,
                EXISTS(SELECT 1 FROM shoppingcarts sc JOIN orders o ON sc.order_id = o.id JOIN products p ON sc.product_id = p.id WHERE sc.user_id = ? AND o.status = 'success' AND p.type = 'bookcover' AND sc.photoshop_filename = JSON_UNQUOTE(JSON_EXTRACT(front.fields, '$.coverfile'))) as is_purchased
            FROM 
                (
                    SELECT h1.*
                    FROM bkz_front_drag_history h1
                    INNER JOIN (
                        SELECT 
                            JSON_UNQUOTE(JSON_EXTRACT(h_inner_front.fields, '$.coverfile')) as coverfile, 
                            JSON_UNQUOTE(JSON_EXTRACT(h_inner_front.fields, '$.trim_size_name')) as trim_size_name,
                            MAX(h_inner_front.id) as max_id
                        FROM bkz_front_drag_history AS h_inner_front
                        WHERE h_inner_front.userid = ? AND (h_inner_front.render_result = 'yes' OR h_inner_front.render_status = 11)
                        GROUP BY coverfile, trim_size_name
                    ) h2 ON JSON_UNQUOTE(JSON_EXTRACT(h1.fields, '$.coverfile')) = h2.coverfile 
                           AND JSON_UNQUOTE(JSON_EXTRACT(h1.fields, '$.trim_size_name')) = h2.trim_size_name
                           AND h1.id = h2.max_id
                ) AS front
            LEFT JOIN 
                (
                    SELECT h3.*
                    FROM bkz_back_drag_history h3
                    INNER JOIN (
                        SELECT 
                            JSON_UNQUOTE(JSON_EXTRACT(h_inner_back.fields, '$.coverfile')) as coverfile, 
                            JSON_UNQUOTE(JSON_EXTRACT(h_inner_back.fields, '$.trim_size_name')) as trim_size_name,
                            MAX(h_inner_back.id) as max_id
                        FROM bkz_back_drag_history AS h_inner_back
                        WHERE h_inner_back.userid = ? AND (h_inner_back.render_result = 'yes' OR h_inner_back.render_status = 11)
                        GROUP BY coverfile, trim_size_name
                    ) h4 ON JSON_UNQUOTE(JSON_EXTRACT(h3.fields, '$.coverfile')) = h4.coverfile 
                           AND JSON_UNQUOTE(JSON_EXTRACT(h3.fields, '$.trim_size_name')) = h4.trim_size_name
                           AND h3.id = h4.max_id
                ) AS back ON JSON_UNQUOTE(JSON_EXTRACT(front.fields, '$.coverfile')) = JSON_UNQUOTE(JSON_EXTRACT(back.fields, '$.coverfile'))
                          AND JSON_UNQUOTE(JSON_EXTRACT(front.fields, '$.trim_size_name')) = JSON_UNQUOTE(JSON_EXTRACT(back.fields, '$.trim_size_name'))
            ORDER BY 
                back.id DESC, front.id DESC
        ";

				$results = DB::connection('mysql_bookcoverzone')->select($sql, [$userId, $userId, $userId]);

				$books = [];
				foreach ($results as $row) {
					$frontFields = json_decode($row->front_fields, true);
					$backFields = $row->back_fields ? json_decode($row->back_fields, true) : null;

					// Extract Title
					$title = 'Untitled';
					foreach ($frontFields as $key => $value) {
						if (stripos($key, "layer_title") !== false && stripos($key, "text") !== false && !empty($value)) {
							$title = $value;
							break;
						}
					}

					// Extract Author
					$author = '';
					foreach ($frontFields as $key => $value) {
						if (stripos($key, "layer_author") !== false && stripos($key, "text") !== false && !empty($value)) {
							$author = $value;
							break;
						}
					}

					// Find Author Photo
					$authorPhotoUrl = null;
					$photoRow = DB::connection('mysql_bookcoverzone')->table('bkz_members_image_library')->where('userid', $userId)->first();
					if ($photoRow && !empty($photoRow->author_image_file) && $photoRow->author_image_file !== '/img/backcover-image-placeholder.jpg') {
						$authorPhotoUrl = rtrim($bczSiteUrl, '/') . $photoRow->author_image_file;
					}

					$books[] = [
						'front_history_id' => (int)$row->front_history_id,
						'back_history_id' => $row->back_history_id ? (int)$row->back_history_id : null,
						'cover_id' => $frontFields['coverfile'] ?? 'N/A',
						'trim_size_name' => $frontFields['trim_size_display_name'] ?? 'Ebook',
						'trim_size_value' => $frontFields['trim_size_name'] ?? 'N/A',
						'render_date' => $row->render_date,
						'is_purchased' => (bool)$row->is_purchased,
						'has_back_cover' => !is_null($backFields),
						'title' => htmlspecialchars($title),
						'author' => htmlspecialchars($author),
						'front_cover_url' => rtrim($userLayersUrl, '/') . "/{$userId}/{$frontFields['coverfile']}/{$frontFields['tempfilename']}-{$frontFields['trim_size_name']}.jpg",
						'author_bio' => $backFields['biographytext'] ?? null,
						'author_photo_url' => ($backFields && ($backFields['use_picture'] ?? 'no') === 'yes') ? $authorPhotoUrl : null,
					];
				}

				return response()->json(['success' => true, 'books' => $books]);

			} catch (\Exception $e) {
				Log::error('Exception while querying BookCoverZone DB: ' . $e->getMessage());
				return response()->json(['success' => false, 'message' => 'An error occurred while fetching your books from the database.'], 500);
			}
		}

		/**
		 * NEW: Import a single book from BookCoverZone data.
		 */
		public function importBook(Request $request): JsonResponse
		{
			$user = $request->user();
			$validated = $request->validate([
				'bookData' => 'required|array',
				'bookData.title' => 'required|string|max:255',
				'bookData.front_cover_url' => 'required|url',
				'bookData.author_bio' => 'nullable|string|max:5000',
				'bookData.author_photo_url' => 'nullable|url',
				'updateProfile' => 'required|boolean',
			]);

			$bookData = $validated['bookData'];
			$updateProfile = $validated['updateProfile'];

			try {
				// 1. Download front cover image
				$coverImagePath = null;
				if (!empty($bookData['front_cover_url'])) {
					$imageData = Http::get($bookData['front_cover_url'])->body();
					if ($imageData) {
						$filename = 'book-covers/' . Str::random(40) . '.jpg';
						Storage::disk('public')->put($filename, $imageData);
						$coverImagePath = $filename;
					}
				}

				// 2. Create the book record
				Book::create([
					'user_id' => $user->id,
					'title' => $bookData['title'],
					'cover_image_path' => $coverImagePath,
					// You can add more fields here if the API provides them
					// 'subtitle' => $bookData['subtitle'] ?? null,
				]);

				// 3. Optionally update user profile
				if ($updateProfile) {
					$profileUpdated = false;
					// Update bio if provided
					if (!empty($bookData['author_bio'])) {
						$user->bio = $bookData['author_bio'];
						$profileUpdated = true;
					}

					// Update profile photo if provided
					if (!empty($bookData['author_photo_url'])) {
						$photoData = Http::get($bookData['author_photo_url'])->body();
						if ($photoData) {
							// Delete old photo if it exists
							if ($user->profile_photo_path && Storage::disk('public')->exists($user->profile_photo_path)) {
								Storage::disk('public')->delete($user->profile_photo_path);
							}
							$photoFilename = 'profile-photos/' . Str::random(40) . '.jpg';
							Storage::disk('public')->put($photoFilename, $photoData);
							$user->profile_photo_path = $photoFilename;
							$profileUpdated = true;
						}
					}

					if ($profileUpdated) {
						$user->save();
					}
				}

				return response()->json(['success' => true, 'message' => 'Book imported successfully!']);

			} catch (\Exception $e) {
				Log::error('Error during book import for user ' . $user->id, [
					'message' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				]);
				return response()->json(['success' => false, 'message' => 'An error occurred during the import process.'], 500);
			}
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

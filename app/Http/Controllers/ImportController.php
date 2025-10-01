<?php

	namespace App\Http\Controllers;

	use App\Models\Book;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Pagination\LengthAwarePaginator;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\Http;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Storage;
	use Illuminate\Support\Str;
	use Illuminate\View\View;

	/**
	 * NEW: This controller handles the functionality for importing books
	 * from the external BookCoverZone service.
	 */
	class ImportController extends Controller
	{
		/**
		 * Display the book import page.
		 */
		public function showImportForm(): View
		{
			return view('profile.import');
		}

		/**
		 * Fetch book data from the BookCoverZone API with search and pagination.
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
			$searchTerm = $request->input('search', '');
			$perPage = 15;

			try {
				// MODIFIED: Fetch purchased cover filenames and their order dates into a map for quick lookup.
				$purchasedCoversResult = DB::connection('mysql_bookcoverzone')
					->table('shoppingcarts as sc')
					->join('orders as o', 'sc.order_id', '=', 'o.id')
					->join('products as p', 'sc.product_id', '=', 'p.id')
					->where('sc.user_id', $userId)
					->where('o.status', 'success')
					->where('p.type', 'bookcover')
					->select('sc.photoshop_filename', 'o.created')
					->distinct('sc.photoshop_filename')
					->get();

				$purchasedCoversMap = $purchasedCoversResult->pluck('created', 'photoshop_filename');

				// 2. Base query for latest front renders, with search.
				$latestFrontHistorySubquery = DB::connection('mysql_bookcoverzone')
					->table('bkz_front_drag_history')
					->select(DB::raw('MAX(id) as max_id'))
					->where('userid', $userId)
					->where(function ($query) {
						$query->where('render_result', 'yes')
							->orWhere('render_status', 11);
					})
					->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fields, '$.coverfile'))"), DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fields, '$.trim_size_name'))"));

				$frontRendersQuery = DB::connection('mysql_bookcoverzone')
					->table('bkz_front_drag_history')
					->whereIn('id', $latestFrontHistorySubquery);

				if (!empty($searchTerm)) {
					$frontRendersQuery->where('fields', 'LIKE', '%' . $searchTerm . '%');
				}

				$total = $frontRendersQuery->count();
				$currentPage = LengthAwarePaginator::resolveCurrentPage();
				$results = $frontRendersQuery->orderBy('id', 'desc')->forPage($currentPage, $perPage)->get();
				$paginatedFrontRenders = new LengthAwarePaginator($results, $total, $perPage, $currentPage, [
					'path' => LengthAwarePaginator::resolveCurrentPath(),
				]);

				if ($paginatedFrontRenders->isEmpty()) {
					return response()->json(['success' => true, 'books' => $paginatedFrontRenders]);
				}

				// 3. Get keys from the current page to fetch related data efficiently.
				$pageKeys = $paginatedFrontRenders->map(function ($render) {
					$fields = json_decode($render->fields, true);
					return [
						'coverfile' => $fields['coverfile'] ?? null,
						'trim_size' => $fields['trim_size_name'] ?? null,
					];
				})->filter();

				// 4. Get the latest back cover renders that match the keys on the current page.
				$latestBackHistoryIds = DB::connection('mysql_bookcoverzone')
					->table('bkz_back_drag_history')
					->select(DB::raw('MAX(id) as max_id'))
					->where('userid', $userId)
					->where('render_status', 11)
					->where(function ($query) use ($pageKeys) {
						foreach ($pageKeys as $key) {
							$query->orWhere(function ($q) use ($key) {
								$q->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fields, '$.frontcover'))"), $key['coverfile'])
									->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fields, '$.trim_size_name'))"), $key['trim_size']);
							});
						}
					})
					->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fields, '$.frontcover'))"), DB::raw("JSON_UNQUOTE(JSON_EXTRACT(fields, '$.trim_size_name'))"))
					->pluck('max_id');

				$latestBackRendersResults = DB::connection('mysql_bookcoverzone')
					->table('bkz_back_drag_history')
					->whereIn('id', $latestBackHistoryIds)
					->get();

				$backRendersMap = [];
				foreach ($latestBackRendersResults as $render) {
					$fields = json_decode($render->fields, true);
					$key = ($fields['frontcover'] ?? '') . ':' . ($fields['trim_size_name'] ?? '');
					$backRendersMap[$key] = $render;
				}

				// 5. Get the user's author photo URL.
				// MODIFIED: START - URL-encode the author photo path to handle spaces and special characters.
				$authorPhotoUrl = null;
				$photoRow = DB::connection('mysql_bookcoverzone')->table('bkz_members_image_library')->where('userid', $userId)->first();
				if ($photoRow && !empty($photoRow->author_image_file) && $photoRow->author_image_file !== '/img/backcover-image-placeholder.jpg') {
					// Split the path into segments, URL-encode each segment, then reassemble.
					// This correctly handles spaces in filenames without encoding the directory slashes.
					$pathParts = explode('/', ltrim($photoRow->author_image_file, '/'));
					$encodedPathParts = array_map('rawurlencode', $pathParts);
					$encodedPath = implode('/', $encodedPathParts);
					$authorPhotoUrl = rtrim($bczSiteUrl, '/') . '/' . $encodedPath;
				}
				// MODIFIED: END

				// 6. Assemble the final book data.
				$books = [];
				foreach ($paginatedFrontRenders as $frontRender) {
					$frontFields = json_decode($frontRender->fields, true);
					$coverfile = $frontFields['coverfile'] ?? null;
					$trimSize = $frontFields['trim_size_name'] ?? null;

					if (!$coverfile || !$trimSize) {
						continue;
					}

					$lookupKey = $coverfile . ':' . $trimSize;
					$backRender = $backRendersMap[$lookupKey] ?? null;
					$backFields = $backRender ? json_decode($backRender->fields, true) : null;

					// MODIFIED: Logic to handle multi-line titles, authors, subtitles, and hooks.
					$titleParts = [];
					$authorParts = [];
					$subtitleParts = []; // NEW: Array for subtitle parts.
					$hookParts = []; // NEW: Array for hook/tagline parts.

					// NEW: Sort keys to ensure parts are assembled in the correct order (e.g., title_1, title_2).
					uksort($frontFields, 'strnatcasecmp');

					foreach ($frontFields as $key => $value) {
						if (!empty($value)) {
							if (stripos($key, "layer_title") !== false && stripos($key, "text") !== false) {
								$titleParts[] = $value;
							}
							if (stripos($key, "layer_author") !== false && stripos($key, "text") !== false) {
								$authorParts[] = $value;
							}
							if (stripos($key, "layer_subtitle") !== false && stripos($key, "text") !== false) {
								$subtitleParts[] = $value;
							}
							if (stripos($key, "layer_hook") !== false && stripos($key, "text") !== false) {
								$hookParts[] = $value;
							}
						}
					}

					$title = trim(implode(' ', $titleParts));
					$author = trim(implode(' ', $authorParts));
					$subtitle = trim(implode(' ', $subtitleParts)); // NEW: Assemble subtitle.
					$hook = trim(implode(' ', $hookParts)); // NEW: Assemble hook.

					if (empty($title)) {
						$title = 'Untitled';
					}
					// END MODIFICATION

					// MODIFIED: START - URL-encode components for the front cover URL to ensure validity.
					$encodedUserId = rawurlencode($userId);
					$encodedCoverfile = rawurlencode($coverfile);
					$encodedTempFilename = rawurlencode($frontFields['tempfilename']);
					$encodedTrimSize = rawurlencode($trimSize);
					$frontCoverUrl = rtrim($userLayersUrl, '/') . "/{$encodedUserId}/{$encodedCoverfile}/{$encodedTempFilename}-{$encodedTrimSize}.jpg";
					// MODIFIED: END

					$books[] = [
						'front_history_id' => (int)$frontRender->id,
						'back_history_id' => $backRender ? (int)$backRender->id : null,
						'cover_id' => $coverfile,
						'trim_size_name' => $frontFields['trim_size_display_name'] ?? 'Ebook',
						'trim_size_value' => $trimSize,
						'render_date' => $frontRender->create_time,
						'is_purchased' => $purchasedCoversMap->has($coverfile),
						'purchase_date' => $purchasedCoversMap->get($coverfile),
						'has_back_cover' => !is_null($backRender),
						'title' => htmlspecialchars($title),
						'subtitle' => htmlspecialchars($subtitle), // NEW: Add subtitle to the response.
						'hook' => htmlspecialchars($hook), // NEW: Add hook to the response.
						'author' => htmlspecialchars($author),
						'front_cover_url' => $frontCoverUrl, // MODIFIED: Use the new encoded URL variable.
						'about_the_book' => isset($backFields['backcovertext']) ? strip_tags($backFields['backcovertext']) : null,
						'author_bio' => isset($backFields['biographytext']) ? strip_tags($backFields['biographytext']) : null,
						'author_photo_url' => ($backFields && ($backFields['use_picture'] ?? 'no') === 'yes') ? $authorPhotoUrl : null,
					];
				}

				// 7. Sort results to prioritize books with back covers.
				usort($books, function ($a, $b) {
					if ($a['has_back_cover'] && !$b['has_back_cover']) {
						return -1;
					}
					if (!$a['has_back_cover'] && $b['has_back_cover']) {
						return 1;
					}
					return strtotime($b['render_date']) - strtotime($a['render_date']);
				});

				// 8. Manually create a new paginator instance.
				$paginatedBooks = new LengthAwarePaginator($books, $total, $perPage, $currentPage, [
					'path' => LengthAwarePaginator::resolveCurrentPath(),
				]);

				return response()->json(['success' => true, 'books' => $paginatedBooks]);
			} catch (\Exception $e) {
				Log::error('Exception while querying BookCoverZone DB: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
				return response()->json(['success' => false, 'message' => 'An error occurred while fetching your books from the database.'], 500);
			}
		}

		/**
		 * Import a single book from BookCoverZone data.
		 */
		public function importBook(Request $request): JsonResponse
		{
			$user = $request->user();
			// MODIFIED: Added author to validation rules.
			$validated = $request->validate([
				'bookData' => 'required|array',
				'bookData.title' => 'required|string|max:255',
				'bookData.subtitle' => 'nullable|string|max:255',
				'bookData.hook' => 'nullable|string|max:1000',
				'bookData.author' => 'nullable|string|max:255', // NEW: Validate author name.
				'bookData.about_the_book' => 'nullable|string|max:5000',
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
				// MODIFIED: Decode HTML entities before saving to the database to prevent storing encoded characters.
				Book::create([
					'user_id' => $user->id,
					'title' => htmlspecialchars_decode($bookData['title']),
					'subtitle' => isset($bookData['subtitle']) ? htmlspecialchars_decode($bookData['subtitle']) : null,
					'hook' => isset($bookData['hook']) ? htmlspecialchars_decode($bookData['hook']) : null,
					'about' => isset($bookData['about_the_book']) ? htmlspecialchars_decode($bookData['about_the_book']) : null,
					'cover_image_path' => $coverImagePath,
				]);

				// 3. Optionally update user profile
				if ($updateProfile) {
					$profileUpdated = false;

					// MODIFIED: Decode author name before updating the user profile.
					if (!empty($bookData['author'])) {
						$user->name = htmlspecialchars_decode($bookData['author']);
						$profileUpdated = true;
					}

					if (!empty($bookData['author_bio'])) {
						$user->bio = strip_tags($bookData['author_bio']);
						$profileUpdated = true;
					}

					if (!empty($bookData['author_photo_url'])) {
						$photoData = Http::get($bookData['author_photo_url'])->body();
						if ($photoData) {
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
	}

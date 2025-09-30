<?php

	namespace App\Http\Controllers;

	use App\Models\Website;
	use App\Models\WebsiteFile;
	use App\Models\Book;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Illuminate\Support\Facades\DB; // MODIFIED: Import DB facade
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Redirect;
	use Illuminate\Validation\Rule;
	use Illuminate\View\View;
	use Illuminate\Support\Str; // NEW: Import the Str helper

	class WebsiteController extends Controller
	{
		/**
		 * Display a listing of the user's websites (Dashboard).
		 * MODIFIED: This method now returns a Blade View instead of an Inertia response.
		 */
		public function index(): View
		{
			$user = Auth::user()->load('websites', 'books');
			$websites = $user->websites()->orderBy('created_at', 'desc')->get();

			// Check profile completeness
			$profileComplete = !empty($user->name) && !empty($user->bio); // && !empty($user->profile_photo_path);
			$hasBooks = $user->books->count() > 0;
			$prerequisitesMet = $profileComplete && $hasBooks;

			// NEW: Generate a suggested, unique slug for the "create website" form.
			$suggestedSlug = Str::slug($user->name . '-new-site');
			$counter = 1;
			$originalSlug = $suggestedSlug;
			while (Website::where('slug', $suggestedSlug)->exists()) {
				$suggestedSlug = $originalSlug . '-' . $counter;
				$counter++;
			}

			// MODIFIED: Render the 'dashboard' Blade view and pass the necessary data.
			return view('dashboard', [
				'websites' => $websites,
				'hasWebsites' => $websites->isNotEmpty(),
				'userBooks' => $user->books,
				'prerequisitesMet' => $prerequisitesMet,
				'profileComplete' => $profileComplete,
				'hasBooks' => $hasBooks,
				'suggestedSlug' => $suggestedSlug, // NEW: Pass suggested slug to the view
				'auth' => [
					'user' => $user
				]
			]);
		}

		/**
		 * Store a newly created website in storage.
		 * NOTE: No changes were needed here as the logic is backend-focused and
		 * the redirect is compatible with Blade.
		 */
		public function store(Request $request)
		{
			$user = Auth::user()->load('books');

			// --- Prerequisite Check (Server-side) ---
			$profileComplete = !empty($user->name) && !empty($user->bio); // && !empty($user->profile_photo_path);
			$hasBooks = $user->books->count() > 0;
			if (!$profileComplete || !$hasBooks) {
				return Redirect::route('dashboard')->with('error', 'Please complete your profile (name, bio, photo) and add at least one book before creating a website.');
			}

			// --- Validation ---
			// MODIFIED: Added validation rules for the new 'slug' field.
			$validated = $request->validate([
				'name' => 'required|string|max:255',
				'slug' => 'required|string|max:255|alpha_dash|unique:websites,slug',
				'primary_book_id' => [
					'required',
					'integer',
					Rule::exists('books', 'id')->where(function ($query) use ($user) {
						$query->where('user_id', $user->id);
					}),
				],
				'featured_book_ids' => 'nullable|array',
				'featured_book_ids.*' => [
					'integer',
					Rule::exists('books', 'id')->where(function ($query) use ($user) {
						$query->where('user_id', $user->id);
					}),
					Rule::notIn([$request->input('primary_book_id')]),
				],
			]);

			// --- Data Preparation ---
			$primaryBook = Book::find($validated['primary_book_id']);
			$featuredBooks = collect();
			if (!empty($validated['featured_book_ids'])) {
				$featuredBooks = Book::whereIn('id', $validated['featured_book_ids'])->get();
			}

			// --- Create Website Record ---
			// MODIFIED: Added the slug to the create() method data.
			$website = $user->websites()->create([
				'name' => $validated['name'],
				'slug' => $validated['slug'],
				'primary_book_id' => $validated['primary_book_id'],
				'featured_book_ids' => $validated['featured_book_ids'] ?? [],
				'llm_model' => env('DEFAULT_LLM', 'google/gemini-2.5-flash-preview-09-2025'),
			]);

			// --- Construct User Prompt for Initial Generation ---
			$initialUserPrompt = "Generate the content for my author website with the following information.\n\n";
			$initialUserPrompt .= "Name: " . $user->name . "\n";
			$initialUserPrompt .= "Bio:\n" . $user->bio . "\n\n";
			$initialUserPrompt .= "Primary Book to Feature:\n";
			$initialUserPrompt .= "Title: " . $primaryBook->title . "\n";
			if ($primaryBook->subtitle) $initialUserPrompt .= "Subtitle: " . $primaryBook->subtitle . "\n";
			if ($primaryBook->hook) $initialUserPrompt .= "Hook/Tagline: " . $primaryBook->hook . "\n";
			if ($primaryBook->about) $initialUserPrompt .= "About: " . $primaryBook->about . "\n";
			if ($primaryBook->cover_image_url) $initialUserPrompt .= "Cover Image URL: " . $primaryBook->cover_image_url . "\n";
			if ($primaryBook->amazon_link) $initialUserPrompt .= "Amazon Link: " . $primaryBook->amazon_link . "\n";
			if ($primaryBook->other_link) $initialUserPrompt .= "Other Link: " . $primaryBook->other_link . "\n";
			$initialUserPrompt .= "\n";

			if ($featuredBooks->isNotEmpty()) {
				$initialUserPrompt .= "Other Books I've written and want to show on the site:\n";
				foreach ($featuredBooks as $book) {
					$initialUserPrompt .= "Title: " . $book->title . "\n";
					if ($book->subtitle) $initialUserPrompt .= "Subtitle: " . $book->subtitle . "\n";
					if ($book->hook) $initialUserPrompt .= "Hook/Tagline: " . $book->hook . "\n";
					if ($book->about) $initialUserPrompt .= "About: " . $book->about . "\n";
					if ($book->cover_image_url) $initialUserPrompt .= "Cover Image URL: " . $book->cover_image_url . "\n";
					if ($book->amazon_link) $initialUserPrompt .= "Amazon Link: " . $book->amazon_link . "\n";
					if ($book->other_link) $initialUserPrompt .= "Other Link: " . $book->other_link . "\n";
					$initialUserPrompt .= "\n";
					$initialUserPrompt .= "\n";
				}
				$initialUserPrompt .= "\n";
			}

			// --- Create Static Header/Footer and Minimal Index Files ---
			try {
				// MODIFIED: Added integrity hashes to the CDN links for security.
				$headerContent = <<<PHP
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$website->name} - {$user->name}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="css/style.css"/>
</head>
<body>
<div class="container">
PHP;

				// MODIFIED: Added integrity hash to the Bootstrap JS CDN link.
				$footerContent = <<<PHP
</div> <!-- /container -->

<footer class="text-center mt-5 text-muted">
    <p>© <?php echo date('Y'); ?> {$user->name}. Website by Book Cover Zone.</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="js/script.js"></script>
</body>
</html>
PHP;

				// Minimal content for index.php
				$minimalIndexContent = <<<PHP
<?php require_once 'includes/header.php'; ?>

<main class="py-5">
    <div class="text-center">
        <h1 class="display-5 fw-bold">{$website->name}</h1>
        <p class="fs-5 text-muted">Generating initial content...</p>
        <!-- Content will be generated by the AI assistant shortly -->
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
PHP;

				// Create header file record
				WebsiteFile::create([
					'website_id' => $website->id,
					'filename' => 'header.php',
					'folder' => '/includes',
					'filetype' => 'php',
					'version' => 1,
					'content' => $headerContent,
					'is_deleted' => false,
				]);

				// Create footer file record
				WebsiteFile::create([
					'website_id' => $website->id,
					'filename' => 'footer.php',
					'folder' => '/includes',
					'filetype' => 'php',
					'version' => 1,
					'content' => $footerContent,
					'is_deleted' => false,
				]);

				// Create minimal index file record
				WebsiteFile::create([
					'website_id' => $website->id,
					'filename' => 'index.php',
					'folder' => '/',
					'filetype' => 'php',
					'version' => 1,
					'content' => $minimalIndexContent,
					'is_deleted' => false,
				]);

				// Create minimal script.js file record
				WebsiteFile::create([
					'website_id' => $website->id,
					'filename' => 'script.js',
					'folder' => '/js',
					'filetype' => 'js',
					'version' => 1,
					'content' => '',
					'is_deleted' => false,
				]);

				// Create minimal style.css file record
				WebsiteFile::create([
					'website_id' => $website->id,
					'filename' => 'style.css',
					'folder' => '/css',
					'filetype' => 'css',
					'version' => 1,
					'content' => ":root {
  --primary-color: #2c3e50;
  --secondary-color: #c0392b;
  --text-color: #333333;
  --light-color: #f8f9fa;
  --font-primary: 'Playfair Display', Georgia, serif;
  --font-secondary: 'Source Sans Pro', Helvetica, sans-serif;
}

body {
  font-family: var(--font-secondary);
  color: var(--text-color);
  line-height: 1.6;
}
h1, h2, h3, h4, h5, h6 {
  font-family: var(--font-primary);
  font-weight: 700;
  margin-bottom: 1rem;
}

a {
  color: var(--secondary-color);
  transition: color 0.3s ease;
}

a:hover {
  color: darken(var(--secondary-color), 15%);
  text-decoration: none;
}

blockquote {
  font-family: var(--font-primary);
  font-style: italic;
  border-left: 4px solid var(--secondary-color);
  padding-left: 1.5rem;
  margin: 1.5rem 0;
  color: #555;
}
.section-padding {
  padding: 5rem 0;
}

.bg-light-custom {
  background-color: var(--light-color);
}

.header-overlay {
  position: relative;
  background-color: rgba(0, 0, 0, 0.6);
  color: white;
  padding: 3rem 0;
}
.navbar {
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.navbar-brand {
  font-family: var(--font-primary);
  font-weight: 700;
  font-size: 1.8rem;
}

.nav-link {
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 1px;
  font-size: 0.9rem;
  margin: 0 0.5rem;
}

.nav-link.active {
  color: var(--secondary-color) !important;
}

.btn-primary {
  background-color: var(--secondary-color);
  border-color: var(--secondary-color);
  padding: 0.5rem 1.5rem;
  font-weight: 600;
}

.btn-primary:hover {
  background-color: darken(var(--secondary-color), 10%);
  border-color: darken(var(--secondary-color), 10%);
}

.btn-outline-primary {
  color: var(--secondary-color);
  border-color: var(--secondary-color);
}

.btn-outline-primary:hover {
  background-color: var(--secondary-color);
  border-color: var(--secondary-color);
}

.hero {
  background-size: cover;
  background-position: center;
  min-height: 500px;
  display: flex;
  align-items: center;
  position: relative;
}

.hero-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
}

.hero-content {
  position: relative;
  z-index: 2;
  color: white;
}

.hero-title {
  font-size: 3.5rem;
  font-weight: 700;
  margin-bottom: 1rem;
}

.hero-subtitle {
  font-size: 1.5rem;
  margin-bottom: 2rem;
  font-weight: 300;
}

.book-cover {
  box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
  transition: transform 0.3s ease;
  max-width: 100%;
  height: auto;
}

.book-cover:hover {
  transform: scale(1.03);
}

.book-title {
  font-size: 1.5rem;
  margin-top: 1.5rem;
}

.book-description {
  color: #666;
}

.author-img {
  border-radius: 50%;
  max-width: 200px;
  margin-bottom: 2rem;
  border: 5px solid white;
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

.testimonial-card {
  border-radius: 10px;
  padding: 2rem;
  margin-bottom: 1.5rem;
  background-color: white;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
}

.testimonial-author {
  font-weight: 600;
  font-style: italic;
  margin-top: 1rem;
}
",
					'is_deleted' => false,
				]);

				Log::info("Created initial files (header, footer, minimal index) for Website ID: {$website->id}");

			} catch (\Exception $e) {
				Log::error("Failed to create initial files for Website ID {$website->id}: " . $e->getMessage());
				return Redirect::route('dashboard')->with('error', 'Failed to create initial website files. Please try again.');
			}

			Log::info("Initial User Prompt Content:", ['prompt' => $initialUserPrompt]);
			Log::info("Flashing initial_prompt to session for Website ID: {$website->id}");

			// --- Redirect with Initial Prompt ---
			// MODIFIED: The route helper now uses the website object, which correctly resolves to the new slug.
			return redirect()->route('websites.show', $website)
				->with('success', 'Website created! Generating initial content via chat...')
				->with('initial_prompt', $initialUserPrompt);
		}

		/**
		 * Display the specified website (Chat/Preview/Code view).
		 * MODIFIED: This method now returns a Blade View.
		 */
		public function show(Website $website): View
		{
			$this->authorize('view', $website);
			$website->load('chatMessages');

			Log::info("Showing website with ID: {$website->id} for user ID: " . Auth::id());
			Log::info('Initial Prompt:', ['prompt' => session('initial_prompt')]);

			// MODIFIED: Render the 'websites.show' Blade view.
			return view('websites.show', [
				'website' => $website,
				'chatMessages' => $website->chatMessages,
			]);
		}

		/**
		 * NEW: Check if a given slug is available.
		 * This is used for real-time validation on the frontend.
		 */
		public function checkSlug(Request $request)
		{
			$validated = $request->validate([
				'slug' => 'required|string|alpha_dash|max:255',
				'ignore_id' => 'nullable|integer|exists:websites,id'
			]);

			$query = Website::where('slug', $validated['slug']);

			if (isset($validated['ignore_id'])) {
				$query->where('id', '!=', $validated['ignore_id']);
			}

			$isAvailable = !$query->exists();

			return response()->json(['available' => $isAvailable]);
		}

		/**
		 * NEW: Update the slug for a given website.
		 */
		public function updateSlug(Request $request, Website $website)
		{
			$this->authorize('update', $website);

			$validated = $request->validate([
				'slug' => [
					'required',
					'string',
					'alpha_dash',
					'max:255',
					Rule::unique('websites')->ignore($website->id),
				]
			]);

			$website->slug = $validated['slug'];
			$website->save();

			return Redirect::route('dashboard')->with('status', 'website-slug-updated');
		}

		/**
		 * NEW: Restore the website's file system to a previous state.
		 */
		public function restore(Request $request, Website $website)
		{
			$this->authorize('update', $website);

			$validated = $request->validate([
				'steps' => 'required|integer|min:1',
			]);

			$stepsToRevert = $validated['steps'];

			DB::beginTransaction();
			try {
				// Find the IDs of the last 'n' file operations to revert
				$fileIdsToDelete = WebsiteFile::where('website_id', $website->id)
					->orderByDesc('id')
					->limit($stepsToRevert)
					->pluck('id');

				if ($fileIdsToDelete->isEmpty()) {
					DB::rollBack();
					return response()->json(['message' => 'No file history to restore.'], 404);
				}

				// Delete the identified records
				WebsiteFile::whereIn('id', $fileIdsToDelete)->delete();

				DB::commit();

				Log::info("User ID " . Auth::id() . " restored Website ID {$website->id} by {$stepsToRevert} steps.");

				return response()->json(['message' => "Successfully restored the last {$stepsToRevert} file operations."]);

			} catch (\Exception $e) {
				DB::rollBack();
				Log::error("Error restoring website history for Website ID {$website->id}: " . $e->getMessage());
				return response()->json(['error' => 'An unexpected error occurred while trying to restore the files.'], 500);
			}
		}
	}

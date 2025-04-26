<?php

	namespace App\Http\Controllers;

	use App\Models\Website;
	use App\Models\WebsiteFile;
	use App\Models\Book;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Illuminate\Support\Facades\File;
	use Illuminate\Support\Facades\Log;
	use Illuminate\Support\Facades\Redirect;
	use Illuminate\Validation\Rule;
	use Inertia\Inertia;
	use Inertia\Response;

	class WebsiteController extends Controller
	{
		/**
		 * Display a listing of the user's websites (Dashboard).
		 */
		public function index(): Response
		{
			$user = Auth::user()->load('websites', 'books'); // Eager load books
			$websites = $user->websites()->orderBy('created_at', 'desc')->get();

			// Check profile completeness
			$profileComplete = !empty($user->name) && !empty($user->bio) && !empty($user->profile_photo_path);
			$hasBooks = $user->books->count() > 0;
			$prerequisitesMet = $profileComplete && $hasBooks;

			return Inertia::render('Dashboard', [
				'websites' => $websites,
				'hasWebsites' => $websites->isNotEmpty(),
				'userBooks' => $user->books, // Pass user's books to the dashboard
				'prerequisitesMet' => $prerequisitesMet, // Pass the check result
				'profileComplete' => $profileComplete, // Pass individual checks for specific messages
				'hasBooks' => $hasBooks, // Pass individual checks for specific messages
				'auth' => [ // Pass necessary user info for display/checks in frontend
					'user' => [
						'id' => $user->id,
						'name' => $user->name,
						'email' => $user->email,
						'bio' => $user->bio, // Needed for check
						'profile_photo_url' => $user->profile_photo_url, // Needed for check
					]
				]
			]);
		}

		/**
		 * Store a newly created website in storage.
		 */
		public function store(Request $request)
		{
			$user = Auth::user()->load('books'); // Load books for checks and data

			// --- Prerequisite Check (Server-side) ---
			$profileComplete = !empty($user->name) && !empty($user->bio) && !empty($user->profile_photo_path);
			$hasBooks = $user->books->count() > 0;
			if (!$profileComplete || !$hasBooks) {
				return Redirect::route('dashboard')->with('error', 'Please complete your profile (name, bio, photo) and add at least one book before creating a website.');
			}

			// --- Validation ---
			$validated = $request->validate([
				'name' => 'required|string|max:255',
				'primary_book_id' => [
					'required',
					'integer',
					Rule::exists('books', 'id')->where(function ($query) use ($user) {
						$query->where('user_id', $user->id);
					}),
				],
				'featured_book_ids' => 'nullable|array',
				'featured_book_ids.*' => [ // Validate each ID in the array
					'integer',
					Rule::exists('books', 'id')->where(function ($query) use ($user) {
						$query->where('user_id', $user->id);
					}),
					// Ensure featured books are not the same as the primary book
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
			$website = $user->websites()->create([
				'name' => $validated['name'],
				'primary_book_id' => $validated['primary_book_id'],
				'featured_book_ids' => $validated['featured_book_ids'] ?? [], // Store as array
				'llm_model' => env('DEFAULT_LLM', 'mistralai/mixtral-8x7b-instruct'), // Or user preference
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
			if ($primaryBook->cover_image_url) $initialUserPrompt .= "Cover Image URL: " . $primaryBook->cover_image_url . "\n"; // Provide URL if available
			if ($primaryBook->amazon_link) $initialUserPrompt .= "Amazon Link: " . $primaryBook->amazon_link . "\n";
			if ($primaryBook->other_link) $initialUserPrompt .= "Other Link: " . $primaryBook->other_link . "\n";
			$initialUserPrompt .= "\n";

			if ($featuredBooks->isNotEmpty()) {
				$initialUserPrompt .= "Other Books I've written and want to show on the site:\n";
				foreach ($featuredBooks as $book) {
					$initialUserPrompt .= "- Title: " . $book->title . ($book->subtitle ? " (" . $book->subtitle . ")" : "") . "\n";
					// Optionally add links or cover URLs for featured books too
					if ($book->cover_image_url) $initialUserPrompt .= "  Cover Image URL: " . $book->cover_image_url . "\n";
					if ($book->amazon_link) $initialUserPrompt .= "  Amazon Link: " . $book->amazon_link . "\n";
				}
				$initialUserPrompt .= "\n";
			}

			// --- Create Static Header/Footer and Minimal Index Files ---
			try {
				// Content for header.php (Keep relatively static)
				$headerContent = <<<PHP
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$website->name} - {$user->name}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"  crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="css/style.css"/>
</head>
<body>
<div class="container">
PHP;

				// Content for footer.php (Keep relatively static)
				$footerContent = <<<PHP
</div> <!-- /container -->

<footer class="text-center mt-5 text-muted">
    <p>© <?php echo date('Y'); ?> {$user->name}. Website by AuthorWebsiteBuilder.</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
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
					'content' => $minimalIndexContent, // Use minimal content
					'is_deleted' => false,
				]);

				// Create minimal index file record
				WebsiteFile::create([
					'website_id' => $website->id,
					'filename' => 'script.js',
					'folder' => '/js',
					'filetype' => 'js',
					'version' => 1,
					'content' => '',
					'is_deleted' => false,
				]);

				// Create minimal index file record
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
				// Optionally: Delete the website record or add an error state?
				// $website->delete(); // Or mark as failed
				return Redirect::route('dashboard')->with('error', 'Failed to create initial website files. Please try again.');
			}

			Log::info("Initial User Prompt Content:", ['prompt' => $initialUserPrompt]); // Log the content
			Log::info("Flashing initial_prompt to session for Website ID: {$website->id}");

			// --- Redirect with Initial Prompt ---
			return redirect()->route('websites.show', $website)
				->with('success', 'Website created! Generating initial content via chat...')
				->with('initial_prompt', $initialUserPrompt);
		}

		/**
		 * Display the specified website (Chat/Preview/Code view).
		 */
		public function show(Website $website): Response // Type hint Inertia Response
		{
			// Authorize: Ensure the user owns this website
			$this->authorize('view', $website); // Requires a Policy

			// Eager load messages to avoid N+1 queries
			$website->load('chatMessages');

			Log::info("Showing website with ID: {$website->id} for user ID: " . Auth::id());
			Log::info('Initial Prompt:', ['prompt' => session('initial_prompt')]); // Log the initial prompt

			// Note: The 'initial_prompt' will be available in the props automatically
			// if it was flashed using ->with() on the redirect.
			return Inertia::render('Website/Show', [
				'website' => $website,
				'chatMessages' => $website->chatMessages,
			]);
		}

	}

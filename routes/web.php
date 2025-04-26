<?php

	use App\Http\Controllers\ChatMessageController;
	use App\Http\Controllers\PageController;
	use App\Http\Controllers\ProfileController;
	use App\Http\Controllers\WebsiteController;
	use App\Http\Controllers\WebsiteFileController;
	use App\Http\Controllers\WebsitePreviewController;
	use Illuminate\Foundation\Application;
	use Illuminate\Support\Facades\Route;
	use Inertia\Inertia;

	/*
	|--------------------------------------------------------------------------
	| Web Routes
	|--------------------------------------------------------------------------
	|
	| Here is where you can register web routes for your application. These
	| routes are loaded by the RouteServiceProvider within a group which
	| contains the "web" middleware group. Now create something great!
	|
	*/

	Route::get('/', [PageController::class, 'home'])->name('home');

	Route::get('/website/{website}/{path?}', [WebsitePreviewController::class, 'serve']) // Use 'serve' method
	->where('path', '.*')
		->name('website.preview.serve');

//	Route::get('/', function () {
//		return Inertia::render('Welcome', [
//			'canLogin' => Route::has('login'),
//			'canRegister' => Route::has('register'),
//			'laravelVersion' => Application::VERSION,
//			'phpVersion' => PHP_VERSION,
//		]);
//	});
//
//	Route::get('/dashboard', function () {
//		return Inertia::render('Dashboard');
//	})->middleware(['auth', 'verified'])->name('dashboard');

	Route::middleware('auth')->group(function () {
		Route::get('/dashboard', [WebsiteController::class, 'index'])->name('dashboard');

		Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
		Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
		Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

		Route::post('/profile/photo', [ProfileController::class, 'updateProfilePhoto'])->name('profile.photo.update');
		Route::delete('/profile/photo', [ProfileController::class, 'deleteProfilePhoto'])->name('profile.photo.delete'); // Optional: To remove photo
		Route::patch('/profile/bio', [ProfileController::class, 'updateBio'])->name('profile.bio.update');
		Route::post('/profile/bio/generate', [ProfileController::class, 'generateBioPlaceholder'])->name('profile.bio.generate');

		Route::post('/profile/books/{book}', [ProfileController::class, 'updateBook'])->name('profile.books.update'); // Use PUT for update
		Route::post('/profile/books', [ProfileController::class, 'storeBook'])->name('profile.books.store');
		Route::delete('/profile/books/{book}', [ProfileController::class, 'destroyBook'])->name('profile.books.destroy');
		Route::post('/profile/books/generate/hook', [ProfileController::class, 'generateBookHookPlaceholder'])->name('profile.books.generate.hook');
		Route::post('/profile/books/generate/about', [ProfileController::class, 'generateBookAboutPlaceholder'])->name('profile.books.generate.about');

		Route::post('/websites', [WebsiteController::class, 'store'])->name('websites.store');
		Route::get('/websites/{website}', [WebsiteController::class, 'show'])->name('websites.show');
		// Route::put('/websites/{website}', [WebsiteController::class, 'update'])->name('websites.update');
		// Route::delete('/websites/{website}', [WebsiteController::class, 'destroy'])->name('websites.destroy');

		Route::post('/websites/{website}/chat', [ChatMessageController::class, 'store'])->name('websites.chat.store');

		Route::prefix('/api/websites/{website}/files')
			->name('api.websites.files.')
			->controller(WebsiteFileController::class) // Group controller
			->group(function () {
				Route::get('/', 'index')->name('index');
				Route::put('/', 'update')->name('update'); // <-- ADD THIS ROUTE (Using PUT for simplicity)
				// Add other file-related API routes here if needed (e.g., show specific version, delete)
			});

	});

	require __DIR__ . '/auth.php';

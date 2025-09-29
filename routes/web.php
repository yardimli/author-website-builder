<?php

	use App\Http\Controllers\ChatMessageController;
	use App\Http\Controllers\PageController;
	use App\Http\Controllers\ProfileController;
	use App\Http\Controllers\WebsiteController;
	use App\Http\Controllers\WebsiteFileController;
	use App\Http\Controllers\WebsitePreviewController;
	use Illuminate\Support\Facades\Route;

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

// The home route remains the same
	Route::get('/', [PageController::class, 'home'])->name('home');
	Route::get('/home', [PageController::class, 'home'])->name('home');

// The website preview route remains the same
	Route::get('/website/{website}/{path?}', [WebsitePreviewController::class, 'serve'])
		->where('path', '.*')
		->name('website.preview.serve');


	Route::middleware('auth')->group(function () {
		// Route to the dashboard
		Route::get('/dashboard', [WebsiteController::class, 'index'])->name('dashboard');

		// Profile routes are now standard GET/PATCH/DELETE
		Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
		Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
		Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

		// Specific profile actions
		Route::post('/profile/photo', [ProfileController::class, 'updateProfilePhoto'])->name('profile.photo.update');
		Route::delete('/profile/photo', [ProfileController::class, 'deleteProfilePhoto'])->name('profile.photo.delete');
		Route::patch('/profile/bio', [ProfileController::class, 'updateBio'])->name('profile.bio.update');
		Route::post('/profile/bio/generate', [ProfileController::class, 'generateBioPlaceholder'])->name('profile.bio.generate');

		// Book management routes
		// Note: Using POST for update to simplify Blade forms without needing a @method directive
		Route::post('/profile/books/{book}', [ProfileController::class, 'updateBook'])->name('profile.books.update');
		Route::post('/profile/books', [ProfileController::class, 'storeBook'])->name('profile.books.store');
		Route::delete('/profile/books/{book}', [ProfileController::class, 'destroyBook'])->name('profile.books.destroy');
		Route::post('/profile/books/generate/hook', [ProfileController::class, 'generateBookHookPlaceholder'])->name('profile.books.generate.hook');
		Route::post('/profile/books/generate/about', [ProfileController::class, 'generateBookAboutPlaceholder'])->name('profile.books.generate.about');

		// Website management routes
		Route::post('/websites', [WebsiteController::class, 'store'])->name('websites.store');
		Route::get('/websites/{website}', [WebsiteController::class, 'show'])->name('websites.show');

		// Chat route
		Route::post('/websites/{website}/chat', [ChatMessageController::class, 'store'])->name('websites.chat.store');

		// API routes for file management (can be called via fetch from Blade)
		Route::prefix('/api/websites/{website}/files')
			->name('api.websites.files.')
			->controller(WebsiteFileController::class)
			->group(function () {
				Route::get('/', 'index')->name('index');
				Route::put('/', 'update')->name('update');
			});
	});


	Auth::routes();

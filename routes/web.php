<?php

	use App\Http\Controllers\AuthController;
	use App\Http\Controllers\BookController; // NEW: Import BookController
	use App\Http\Controllers\ChatMessageController;
	use App\Http\Controllers\ImportController; // NEW: Import ImportController
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

	Route::get('/auto-login', [AuthController::class, 'handleAutoLogin'])->name('auto.login');

	Route::get('/', [PageController::class, 'home'])->name('home');
	Route::get('/home', [PageController::class, 'home'])->name('home');

	Route::get('/website/{website:slug}/{path?}', [WebsitePreviewController::class, 'serve'])
		->where('path', '.*')
		->name('website.preview.serve');

	Route::middleware('auth')->group(function () {
		Route::get('/dashboard', [WebsiteController::class, 'index'])->name('dashboard');

		// MODIFIED: Profile routes now point to the refactored ProfileController
		Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
		Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
		Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
		Route::get('/profile/security', [ProfileController::class, 'editSecurity'])->name('profile.security.edit');
		Route::get('/profile/account', [ProfileController::class, 'editAccount'])->name('profile.account.edit');
		Route::post('/profile/photo', [ProfileController::class, 'updateProfilePhoto'])->name('profile.photo.update');
		Route::delete('/profile/photo', [ProfileController::class, 'deleteProfilePhoto'])->name('profile.photo.delete');
		Route::patch('/profile/bio', [ProfileController::class, 'updateBio'])->name('profile.bio.update');
		Route::post('/profile/bio/generate', [ProfileController::class, 'generateBioPlaceholder'])->name('profile.bio.generate');

		// NEW: Book management routes now point to the new BookController
		Route::get('/profile/books', [BookController::class, 'index'])->name('profile.books.edit');
		Route::post('/profile/books', [BookController::class, 'store'])->name('profile.books.store');
		Route::post('/profile/books/{book}', [BookController::class, 'update'])->name('profile.books.update');
		Route::delete('/profile/books/{book}', [BookController::class, 'destroy'])->name('profile.books.destroy');
		Route::post('/profile/books/generate/hook', [BookController::class, 'generateBookHookPlaceholder'])->name('profile.books.generate.hook');
		Route::post('/profile/books/generate/about', [BookController::class, 'generateBookAboutPlaceholder'])->name('profile.books.generate.about');

		// NEW: Book import routes now point to the new ImportController
		Route::get('/profile/import', [ImportController::class, 'showImportForm'])->name('profile.import');
		Route::post('/profile/import/fetch', [ImportController::class, 'fetchBookcoverzoneBooks'])->name('profile.import.fetch');
		Route::post('/profile/import/store', [ImportController::class, 'importBook'])->name('profile.import.store');

		// Website management routes
		Route::get('/websites/create', [WebsiteController::class, 'create'])->name('websites.create'); // NEW: Route to show the create form.
		Route::post('/websites', [WebsiteController::class, 'store'])->name('websites.store');
		Route::get('/websites/{website:slug}', [WebsiteController::class, 'show'])->name('websites.show');
		Route::patch('/websites/{website:slug}/slug', [WebsiteController::class, 'updateSlug'])->name('websites.slug.update');
		Route::post('/websites/slug/check', [WebsiteController::class, 'checkSlug'])->name('websites.slug.check');
		Route::post('/websites/{website:slug}/restore', [WebsiteController::class, 'restore'])->name('websites.restore');

		// Chat route
		Route::post('/websites/{website:slug}/chat', [ChatMessageController::class, 'store'])->name('websites.chat.store');

		// API routes for file management
		Route::prefix('/api/websites/{website:slug}/files')
			->name('api.websites.files.')
			->controller(WebsiteFileController::class)
			->group(function () {
				Route::get('/', 'index')->name('index');
				Route::put('/', 'update')->name('update');
			});
	});

	Auth::routes();

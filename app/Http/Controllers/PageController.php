<?php

	namespace App\Http\Controllers;

	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Route;
	use Illuminate\Support\Facades\Auth;
	use Inertia\Inertia;
	use Illuminate\Foundation\Application; // Import Application

	class PageController extends Controller
	{
		/**
		 * Display the homepage.
		 * Redirects authenticated users to the dashboard, shows login/register for guests.
		 */
		public function home()
		{
			if (Auth::check()) {
				// If user is logged in, redirect them to the dashboard
				return redirect()->route('dashboard');
			}

			// If user is a guest, show the login page (using Breeze's default)
			// Or render a specific 'Welcome' page if you prefer
			return Inertia::render('Auth/Login', [ // Or 'Welcome' if you have that page setup
				'canResetPassword' => Route::has('password.request'),
				'status' => session('status'),
				// Required props for Auth/Login might vary slightly based on Breeze version
				// Add these if you use the 'Welcome' page instead:
				// 'canLogin' => Route::has('login'),
				// 'canRegister' => Route::has('register'),
				// 'laravelVersion' => Application::VERSION,
				// 'phpVersion' => PHP_VERSION,
			]);
		}
	}

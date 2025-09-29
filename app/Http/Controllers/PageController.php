<?php

	namespace App\Http\Controllers;

	use Illuminate\Support\Facades\Auth;
	use Illuminate\Support\Facades\Route;

	class PageController extends Controller
	{
		/**
		 * Display the homepage.
		 * Redirects authenticated users to the dashboard, shows login for guests.
		 */
		public function home()
		{
			if (Auth::check()) {
				// If user is logged in, redirect them to the dashboard
				return redirect()->route('dashboard');
			}

			// If user is a guest, show the login page
			return view('auth.login', [
				'canResetPassword' => Route::has('password.request'),
				'status' => session('status'),
			]);
		}
	}

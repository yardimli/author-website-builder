<?php

	namespace App\Http\Middleware;

	use Illuminate\Http\Request;
	use Inertia\Middleware;
	use Tightenco\Ziggy\Ziggy;
	use Illuminate\Support\Facades\Session; // <-- Import Session facade
	use Illuminate\Support\Facades\Log; // <-- Optional: Add Log facade for debugging

	class HandleInertiaRequests extends Middleware
	{
		/**
		 * The root template that is loaded on the first page visit.
		 *
		 * @var string
		 */
		protected $rootView = 'app';

		/**
		 * Determine the current asset version.
		 */
		public function version(Request $request): string|null
		{
			return parent::version($request);
		}

		/**
		 * Define the props that are shared by default.
		 *
		 * @return array<string, mixed>
		 */
		public function share(Request $request): array
		{
			// Get the base shared data (includes auth, default flash messages via parent::share)
			$shared = parent::share($request);

			// --- Explicitly add 'initial_prompt' from flash data ---
			if (Session::has('initial_prompt')) {
				// Log::info('HandleInertiaRequests: Sharing initial_prompt from session flash.'); // Optional debug log
				$shared['initial_prompt'] = Session::get('initial_prompt');
			}
			// --- End explicit sharing ---

			// Merge with other custom shared data (like auth, ziggy)
			// Note: We merge $shared last to ensure our explicit 'initial_prompt' isn't overwritten
			// if parent::share somehow also tried to add it differently.
			return array_merge([
				'auth' => [
					'user' => $request->user() ? [
						'id' => $request->user()->id,
						'name' => $request->user()->name,
						'email' => $request->user()->email,
						'profile_photo_url' => $request->user()?->profile_photo_url, // Use optional chaining
					] : null,
				],
//				'ziggy' => fn () => [
//					...(new Ziggy)->toArray(),
//					'location' => $request->url(),
//				],
				// Add any other globally shared props here
			], $shared); // Merge $shared containing parent data + our explicit prompt
		}
	}

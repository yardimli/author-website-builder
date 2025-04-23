<?php

	namespace App\Http\Controllers;

	use App\Models\Website;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Inertia\Inertia;
	use Inertia\Response; // Import Response

	class WebsiteController extends Controller
	{
		/**
		 * Display a listing of the user's websites (Dashboard).
		 */
		public function index(): Response // Type hint Inertia Response
		{
			$websites = Auth::user()->websites()->orderBy('created_at', 'desc')->get();
			return Inertia::render('Dashboard', [
				'websites' => $websites,
				'hasWebsites' => $websites->isNotEmpty(), // Flag for initial prompt
			]);
		}

		/**
		 * Store a newly created website in storage.
		 */
		public function store(Request $request)
		{
			$request->validate([
				// Add validation if needed, e.g., for initial name/prompt
				'name' => 'required|string|max:255', // Example: require a name
			]);

			$website = Auth::user()->websites()->create([
				'name' => $request->input('name', 'Untitled Website'), // Use input or default
			]);

			// Optionally: Create initial chat message or files here based on prompt

			// Redirect to the newly created website's view
			return redirect()->route('websites.show', $website);
		}

		/**
		 * Display the specified website (Chat/Preview/Code view).
		 */
		public function show(Website $website): Response // Type hint Inertia Response
		{
			// Authorize: Ensure the user owns this website
			$this->authorize('view', $website); // Requires a Policy (see step 10)

			// Eager load messages to avoid N+1 queries
			$website->load('chatMessages');

			return Inertia::render('Website/Show', [
				'website' => $website,
				'chatMessages' => $website->chatMessages, // Pass messages separately if preferred
				// Initial file list could be passed here, or fetched via API later
			]);
		}

		/**
		 * Show the form for creating a new resource.
		 * Not typically used with Inertia single-button creation.
		 */
		// public function create() { }

		/**
		 * Show the form for editing the specified resource.
		 * Not implemented in this scope.
		 */
		// public function edit(Website $website) { }

		/**
		 * Update the specified resource in storage.
		 * Not implemented in this scope.
		 */
		// public function update(Request $request, Website $website) { }

		/**
		 * Remove the specified resource from storage.
		 * Not implemented in this scope.
		 */
		// public function destroy(Website $website) { }
	}

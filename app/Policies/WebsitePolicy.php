<?php

	namespace App\Policies;

	use App\Models\User;
	use App\Models\Website;
	use Illuminate\Auth\Access\HandlesAuthorization; // Import correct trait

	class WebsitePolicy
	{
		use HandlesAuthorization; // Use correct trait

		/**
		 * Determine whether the user can view any models.
		 * Generally true if logged in, controller filters specific ones.
		 */
		public function viewAny(User $user): bool
		{
			return true; // Or check for specific roles if needed
		}

		/**
		 * Determine whether the user can view the model.
		 */
		public function view(User $user, Website $website): bool
		{
			// User can view the website if they own it
			return $user->id === $website->user_id;
		}

		/**
		 * Determine whether the user can create models.
		 */
		public function create(User $user): bool
		{
			return true; // Any authenticated user can create
		}

		/**
		 * Determine whether the user can update the model.
		 * Used for chat messages and potentially file updates via AI.
		 */
		public function update(User $user, Website $website): bool
		{
			// User can update (send messages, trigger AI changes) if they own it
			return $user->id === $website->user_id;
		}

		/**
		 * Determine whether the user can delete the model.
		 */
		public function delete(User $user, Website $website): bool
		{
			// User can delete the website if they own it
			return $user->id === $website->user_id;
		}

		/**
		 * Determine whether the user can restore the model.
		 * Not implemented in this scope.
		 */
		// public function restore(User $user, Website $website): bool { }

		/**
		 * Determine whether the user can permanently delete the model.
		 * Not implemented in this scope.
		 */
		// public function forceDelete(User $user, Website $website): bool { }
	}

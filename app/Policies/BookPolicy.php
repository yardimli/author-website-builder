<?php

	namespace App\Policies;

	use App\Models\Book;
	use App\Models\User;
	use Illuminate\Auth\Access\HandlesAuthorization;

	/**
	 * NEW: Policy to control access to Book resources.
	 * NOTE: This policy must be registered in your app/Providers/AuthServiceProvider.php file.
	 * Add `App\Models\Book::class => App\Policies\BookPolicy::class,` to the `$policies` array.
	 */
	class BookPolicy
	{
		use HandlesAuthorization;

		/**
		 * Determine whether the user can update the book.
		 *
		 * @param  \App\Models\User  $user
		 * @param  \App\Models\Book  $book
		 * @return bool
		 */
		public function update(User $user, Book $book): bool
		{
			// A user can update a book if they own it.
			return $user->id === $book->user_id;
		}

		/**
		 * Determine whether the user can delete the book.
		 *
		 * @param  \App\Models\User  $user
		 * @param  \App\Models\Book  $book
		 * @return bool
		 */
		public function delete(User $user, Book $book): bool
		{
			// A user can delete a book if they own it.
			return $user->id === $book->user_id;
		}
	}

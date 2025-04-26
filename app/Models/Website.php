<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;
	use Illuminate\Database\Eloquent\Relations\BelongsTo;
	use Illuminate\Database\Eloquent\Relations\HasMany;
	use Illuminate\Database\Eloquent\Relations\HasOne;
	use Illuminate\Support\Collection;

	class Website extends Model
	{
		use HasFactory;

		protected $fillable = [
			'user_id',
			'name',
			'primary_book_id',
			'featured_book_ids',
		];

		protected $casts = [
			'featured_book_ids' => 'array',
		];

		public function user(): BelongsTo
		{
			return $this->belongsTo(User::class);
		}

		public function chatMessages(): HasMany
		{
			return $this->hasMany(ChatMessage::class)->orderBy('created_at');
		}

		public function websiteFiles(): HasMany
		{
			return $this->hasMany(WebsiteFile::class);
		}

		// Helper to get the latest version of all files for this website
		public function latestWebsiteFiles()
		{
			// This is a more complex query. We group by filename/folder and get the max version.
			// Using raw SQL might be more efficient here, but Eloquent can do it too.
			// For simplicity in this example, we might fetch this differently in the controller.
			// A simpler approach is shown in the WebsitePreviewController.
			return $this->websiteFiles()
				->orderBy('version', 'desc'); // Get latest versions first
			// Further filtering/grouping needed here or in the controller
		}

		public function primaryBook(): BelongsTo // BelongsTo is appropriate here
		{
			return $this->belongsTo(Book::class, 'primary_book_id');
		}

		public function getFeaturedBooksAttribute(): Collection
		{
			$ids = $this->featured_book_ids ?? [];
			if (empty($ids)) {
				return collect(); // Return empty collection if no IDs
			}
			return Book::whereIn('id', $ids)->get();
		}


	}

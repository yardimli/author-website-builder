<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;
	use Illuminate\Database\Eloquent\Relations\BelongsTo;
	use Illuminate\Support\Facades\Storage; // <-- Add Storage

	class Book extends Model
	{
		use HasFactory;

		protected $fillable = [
			'user_id',
			'cover_image_path',
			'title',
			'subtitle',
			'hook',
			'about',
			'extract',
			'amazon_link',
			'other_link',
			'published_at',
			'series_name',
			'series_number',
		];

		protected $casts = [
			'published_at' => 'date',
			'series_number' => 'integer',
		];

		/**
		 * The accessors to append to the model's array form.
		 *
		 * @var array
		 */
		protected $appends = [
			'cover_image_url', // <-- Add accessor for URL
		];

		public function user(): BelongsTo
		{
			return $this->belongsTo(User::class);
		}

		// --- ADDED ACCESSOR ---
		/**
		 * Get the URL to the book's cover image.
		 *
		 * @return string|null
		 */
		public function getCoverImageUrlAttribute(): ?string
		{
			if ($this->cover_image_path && Storage::disk('public')->exists($this->cover_image_path)) {
				return Storage::disk('public')->url($this->cover_image_path);
			}
			return null; // Or a placeholder URL
		}
	}

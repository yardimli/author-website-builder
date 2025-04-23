<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;
	use Illuminate\Database\Eloquent\Relations\BelongsTo;
	use Illuminate\Database\Eloquent\Relations\HasMany;

	class Website extends Model
	{
		use HasFactory;

		protected $fillable = [
			'user_id',
			'name',
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
	}

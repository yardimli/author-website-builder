<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;
	use Illuminate\Database\Eloquent\Relations\BelongsTo;

	class WebsiteFile extends Model
	{
		use HasFactory;

		protected $fillable = [
			'website_id',
			'filename',
			'folder',
			'filetype',
			'version',
			'content',
			'is_deleted',
            'chat_messages_ids',
		];

		// Add casting for the boolean field
		protected $casts = [
			'is_deleted' => 'boolean',
            'chat_messages_ids' => 'array',
		];


		public function website(): BelongsTo
		{
			return $this->belongsTo(Website::class);
		}

		// Helper function to parse a full path into folder and filename
		public static function parsePath(string $fullPath): array
		{
			$fullPath = trim($fullPath);
			$filename = basename($fullPath);
			$folder = dirname($fullPath);

			// Normalize folder representation
			if ($folder === '.' || $folder === '') {
				$folder = '/'; // Root folder
			} else {
				// Ensure leading slash, remove trailing, sanitize '..'
				$folder = '/' . trim(str_replace('..', '', $folder), '/');
				if (empty($folder)) { // Handle cases like '/..' becoming empty
					$folder = '/';
				}
			}

			// Basic filename validation
			if (str_contains($filename, '/') || str_contains($filename, '\\') || $filename === '.' || $filename === '..') {
				Log::warning("Invalid filename detected in path parsing: {$filename}");
				// Decide how to handle: throw exception, return null, return defaults?
				// Returning defaults might hide LLM errors, throwing might be better
				// For now, let's return nulls to indicate failure upstream
				return ['folder' => null, 'filename' => null];
			}


			return ['folder' => $folder, 'filename' => $filename];
		}

		// Helper to find the latest *active* version of a file
		public static function findLatestActive(int $websiteId, string $folder, string $filename): ?WebsiteFile
		{
			return self::where('website_id', $websiteId)
				->where('folder', $folder)
				->where('filename', $filename)
				->where('is_deleted', false) // Only find active files
				->orderByDesc('version')
				->first();
		}
	}

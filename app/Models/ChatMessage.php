<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;
	use Illuminate\Database\Eloquent\Relations\BelongsTo;
    use Illuminate\Support\Str;

	class ChatMessage extends Model
	{
		use HasFactory;

		protected $fillable = [
			'website_id',
			'role',
			'content',
            'prompt_images_ids',
            'deleted',
            'uuid',
		];

		protected $casts = [
			'created_at' => 'datetime',
			'updated_at' => 'datetime',
            'prompt_images_ids' => 'array',
            'deleted' => 'boolean',
		];

        protected static function booted()
        {
            // Automatically generate a UUID when a new ChatMessage is being created
            static::creating(function ($message) {
                if (empty($message->uuid)) {
                    $message->uuid = (string) Str::uuid();
                }
            });
        }

		public function website(): BelongsTo
		{
			return $this->belongsTo(Website::class);
		}
	}

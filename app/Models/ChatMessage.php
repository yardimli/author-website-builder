<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;
	use Illuminate\Database\Eloquent\Relations\BelongsTo;

	class ChatMessage extends Model
	{
		use HasFactory;

		protected $fillable = [
			'website_id',
			'role',
			'content',
            'prompt_images_ids',
            'deleted',
		];

		protected $casts = [
			'created_at' => 'datetime',
			'updated_at' => 'datetime',
            'prompt_images_ids' => 'array',
            'deleted' => 'boolean',
		];

		public function website(): BelongsTo
		{
			return $this->belongsTo(Website::class);
		}
	}

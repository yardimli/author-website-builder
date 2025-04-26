<?php

	namespace App\Models;

	// use Illuminate\Contracts\Auth\MustVerifyEmail;
	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Relations\HasMany;
	use Illuminate\Foundation\Auth\User as Authenticatable;
	use Illuminate\Notifications\Notifiable;
	use Laravel\Sanctum\HasApiTokens;
	use Illuminate\Support\Facades\Storage; // <-- Add Storage

	class User extends Authenticatable
	{
		use HasApiTokens, HasFactory, Notifiable;

		/**
		 * The attributes that are mass assignable.
		 *
		 * @var array<int, string>
		 */
		protected $fillable = [
			'name',
			'email',
			'password',
			'profile_photo_path', // <-- Add
			'bio',              // <-- Add
		];

		/**
		 * The attributes that should be hidden for serialization.
		 *
		 * @var array<int, string>
		 */
		protected $hidden = [
			'password',
			'remember_token',
		];

		/**
		 * The attributes that should be cast.
		 *
		 * @var array<string, string>
		 */
		protected $casts = [
			'email_verified_at' => 'datetime',
			'password' => 'hashed',
		];

		/**
		 * The accessors to append to the model's array form.
		 *
		 * @var array
		 */
		protected $appends = [
			'profile_photo_url', // <-- Add accessor for URL
		];


		public function websites(): HasMany
		{
			return $this->hasMany(Website::class);
		}

		// --- ADDED RELATIONSHIP ---
		public function books(): HasMany
		{
			return $this->hasMany(Book::class)->orderBy('published_at', 'desc')->orderBy('series_name')->orderBy('series_number');
		}

		// --- ADDED ACCESSOR ---
		/**
		 * Get the URL to the user's profile photo.
		 *
		 * @return string|null
		 */
		public function getProfilePhotoUrlAttribute(): ?string
		{
			if ($this->profile_photo_path && Storage::disk('public')->exists($this->profile_photo_path)) {
				return Storage::disk('public')->url($this->profile_photo_path);
			}

			// Optional: Return a default image URL
			// return 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&color=7F9CF5&background=EBF4FF';
			return null;
		}
	}

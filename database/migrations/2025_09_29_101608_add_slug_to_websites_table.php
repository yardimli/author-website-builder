<?php

	use App\Models\User;
	use App\Models\Website;
	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;
	use Illuminate\Support\Str;

	return new class extends Migration
	{
		/**
		 * Run the migrations.
		 */
		public function up(): void
		{
			// Add the slug column as nullable first to allow populating existing records
			Schema::table('websites', function (Blueprint $table) {
				$table->string('slug')->after('name')->unique()->nullable();
			});

			// Populate the slug for any existing websites
			// This ensures old sites continue to work without manual intervention.
			$websites = Website::with('user')->get();
			foreach ($websites as $website) {
				$baseSlug = Str::slug($website->user->name . '-' . $website->name);
				$slug = $baseSlug;
				$counter = 1;
				// Ensure the generated slug is unique
				while (Website::where('slug', $slug)->exists()) {
					$slug = $baseSlug . '-' . $counter;
					$counter++;
				}
				$website->slug = $slug;
				$website->save();
			}

			// Now that all records have a slug, make the column non-nullable
			Schema::table('websites', function (Blueprint $table) {
				$table->string('slug')->nullable(false)->change();
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::table('websites', function (Blueprint $table) {
				$table->dropColumn('slug');
			});
		}
	};

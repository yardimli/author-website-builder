<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	return new class extends Migration
	{
		/**
		 * Run the migrations.
		 */
		public function up(): void
		{
			Schema::table('website_files', function (Blueprint $table) {
				// Add is_deleted column after 'content', defaulting to false
				$table->boolean('is_deleted')->default(false)->after('content');
				// Optional: Add an index if you frequently query deleted files specifically
				// $table->index('is_deleted');
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::table('website_files', function (Blueprint $table) {
				$table->dropColumn('is_deleted');
			});
		}
	};

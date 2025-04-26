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
			Schema::table('websites', function (Blueprint $table) {
				// Add foreign key for the primary book
				$table->foreignId('primary_book_id')
					->nullable()
					->after('name') // Place it after the name column
					->constrained('books') // Assumes your books table is named 'books'
					->onDelete('set null'); // If the book is deleted, set this column to null

				// Add JSON column for additional featured book IDs
				$table->json('featured_book_ids')->nullable()->after('primary_book_id');
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::table('websites', function (Blueprint $table) {
				// Drop the foreign key constraint first
				// The constraint name follows the pattern: {table}_{column}_foreign
				$table->dropForeign(['primary_book_id']);

				// Drop the columns
				$table->dropColumn('primary_book_id');
				$table->dropColumn('featured_book_ids');
			});
		}
	};

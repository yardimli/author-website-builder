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
			Schema::create('books', function (Blueprint $table) {
				$table->id();
				$table->foreignId('user_id')->constrained()->onDelete('cascade');
				$table->string('cover_image_path')->nullable();
				$table->string('title');
				$table->string('subtitle')->nullable();
				$table->text('hook')->nullable();
				$table->text('about')->nullable();
				$table->longText('extract')->nullable(); // For first chapter etc.
				$table->string('amazon_link')->nullable();
				$table->string('other_link')->nullable();
				$table->date('published_at')->nullable();
				$table->string('series_name')->nullable();
				$table->unsignedInteger('series_number')->nullable();
				$table->timestamps();
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::dropIfExists('books');
		}
	};

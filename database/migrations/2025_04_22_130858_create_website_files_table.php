<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	return new class extends Migration
	{
		public function up(): void
		{
			Schema::create('website_files', function (Blueprint $table) {
				$table->id();
				$table->foreignId('website_id')->constrained()->onDelete('cascade'); // Link to website
				$table->string('filename'); // e.g., index.html, style.css
				$table->string('folder')->default('/'); // e.g., /, /src, /public
				$table->string('filetype')->nullable(); // e.g., html, css, js (can be derived)
				$table->unsignedInteger('version'); // Version number for this specific file
				$table->longText('content'); // The actual file content
				$table->timestamps();

				// Index to quickly find the latest version of a file
				$table->index(['website_id', 'folder', 'filename', 'version']);
				// Unique constraint to prevent duplicate versions of the same file
				$table->unique(['website_id', 'folder', 'filename', 'version']);
			});
		}

		public function down(): void
		{
			Schema::dropIfExists('website_files');
		}
	};

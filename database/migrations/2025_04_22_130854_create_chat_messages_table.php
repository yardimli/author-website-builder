<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	return new class extends Migration
	{
		public function up(): void
		{
			Schema::create('chat_messages', function (Blueprint $table) {
				$table->id();
				$table->foreignId('website_id')->constrained()->onDelete('cascade'); // Link to website
				$table->enum('role', ['user', 'assistant']); // Who sent the message
				$table->text('content'); // The message text
				$table->timestamps();
			});
		}

		public function down(): void
		{
			Schema::dropIfExists('chat_messages');
		}
	};

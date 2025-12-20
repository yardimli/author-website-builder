<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_user_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')
                ->constrained('websites')
                ->cascadeOnDelete();

            $table->string('image_file_path', 255);
            $table->boolean('is_deleted')->default(false);

            $table->timestamps();

            $table->index(['website_id']);

            // Optional index if you commonly query by website_id + is_deleted
            // $table->index(['website_id', 'is_deleted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_user_images');
    }
};

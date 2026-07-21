<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('featured_book_ids');
            $table->string('demo_key', 40)->nullable()->after('is_demo');
            $table->unique(['user_id', 'demo_key']);
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'demo_key']);
            $table->dropColumn(['is_demo', 'demo_key']);
        });
    }
};

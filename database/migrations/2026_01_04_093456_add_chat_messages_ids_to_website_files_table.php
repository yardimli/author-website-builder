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
            // LONGTEXT, nullable
            // Note: We use longText to store JSON array string "[1,2,3...]"
            $table->longText('chat_messages_ids')->nullable()->after('is_deleted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('website_files', function (Blueprint $table) {
            $table->dropColumn('chat_messages_ids');
        });
    }
};

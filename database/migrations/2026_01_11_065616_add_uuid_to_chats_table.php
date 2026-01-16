<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // STEP 1: Add the column as NULLABLE first.
        // We do this so the database doesn't crash on existing rows.
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->uuid('uuid')->after('id')->nullable();
        });

        // STEP 2: Backfill existing data.
        // We fetch all IDs and generate a unique UUID for each one.
        // Using cursor() uses less memory than get() for large tables.
        DB::table('chat_messages')->orderBy('id')->cursor()->each(function ($chat) {
            DB::table('chat_messages')
                ->where('id', $chat->id)
                ->update(['uuid' => (string) Str::uuid()]);
        });

        // STEP 3: Enforce constraints.
        // Now that every row has a UUID, we can make it NOT NULL and UNIQUE.
        Schema::table('chat_messages', function (Blueprint $table) {
            // change() requires the doctrine/dbal package in some setups,
            // but recent Laravel versions handle this natively for most DBs.
            $table->uuid('uuid')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            // Simply drop the column.
            // The unique index is usually dropped automatically with the column.
            $table->dropColumn('uuid');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $checkName = 'chat_messages_prompt_images_ids_json';

    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->longText('prompt_images_ids')
                ->nullable()
                ->after('content');
        });

        // Add JSON validity CHECK constraint (MySQL 8.0.16+ enforces it)
        DB::statement(sprintf(
            "ALTER TABLE `chat_messages` ADD CONSTRAINT `%s` CHECK (json_valid(`prompt_images_ids`))",
            $this->checkName
        ));
    }

    public function down(): void
    {
        // Drop CHECK constraint first (MySQL requires it before dropping the column)
        try {
            DB::statement(sprintf(
                "ALTER TABLE `chat_messages` DROP CHECK `%s`",
                $this->checkName
            ));
        } catch (\Throwable $e) {
            // ignore if constraint doesn't exist / DB doesn't support it
        }

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('prompt_images_ids');
        });
    }
};

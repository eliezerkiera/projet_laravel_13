<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Arabic is not supported by the application (only fr and en are). Users who
     * selected Arabic fall back to auto-detection and the language is deactivated.
     */
    public function up(): void
    {
        $arabicId = (int) DB::table('languages')->where('code', 'ar')->value('id');

        if ($arabicId < 1) {
            return;
        }

        // Fall back to automatic language detection for users who chose Arabic.
        if (Schema::hasTable('users')) {
            DB::table('users')
                ->where('language_id', $arabicId)
                ->update(['language_id' => null]);
        }

        DB::table('languages')
            ->where('id', $arabicId)
            ->update(['is_active' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $arabicId = (int) DB::table('languages')->where('code', 'ar')->value('id');

        if ($arabicId < 1) {
            return;
        }

        DB::table('languages')
            ->where('id', $arabicId)
            ->update(['is_active' => true]);
    }
};

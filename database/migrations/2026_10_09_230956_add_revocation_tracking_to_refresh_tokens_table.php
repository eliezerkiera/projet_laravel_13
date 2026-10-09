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
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->uuid('family_id')->nullable()->after('id')->index();
            $table->string('revoked_reason', 50)->nullable()->after('revoked');
            $table->timestamp('revoked_at')->nullable()->after('revoked_reason');
            $table->timestamp('rotated_at')->nullable()->after('revoked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropColumn(['family_id', 'revoked_reason', 'revoked_at', 'rotated_at']);
        });
    }
};

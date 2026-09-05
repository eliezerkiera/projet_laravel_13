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
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();          // ISO 639-1 : fr, en, ar
            $table->string('name');                         // Nom en anglais : French
            $table->string('native_name');                  // Nom local : Français, العربية
            $table->string('flag', 10)->nullable();         // Emoji drapeau : 🇫🇷
            $table->string('locale', 10)->unique();         // fr_FR, en_US, ar_SA
            $table->string('carbon_locale', 10)             // fr, en, ar (pour Carbon plus tard)
                ->nullable();
            $table->enum('direction', ['ltr', 'rtl'])       // Sens d'écriture
                ->default('ltr');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};

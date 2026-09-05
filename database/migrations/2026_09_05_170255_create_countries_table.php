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
        Schema::create('countries', function (Blueprint $table) {
                $table->id();
                $table->string('code', 3)->unique();        // ISO 3166-1 alpha-2 : BF, FR, US
                $table->string('name');                      // Nom en anglais : Burkina Faso
                $table->string('native_name');               // Nom local : Burkina Faso, France
                $table->string('flag_image')->nullable();    // Chemin image : flags/bf.png
                $table->string('phone_code', 10)->nullable(); // Indicatif : +226, +33
                $table->foreignId('currency_id')             // Devise du pays
                    ->constrained('currencies')
                    ->restrictOnDelete();
                $table->foreignId('language_id')             // Langue principale du pays
                    ->constrained('languages')
                    ->restrictOnDelete();
                $table->boolean('is_active')->default(true);
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
        Schema::dropIfExists('countries');
    }
};

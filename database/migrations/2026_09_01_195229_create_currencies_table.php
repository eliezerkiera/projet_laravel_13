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
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();        // ISO 4217 : XOF, EUR, USD
            $table->string('name');                      // Franc CFA, Euro
            $table->string('symbol', 10)->nullable();    // €, $, ₣
            $table->string('symbol_position', 10)         // 'before' ou 'after' le montant
                     ->default('after');
            $table->unsignedTinyInteger('decimal_places') // Nombre de décimales (0, 2, 3)
                        ->default(2);
            $table->string('decimal_separator', 1)       // '.' ou ','
                        ->default(',');
            $table->string('thousands_separator', 1)     // ' ', '.', ','
                        ->default(' ');
            $table->boolean('is_active')->default(true); // Activer/désactiver une devise
            $table->boolean('is_default')->default(false); // Devise par défaut du système
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};

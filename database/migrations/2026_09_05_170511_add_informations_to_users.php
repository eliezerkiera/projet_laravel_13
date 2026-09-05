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
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('name','last_name');
            $table->string('first_name')->nullable();
            $table->unsignedBigInteger('language_id');
            $table->unsignedBigInteger('country_id');
            $table->foreign('language_id')->references('id')->on('languages');
            $table->foreign('country_id')->references('id')->on('countries');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('name','last_name');
            $table->string('first_name')->nullable();
            $table->unsignedBigInteger('language_id');
            $table->unsignedBigInteger('country_id');
            $table->foreign('language_id')->references('id')->on('languages');
            $table->foreign('country_id')->references('id')->on('countries');
        });
    }
};

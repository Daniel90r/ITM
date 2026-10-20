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
        Schema::dropIfExists('conductas');
        Schema::dropIfExists('tipo_evaluaciones');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('tipo_evaluaciones', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('conductas', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }
};

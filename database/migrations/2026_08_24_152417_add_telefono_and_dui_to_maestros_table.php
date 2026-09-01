<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maestros', function (Blueprint $table) {
            $table->string('telefono', 9)->nullable();
            $table->string('dui', 10)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('maestros', function (Blueprint $table) {
            $table->dropUnique(['dui']);
            $table->dropColumn(['telefono', 'dui']);
        });
    }
};

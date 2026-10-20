<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directores', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('nombre');
            $table->string('dui')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->string('firma_path')->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->boolean('activo')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('directores', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'user_id',
                'nombre',
                'dui',
                'telefono',
                'email',
                'firma_path',
                'fecha_inicio',
                'fecha_fin',
                'activo',
            ]);
        });
    }
};

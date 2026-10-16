<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispositivos_iot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('puerta_id')->constrained('puertas')->restrictOnDelete();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['puerta_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivos_iot');
    }
};

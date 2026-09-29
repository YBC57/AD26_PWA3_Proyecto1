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
        Schema::create('eventos_puerta', function (Blueprint $table) {
            $table->id();
            $table->enum('estado_puerta', ['abierta', 'cerrada']);
            $table->enum('estado_candado', ['bloqueado', 'desbloqueado']);
            $table->boolean('vibracion_detectada')->default(false);
            $table->unsignedInteger('cantidad_vibracion')->default(0);
            $table->string('tipo_evento', 50)->index();
            $table->timestamp('detectado_en')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eventos_puerta');
    }
};

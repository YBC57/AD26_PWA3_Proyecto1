<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos_puerta', function (Blueprint $table) {
            $table->string('origen', 20)->default('prueba')->after('tipo_evento')->index();
            $table->foreignId('dispositivo_iot_id')
                ->nullable()
                ->after('origen')
                ->constrained('dispositivos_iot')
                ->nullOnDelete();
            $table->uuid('id_evento_dispositivo')->nullable()->after('dispositivo_iot_id');
            $table->char('hash_payload', 64)->nullable()->after('id_evento_dispositivo');
            $table->decimal('distancia_pasador_cm', 7, 2)->nullable()->after('hash_payload');
            $table->unique(
                ['dispositivo_iot_id', 'id_evento_dispositivo'],
                'eventos_puerta_dispositivo_idempotencia_unique',
            );
            $table->enum('estado_puerta', ['abierta', 'cerrada'])->nullable()->change();
            $table->enum('estado_candado', ['bloqueado', 'desbloqueado'])->nullable()->change();
            $table->unsignedInteger('cantidad_vibracion')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (
            DB::table('eventos_puerta')
                ->whereNull('estado_puerta')
                ->orWhereNull('estado_candado')
                ->orWhereNull('cantidad_vibracion')
                ->exists()
        ) {
            throw new RuntimeException(
                'No se puede restaurar el esquema: hay eventos IoT con campos indeterminados.',
            );
        }

        Schema::table('eventos_puerta', function (Blueprint $table) {
            $table->dropUnique('eventos_puerta_dispositivo_idempotencia_unique');
            $table->dropForeign(['dispositivo_iot_id']);
            $table->dropIndex(['origen']);
            $table->dropColumn([
                'origen',
                'dispositivo_iot_id',
                'id_evento_dispositivo',
                'hash_payload',
                'distancia_pasador_cm',
            ]);
            $table->enum('estado_puerta', ['abierta', 'cerrada'])->nullable(false)->change();
            $table->enum('estado_candado', ['bloqueado', 'desbloqueado'])->nullable(false)->change();
            $table->unsignedInteger('cantidad_vibracion')->nullable(false)->change();
        });
    }
};

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
            $table->foreignId('puerta_id')->nullable()->after('id');
        });

        $puertaId = DB::table('puertas')
            ->where('nombre', 'Puerta de maqueta')
            ->value('id');

        if ($puertaId === null) {
            $puertaId = DB::table('puertas')->insertGetId([
                'nombre' => 'Puerta de maqueta',
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('eventos_puerta')
            ->whereNull('puerta_id')
            ->update(['puerta_id' => $puertaId]);

        Schema::table('eventos_puerta', function (Blueprint $table) {
            $table->foreign('puerta_id')
                ->references('id')
                ->on('puertas')
                ->restrictOnDelete();
            $table->unsignedBigInteger('puerta_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('eventos_puerta', function (Blueprint $table) {
            $table->dropForeign(['puerta_id']);
            $table->dropColumn('puerta_id');
        });
    }
};

<?php

namespace Database\Seeders;

use App\Models\EventoPuerta;
use App\Models\Puerta;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

class EventoPuertaSeeder extends Seeder
{
    private const EVENTOS_ESPERADOS = [
        'puerta_abierta' => [
            'cantidad' => 5,
            'estado_puerta' => 'abierta',
            'estado_candado' => 'desbloqueado',
            'vibracion_detectada' => false,
            'cantidad_vibracion_min' => 0,
            'cantidad_vibracion_max' => 0,
        ],
        'puerta_cerrada' => [
            'cantidad' => 5,
            'estado_puerta' => 'cerrada',
            'estado_candado' => 'desbloqueado',
            'vibracion_detectada' => false,
            'cantidad_vibracion_min' => 0,
            'cantidad_vibracion_max' => 0,
        ],
        'candado_activado' => [
            'cantidad' => 5,
            'estado_puerta' => 'cerrada',
            'estado_candado' => 'bloqueado',
            'vibracion_detectada' => false,
            'cantidad_vibracion_min' => 0,
            'cantidad_vibracion_max' => 0,
        ],
        'candado_desactivado' => [
            'cantidad' => 4,
            'estado_puerta' => 'cerrada',
            'estado_candado' => 'desbloqueado',
            'vibracion_detectada' => false,
            'cantidad_vibracion_min' => 0,
            'cantidad_vibracion_max' => 0,
        ],
        'vibracion_suave' => [
            'cantidad' => 4,
            'estado_puerta' => 'cerrada',
            'estado_candado' => 'bloqueado',
            'vibracion_detectada' => true,
            'cantidad_vibracion_min' => 1,
            'cantidad_vibracion_max' => 3,
        ],
        'vibracion_fuerte' => [
            'cantidad' => 4,
            'estado_puerta' => 'cerrada',
            'estado_candado' => 'bloqueado',
            'vibracion_detectada' => true,
            'cantidad_vibracion_min' => 4,
            'cantidad_vibracion_max' => 8,
        ],
        'intento_forzado' => [
            'cantidad' => 3,
            'estado_puerta' => 'cerrada',
            'estado_candado' => 'bloqueado',
            'vibracion_detectada' => true,
            'cantidad_vibracion_min' => 2,
            'cantidad_vibracion_max' => 10,
        ],
    ];

    /**
     * Seed representative door and sensor events.
     */
    public function run(): void
    {
        if (! Schema::hasColumn('puertas', 'tipo')) {
            throw new LogicException(
                'Ejecuta la migración de clasificación de puertas antes de sembrar eventos.',
            );
        }

        DB::transaction(function (): void {
            $puerta = Puerta::query()
                ->where('nombre', 'Puerta de prueba 2')
                ->lockForUpdate()
                ->first();

            if (! $puerta) {
                throw new LogicException(
                    'No existe Puerta de prueba 2. Ejecuta primero PuertaSeeder después de la migración de clasificación.',
                );
            }

            if ($puerta->tipo !== 'prueba') {
                throw new LogicException('Puerta de prueba 2 no está clasificada como prueba.');
            }

            $eventos = $puerta->eventos()
                ->lockForUpdate()
                ->get([
                    'origen',
                    'dispositivo_iot_id',
                    'id_evento_dispositivo',
                    'hash_payload',
                    'distancia_pasador_cm',
                    'estado_puerta',
                    'estado_candado',
                    'vibracion_detectada',
                    'cantidad_vibracion',
                    'tipo_evento',
                ]);

            if ($eventos->isNotEmpty()) {
                if ($this->esLoteDePruebaExistente($eventos)) {
                    return;
                }

                throw new LogicException(
                    'Puerta de prueba 2 ya tiene eventos que no coinciden con el lote de prueba esperado; no se insertaron duplicados.',
                );
            }

            $this->crearEventosDePrueba($puerta);
        });
    }

    /**
     * @param  Collection<int, EventoPuerta>  $eventos
     */
    private function esLoteDePruebaExistente(Collection $eventos): bool
    {
        $cantidadEsperada = array_sum(array_column(self::EVENTOS_ESPERADOS, 'cantidad'));

        if ($eventos->count() !== $cantidadEsperada) {
            return false;
        }

        foreach (self::EVENTOS_ESPERADOS as $tipo => $esperado) {
            $eventosDelTipo = $eventos->where('tipo_evento', $tipo);

            if ($eventosDelTipo->count() !== $esperado['cantidad']) {
                return false;
            }

            foreach ($eventosDelTipo as $evento) {
                if (
                    $evento->origen !== 'prueba'
                    || $evento->dispositivo_iot_id !== null
                    || $evento->id_evento_dispositivo !== null
                    || $evento->hash_payload !== null
                    || $evento->distancia_pasador_cm !== null
                    || $evento->estado_puerta !== $esperado['estado_puerta']
                    || $evento->estado_candado !== $esperado['estado_candado']
                    || $evento->vibracion_detectada !== $esperado['vibracion_detectada']
                    || $evento->cantidad_vibracion === null
                    || $evento->cantidad_vibracion < $esperado['cantidad_vibracion_min']
                    || $evento->cantidad_vibracion > $esperado['cantidad_vibracion_max']
                ) {
                    return false;
                }
            }
        }

        return true;
    }

    private function crearEventosDePrueba(Puerta $puerta): void
    {
        EventoPuerta::factory()->for($puerta)->state(['origen' => 'prueba'])->count(5)->abierta()->create();
        EventoPuerta::factory()->for($puerta)->state(['origen' => 'prueba'])->count(5)->cerradaDesbloqueada()->create();
        EventoPuerta::factory()->for($puerta)->state(['origen' => 'prueba'])->count(5)->cerradaBloqueada()->create();
        EventoPuerta::factory()->for($puerta)->state(['origen' => 'prueba'])->count(4)->candadoDesactivado()->create();
        EventoPuerta::factory()->for($puerta)->state(['origen' => 'prueba'])->count(4)->vibracionSuave()->create();
        EventoPuerta::factory()->for($puerta)->state(['origen' => 'prueba'])->count(4)->vibracionFuerte()->create();
        EventoPuerta::factory()->for($puerta)->state(['origen' => 'prueba'])->count(3)->intentoForzado()->create();
    }
}

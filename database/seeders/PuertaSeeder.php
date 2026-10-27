<?php

namespace Database\Seeders;

use App\Models\Puerta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

class PuertaSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasColumn('puertas', 'tipo')) {
            throw new LogicException(
                'Ejecuta la migración de clasificación de puertas antes de sembrarlas.',
            );
        }

        DB::transaction(function (): void {
            $puertas = Puerta::query()
                ->whereIn('nombre', [
                    'Puerta principal',
                    'Puerta de prueba 1',
                    'Puerta de prueba 2',
                    'Puerta de maqueta',
                ])
                ->lockForUpdate()
                ->get()
                ->keyBy('nombre');

            $puertaLegacy = $puertas->get('Puerta de maqueta');
            $puertaPrueba1 = $puertas->get('Puerta de prueba 1');

            if ($puertaLegacy && $puertaPrueba1) {
                throw new LogicException(
                    'Existen tanto Puerta de maqueta como Puerta de prueba 1; resuelve el conflicto antes de sembrar.',
                );
            }

            $puertaPrueba1 ??= $puertaLegacy;

            $clasificaciones = [
                'Puerta principal' => 'real',
                'Puerta de prueba 1' => 'prueba',
                'Puerta de prueba 2' => 'prueba',
            ];

            foreach ($clasificaciones as $nombre => $tipo) {
                $puerta = $nombre === 'Puerta de prueba 1'
                    ? $puertaPrueba1
                    : $puertas->get($nombre);

                if ($puerta && $puerta->tipo !== $tipo) {
                    throw new LogicException(
                        "La puerta [{$nombre}] ya tiene un tipo incompatible [{$puerta->tipo}].",
                    );
                }
            }

            if ($puertaLegacy) {
                $puertaLegacy->nombre = 'Puerta de prueba 1';
                $puertaLegacy->save();
            }

            foreach ($clasificaciones as $nombre => $tipo) {
                $puerta = Puerta::query()->firstOrCreate(
                    ['nombre' => $nombre],
                    ['tipo' => $tipo, 'activa' => true],
                );

                if ($puerta->tipo !== $tipo) {
                    throw new LogicException(
                        "La puerta [{$nombre}] ya tiene un tipo incompatible [{$puerta->tipo}].",
                    );
                }
            }
        });
    }
}

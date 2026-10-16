<?php

namespace App\Console\Commands;

use App\Models\DispositivoIot;
use App\Models\Puerta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrearDispositivoIot extends Command
{
    protected $signature = 'iot:crear-dispositivo {puerta_id} {nombre}';

    protected $description = 'Crea un dispositivo IoT y muestra su credencial una sola vez';

    public function handle(): int
    {
        $puerta = Puerta::query()->find($this->argument('puerta_id'));

        if (! $puerta || ! $puerta->activa) {
            $this->error('La puerta no existe o está inactiva.');

            return self::FAILURE;
        }

        $nombre = (string) $this->argument('nombre');

        if (DispositivoIot::query()->where('puerta_id', $puerta->id)->where('nombre', $nombre)->exists()) {
            $this->error('Ya existe un dispositivo con ese nombre para la puerta.');

            return self::FAILURE;
        }

        $token = DB::transaction(function () use ($puerta, $nombre) {
            $dispositivo = DispositivoIot::query()->create([
                'puerta_id' => $puerta->id,
                'nombre' => $nombre,
                'activo' => true,
            ]);

            return $dispositivo->createToken(
                $nombre,
                ['events:create'],
                now()->addDays(90),
            );
        });

        $this->info('Dispositivo creado. Guarda esta credencial ahora; no se volverá a mostrar.');
        $this->line('Dispositivo ID: '.$token->accessToken->tokenable_id);
        $this->line('Token ID: '.$token->accessToken->id);
        $this->line('Bearer token: '.$token->plainTextToken);

        return self::SUCCESS;
    }
}

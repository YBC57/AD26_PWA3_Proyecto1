<?php

namespace App\Console\Commands;

use App\Models\DispositivoIot;
use Illuminate\Console\Command;

class RevocarTokenDispositivoIot extends Command
{
    protected $signature = 'iot:revocar-token {dispositivo_id} {token_id}';

    protected $description = 'Revoca un token IoT específico de un dispositivo';

    public function handle(): int
    {
        $dispositivo = DispositivoIot::query()->find($this->argument('dispositivo_id'));

        if (! $dispositivo) {
            $this->error('El dispositivo no existe.');

            return self::FAILURE;
        }

        $revocados = $dispositivo->tokens()
            ->whereKey($this->argument('token_id'))
            ->delete();

        if ($revocados === 0) {
            $this->error('El token no existe para ese dispositivo.');

            return self::FAILURE;
        }

        $this->info('Token revocado.');

        return self::SUCCESS;
    }
}

<?php

namespace Database\Seeders;

use App\Models\EventoPuerta;
use App\Models\Puerta;
use Illuminate\Database\Seeder;

class PuertaSeeder extends Seeder
{
    public function run(): void
    {
        $puerta = Puerta::firstOrCreate(
            ['nombre' => 'Puerta de maqueta'],
            ['activa' => true],
        );

        EventoPuerta::query()
            ->whereNull('puerta_id')
            ->update(['puerta_id' => $puerta->id]);
    }
}

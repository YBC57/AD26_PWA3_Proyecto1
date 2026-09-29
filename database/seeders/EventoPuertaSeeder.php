<?php

namespace Database\Seeders;

use App\Models\EventoPuerta;
use Illuminate\Database\Seeder;

class EventoPuertaSeeder extends Seeder
{
    /**
     * Seed representative door and sensor events.
     */
    public function run(): void
    {
        EventoPuerta::factory()->count(5)->abierta()->create();
        EventoPuerta::factory()->count(5)->cerradaDesbloqueada()->create();
        EventoPuerta::factory()->count(5)->cerradaBloqueada()->create();
        EventoPuerta::factory()->count(4)->candadoDesactivado()->create();
        EventoPuerta::factory()->count(4)->vibracionSuave()->create();
        EventoPuerta::factory()->count(4)->vibracionFuerte()->create();
        EventoPuerta::factory()->count(3)->intentoForzado()->create();
    }
}

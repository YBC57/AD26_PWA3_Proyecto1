<?php

namespace Tests\Feature;

use App\Models\EventoPuerta;
use App\Models\Puerta;
use App\Models\User;
use Database\Seeders\PuertaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventoPuertaTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_history_paginates_ten_records_and_uses_global_door_statistics(): void
    {
        $puerta = Puerta::query()->where('nombre', 'Puerta de maqueta')->firstOrFail();

        EventoPuerta::factory()->count(5)->for($puerta)->abierta()->create();
        EventoPuerta::factory()->count(5)->for($puerta)->cerradaDesbloqueada()->create();
        EventoPuerta::factory()->count(4)->for($puerta)->candadoDesactivado()->create();
        EventoPuerta::factory()->count(5)->for($puerta)->cerradaBloqueada()->create();
        EventoPuerta::factory()->count(4)->for($puerta)->vibracionSuave()->create();
        EventoPuerta::factory()->count(4)->for($puerta)->vibracionFuerte()->create();
        EventoPuerta::factory()->count(3)->for($puerta)->intentoForzado()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('eventos_puerta.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('puertaSeleccionada.id', $puerta->id)
                ->where('estadisticas.total', 30)
                ->where('estadisticas.abiertas', 5)
                ->where('estadisticas.cerradas_sin_seguro', 9)
                ->where('estadisticas.cerradas_con_seguro', 16)
                ->where('estadisticas.llamados', 4)
                ->where('estadisticas.posibles_forzamientos', 3)
                ->has('eventos.data', 10)
                ->where('eventos.current_page', 1)
                ->where('eventos.last_page', 3)
                ->where('eventos.total', 30));
    }

    public function test_history_and_statistics_are_filtered_to_the_selected_door(): void
    {
        $puertaPrincipal = Puerta::query()->where('nombre', 'Puerta de maqueta')->firstOrFail();
        $otraPuerta = Puerta::query()->create(['nombre' => 'Puerta secundaria']);

        EventoPuerta::factory()->count(15)->for($puertaPrincipal)->abierta()->create();
        EventoPuerta::factory()->count(12)->for($otraPuerta)->cerradaBloqueada()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('eventos_puerta.index', ['puerta_id' => $otraPuerta->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('puertaSeleccionada.id', $otraPuerta->id)
                ->where('estadisticas.total', 12)
                ->where('estadisticas.abiertas', 0)
                ->where('estadisticas.cerradas_con_seguro', 12)
                ->has('eventos.data', 10)
                ->where('eventos.total', 12)
                ->where('eventos.last_page', 2));

        $this->get(route('eventos_puerta.index', [
            'puerta_id' => $otraPuerta->id,
            'page' => 2,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('puertaSeleccionada.id', $otraPuerta->id)
                ->where('eventos.current_page', 2)
                ->where('eventos.total', 12)
                ->has('eventos.data', 2));
    }

    public function test_door_seeder_is_idempotent_and_preserves_legacy_event_associations(): void
    {
        $puertaOriginal = Puerta::query()->where('nombre', 'Puerta de maqueta')->firstOrFail();
        $evento = EventoPuerta::factory()
            ->for($puertaOriginal)
            ->abierta()
            ->create();

        app(PuertaSeeder::class)->run();
        app(PuertaSeeder::class)->run();

        $this->assertSame(3, Puerta::query()->count());
        $this->assertDatabaseHas('puertas', [
            'id' => $puertaOriginal->id,
            'nombre' => 'Puerta de prueba 1',
            'tipo' => 'prueba',
        ]);
        $this->assertDatabaseHas('puertas', [
            'nombre' => 'Puerta principal',
            'tipo' => 'real',
        ]);
        $this->assertDatabaseHas('puertas', [
            'nombre' => 'Puerta de prueba 2',
            'tipo' => 'prueba',
        ]);
        $this->assertDatabaseHas('eventos_puerta', [
            'id' => $evento->id,
            'puerta_id' => $puertaOriginal->id,
        ]);
    }

    public function test_door_seeder_stops_if_legacy_and_canonical_test_door_both_exist(): void
    {
        Puerta::query()->create([
            'nombre' => 'Puerta de prueba 1',
            'tipo' => 'prueba',
        ]);

        try {
            app(PuertaSeeder::class)->run();
            $this->fail('Se esperaba un conflicto de nombres.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('resuelve el conflicto', $exception->getMessage());
        }

        $this->assertSame(2, Puerta::query()->count());
        $this->assertDatabaseHas('puertas', [
            'nombre' => 'Puerta de maqueta',
        ]);
        $this->assertDatabaseMissing('puertas', [
            'nombre' => 'Puerta principal',
        ]);
    }
}

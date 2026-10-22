<?php

namespace Tests\Feature;

use App\Models\EventoPuerta;
use App\Models\Puerta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_dashboard_receives_paginated_events_and_preserves_nullable_values(): void
    {
        $puerta = Puerta::query()->where('nombre', 'Puerta de maqueta')->firstOrFail();
        $evento = EventoPuerta::factory()
            ->for($puerta)
            ->abierta()
            ->create(['cantidad_vibracion' => null]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('puertaSeleccionada.id', $puerta->id)
                ->where('estadisticas.total', 1)
                ->where('eventos.total', 1)
                ->where('eventos.data.0.id', $evento->id)
                ->where('eventos.data.0.cantidad_vibracion', null));
    }
}

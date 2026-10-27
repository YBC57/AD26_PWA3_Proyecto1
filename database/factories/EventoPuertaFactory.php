<?php

namespace Database\Factories;

use App\Models\EventoPuerta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventoPuerta>
 */
class EventoPuertaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $escenario = fake()->randomElement([
            [
                'estado_puerta' => 'abierta',
                'estado_candado' => 'desbloqueado',
                'tipo_evento' => 'puerta_abierta',
                'vibracion_detectada' => false,
                'cantidad_vibracion' => 0,
            ],
            [
                'estado_puerta' => 'cerrada',
                'estado_candado' => 'desbloqueado',
                'tipo_evento' => 'puerta_cerrada',
                'vibracion_detectada' => false,
                'cantidad_vibracion' => 0,
            ],
            [
                'estado_puerta' => 'cerrada',
                'estado_candado' => 'bloqueado',
                'tipo_evento' => 'candado_activado',
                'vibracion_detectada' => false,
                'cantidad_vibracion' => 0,
            ],
            [
                'estado_puerta' => 'cerrada',
                'estado_candado' => 'desbloqueado',
                'tipo_evento' => 'candado_desactivado',
                'vibracion_detectada' => false,
                'cantidad_vibracion' => 0,
            ],
            [
                'estado_puerta' => 'cerrada',
                'estado_candado' => 'bloqueado',
                'tipo_evento' => fake()->randomElement(['vibracion_suave', 'vibracion_fuerte']),
                'vibracion_detectada' => true,
                'cantidad_vibracion' => fake()->numberBetween(1, 8),
            ],
            [
                'estado_puerta' => 'cerrada',
                'estado_candado' => 'bloqueado',
                'tipo_evento' => 'intento_forzado',
                'vibracion_detectada' => true,
                'cantidad_vibracion' => fake()->numberBetween(2, 10),
            ],
        ]);

        return $escenario + [
            'detectado_en' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function abierta(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado_puerta' => 'abierta',
            'estado_candado' => 'desbloqueado',
            'tipo_evento' => 'puerta_abierta',
            'vibracion_detectada' => false,
            'cantidad_vibracion' => 0,
        ]);
    }

    public function cerradaDesbloqueada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado_puerta' => 'cerrada',
            'estado_candado' => 'desbloqueado',
            'tipo_evento' => 'puerta_cerrada',
            'vibracion_detectada' => false,
            'cantidad_vibracion' => 0,
        ]);
    }

    public function cerradaBloqueada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado_puerta' => 'cerrada',
            'estado_candado' => 'bloqueado',
            'tipo_evento' => 'candado_activado',
            'vibracion_detectada' => false,
            'cantidad_vibracion' => 0,
        ]);
    }

    public function candadoDesactivado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado_puerta' => 'cerrada',
            'estado_candado' => 'desbloqueado',
            'tipo_evento' => 'candado_desactivado',
            'vibracion_detectada' => false,
            'cantidad_vibracion' => 0,
        ]);
    }

    public function vibracionSuave(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado_puerta' => 'cerrada',
            'estado_candado' => 'bloqueado',
            'tipo_evento' => 'vibracion_suave',
            'vibracion_detectada' => true,
            'cantidad_vibracion' => fake()->numberBetween(1, 3),
        ]);
    }

    public function vibracionFuerte(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado_puerta' => 'cerrada',
            'estado_candado' => 'bloqueado',
            'tipo_evento' => 'vibracion_fuerte',
            'vibracion_detectada' => true,
            'cantidad_vibracion' => fake()->numberBetween(4, 8),
        ]);
    }

    public function intentoForzado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado_puerta' => 'cerrada',
            'estado_candado' => 'bloqueado',
            'tipo_evento' => 'intento_forzado',
            'vibracion_detectada' => true,
            'cantidad_vibracion' => fake()->numberBetween(2, 10),
        ]);
    }
}

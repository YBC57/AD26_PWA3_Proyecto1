<?php

namespace Database\Factories;

use App\Models\DoorEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoorEvent>
 */
class DoorEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $scenario = fake()->randomElement([
            [
                'door_state' => 'open',
                'lock_state' => 'unlocked',
                'event_type' => 'door_opened',
                'vibration_detected' => false,
                'vibration_count' => 0,
            ],
            [
                'door_state' => 'closed',
                'lock_state' => 'unlocked',
                'event_type' => 'door_closed',
                'vibration_detected' => false,
                'vibration_count' => 0,
            ],
            [
                'door_state' => 'closed',
                'lock_state' => 'locked',
                'event_type' => 'lock_activated',
                'vibration_detected' => false,
                'vibration_count' => 0,
            ],
            [
                'door_state' => 'closed',
                'lock_state' => 'unlocked',
                'event_type' => 'lock_deactivated',
                'vibration_detected' => false,
                'vibration_count' => 0,
            ],
            [
                'door_state' => 'closed',
                'lock_state' => 'locked',
                'event_type' => fake()->randomElement(['soft_vibration', 'strong_vibration']),
                'vibration_detected' => true,
                'vibration_count' => fake()->numberBetween(1, 8),
            ],
            [
                'door_state' => 'closed',
                'lock_state' => 'locked',
                'event_type' => 'forced_attempt',
                'vibration_detected' => true,
                'vibration_count' => fake()->numberBetween(2, 10),
            ],
        ]);

        return $scenario + [
            'detected_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'door_state' => 'open',
            'lock_state' => 'unlocked',
            'event_type' => 'door_opened',
            'vibration_detected' => false,
            'vibration_count' => 0,
        ]);
    }

    public function closedUnlocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'door_state' => 'closed',
            'lock_state' => 'unlocked',
            'event_type' => 'door_closed',
            'vibration_detected' => false,
            'vibration_count' => 0,
        ]);
    }

    public function closedLocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'door_state' => 'closed',
            'lock_state' => 'locked',
            'event_type' => 'lock_activated',
            'vibration_detected' => false,
            'vibration_count' => 0,
        ]);
    }

    public function lockDeactivated(): static
    {
        return $this->state(fn (array $attributes) => [
            'door_state' => 'closed',
            'lock_state' => 'unlocked',
            'event_type' => 'lock_deactivated',
            'vibration_detected' => false,
            'vibration_count' => 0,
        ]);
    }

    public function softVibration(): static
    {
        return $this->state(fn (array $attributes) => [
            'door_state' => 'closed',
            'lock_state' => 'locked',
            'event_type' => 'soft_vibration',
            'vibration_detected' => true,
            'vibration_count' => fake()->numberBetween(1, 3),
        ]);
    }

    public function strongVibration(): static
    {
        return $this->state(fn (array $attributes) => [
            'door_state' => 'closed',
            'lock_state' => 'locked',
            'event_type' => 'strong_vibration',
            'vibration_detected' => true,
            'vibration_count' => fake()->numberBetween(4, 8),
        ]);
    }

    public function forcedAttempt(): static
    {
        return $this->state(fn (array $attributes) => [
            'door_state' => 'closed',
            'lock_state' => 'locked',
            'event_type' => 'forced_attempt',
            'vibration_detected' => true,
            'vibration_count' => fake()->numberBetween(2, 10),
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\DoorEvent;
use Illuminate\Database\Seeder;

class DoorEventSeeder extends Seeder
{
    /**
     * Seed representative door and sensor events.
     */
    public function run(): void
    {
        DoorEvent::factory()->count(5)->open()->create();
        DoorEvent::factory()->count(5)->closedUnlocked()->create();
        DoorEvent::factory()->count(5)->closedLocked()->create();
        DoorEvent::factory()->count(4)->lockDeactivated()->create();
        DoorEvent::factory()->count(4)->softVibration()->create();
        DoorEvent::factory()->count(4)->strongVibration()->create();
        DoorEvent::factory()->count(3)->forcedAttempt()->create();
    }
}

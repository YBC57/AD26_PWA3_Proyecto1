<?php

namespace App\Models;

use Database\Factories\DoorEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $door_state
 * @property string $lock_state
 * @property bool $vibration_detected
 * @property int $vibration_count
 * @property string $event_type
 * @property \Illuminate\Support\Carbon $detected_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class DoorEvent extends Model
{
    /** @use HasFactory<DoorEventFactory> */
    use HasFactory;

    protected $fillable = [
        'door_state',
        'lock_state',
        'vibration_detected',
        'vibration_count',
        'event_type',
        'detected_at',
    ];

    protected $casts = [
        'vibration_detected' => 'boolean',
        'vibration_count' => 'integer',
        'detected_at' => 'datetime',
    ];
}

<?php

namespace App\Models;

use Database\Factories\EventoPuertaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $estado_puerta
 * @property string $estado_candado
 * @property bool $vibracion_detectada
 * @property int $cantidad_vibracion
 * @property string $tipo_evento
 * @property Carbon $detectado_en
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EventoPuerta extends Model
{
    /** @use HasFactory<EventoPuertaFactory> */
    use HasFactory;

    protected $table = 'eventos_puerta';

    protected $fillable = [
        'estado_puerta',
        'estado_candado',
        'vibracion_detectada',
        'cantidad_vibracion',
        'tipo_evento',
        'detectado_en',
    ];

    protected $casts = [
        'vibracion_detectada' => 'boolean',
        'cantidad_vibracion' => 'integer',
        'detectado_en' => 'datetime',
    ];
}

<?php

namespace App\Models;

use Database\Factories\EventoPuertaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $puerta_id
 * @property string|null $estado_puerta
 * @property string|null $estado_candado
 * @property bool $vibracion_detectada
 * @property int|null $cantidad_vibracion
 * @property string $tipo_evento
 * @property string $origen
 * @property int|null $dispositivo_iot_id
 * @property string|null $id_evento_dispositivo
 * @property string|null $hash_payload
 * @property string|null $distancia_pasador_cm
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
        'puerta_id',
        'estado_puerta',
        'estado_candado',
        'vibracion_detectada',
        'cantidad_vibracion',
        'tipo_evento',
        'origen',
        'dispositivo_iot_id',
        'id_evento_dispositivo',
        'hash_payload',
        'distancia_pasador_cm',
        'detectado_en',
    ];

    protected $casts = [
        'vibracion_detectada' => 'boolean',
        'cantidad_vibracion' => 'integer',
        'detectado_en' => 'datetime',
        'distancia_pasador_cm' => 'decimal:2',
    ];

    /** @return BelongsTo<Puerta, $this> */
    public function puerta(): BelongsTo
    {
        return $this->belongsTo(Puerta::class);
    }

    /** @return BelongsTo<DispositivoIot, $this> */
    public function dispositivoIot(): BelongsTo
    {
        return $this->belongsTo(DispositivoIot::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @use HasApiTokens<PersonalAccessToken>
 *
 * @property int $id
 * @property int $puerta_id
 * @property string $nombre
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DispositivoIot extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'dispositivos_iot';

    protected $fillable = [
        'puerta_id',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /** @return BelongsTo<Puerta, $this> */
    public function puerta(): BelongsTo
    {
        return $this->belongsTo(Puerta::class);
    }

    /** @return HasMany<EventoPuerta, $this> */
    public function eventos(): HasMany
    {
        return $this->hasMany(EventoPuerta::class, 'dispositivo_iot_id');
    }
}

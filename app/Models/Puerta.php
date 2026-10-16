<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Puerta extends Model
{
    protected $fillable = [
        'nombre',
        'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    /** @return HasMany<EventoPuerta, $this> */
    public function eventos(): HasMany
    {
        return $this->hasMany(EventoPuerta::class);
    }
}

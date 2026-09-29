<?php

namespace App\Http\Controllers;

use App\Models\EventoPuerta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventoPuertaController extends Controller
{
    /**
     * Listado de eventos de puerta ordenados por fecha más reciente.
     */
    public function index(): Response
    {
        $eventos = EventoPuerta::query()
            ->orderByDesc('detectado_en')
            ->orderByDesc('id')
            ->paginate(10)
            ->through(fn (EventoPuerta $evento) => [
                'id' => $evento->id,
                'estado_puerta' => $evento->estado_puerta,
                'estado_candado' => $evento->estado_candado,
                'vibracion_detectada' => $evento->vibracion_detectada,
                'cantidad_vibracion' => $evento->cantidad_vibracion,
                'tipo_evento' => $evento->tipo_evento,
                'detectado_en' => $evento->detectado_en?->toISOString(),
                'created_at' => $evento->created_at?->toISOString(),
            ]);

        return Inertia::render('Dashboard', [
            'eventos' => $eventos,
            'titulo' => 'Eventos de puerta',
        ]);
    }

    /**
     * Muestra un evento individual del monitoreo de la puerta.
     */
    public function show(EventoPuerta $eventoPuerta): Response
    {
        return Inertia::render('Dashboard', [
            'evento' => [
                'id' => $eventoPuerta->id,
                'estado_puerta' => $eventoPuerta->estado_puerta,
                'estado_candado' => $eventoPuerta->estado_candado,
                'vibracion_detectada' => $eventoPuerta->vibracion_detectada,
                'cantidad_vibracion' => $eventoPuerta->cantidad_vibracion,
                'tipo_evento' => $eventoPuerta->tipo_evento,
                'detectado_en' => $eventoPuerta->detectado_en?->toISOString(),
                'created_at' => $eventoPuerta->created_at?->toISOString(),
            ],
            'eventos' => EventoPuerta::query()
                ->orderByDesc('detectado_en')
                ->limit(10)
                ->get()
                ->map(fn (EventoPuerta $evento) => [
                    'id' => $evento->id,
                    'estado_puerta' => $evento->estado_puerta,
                    'estado_candado' => $evento->estado_candado,
                    'vibracion_detectada' => $evento->vibracion_detectada,
                    'cantidad_vibracion' => $evento->cantidad_vibracion,
                    'tipo_evento' => $evento->tipo_evento,
                    'detectado_en' => $evento->detectado_en?->toISOString(),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Registra un evento de prueba con la estructura real del modelo.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'estado_puerta' => ['required', 'string', 'in:abierta,cerrada'],
            'estado_candado' => ['required', 'string', 'in:bloqueado,desbloqueado'],
            'vibracion_detectada' => ['required', 'boolean'],
            'cantidad_vibracion' => ['nullable', 'integer', 'min:0'],
            'tipo_evento' => ['required', 'string', 'max:50'],
            'detectado_en' => ['required', 'date'],
        ]);

        $evento = EventoPuerta::create($validated);

        return redirect()->route('eventos_puerta.show', ['eventoPuerta' => $evento]);
    }
}

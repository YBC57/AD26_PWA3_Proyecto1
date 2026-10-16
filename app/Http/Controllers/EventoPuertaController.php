<?php

namespace App\Http\Controllers;

use App\Models\EventoPuerta;
use App\Models\Puerta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventoPuertaController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'puerta_id' => ['nullable', 'integer', 'exists:puertas,id'],
        ]);

        $puerta = isset($validated['puerta_id'])
            ? Puerta::query()->whereKey($validated['puerta_id'])->firstOrFail()
            : Puerta::query()->orderByDesc('activa')->orderBy('id')->firstOrFail();

        return $this->dashboard($puerta);
    }

    public function show(EventoPuerta $eventoPuerta): Response
    {
        return $this->dashboard($eventoPuerta->puerta()->firstOrFail(), $eventoPuerta);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'puerta_id' => ['nullable', 'integer', 'exists:puertas,id'],
            'estado_puerta' => ['required', 'string', 'in:abierta,cerrada'],
            'estado_candado' => ['required', 'string', 'in:bloqueado,desbloqueado'],
            'vibracion_detectada' => ['required', 'boolean'],
            'cantidad_vibracion' => ['nullable', 'integer', 'min:0'],
            'tipo_evento' => ['required', 'string', 'max:50'],
            'detectado_en' => ['required', 'date'],
        ]);

        $puerta = isset($validated['puerta_id'])
            ? Puerta::query()->whereKey($validated['puerta_id'])->firstOrFail()
            : Puerta::query()->orderByDesc('activa')->orderBy('id')->firstOrFail();

        $evento = EventoPuerta::create([
            ...$validated,
            'puerta_id' => $puerta->id,
        ]);

        return redirect()->route('eventos_puerta.show', ['eventoPuerta' => $evento]);
    }

    private function dashboard(Puerta $puerta, ?EventoPuerta $evento = null): Response
    {
        $consultaEventos = $puerta->eventos();

        $eventos = (clone $consultaEventos)
            ->orderByDesc('detectado_en')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (EventoPuerta $registro) => $this->datosEvento($registro));

        $atributosEstadisticas = (clone $consultaEventos)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN estado_puerta = 'abierta' THEN 1 ELSE 0 END) as abiertas")
            ->selectRaw("SUM(CASE WHEN estado_puerta = 'cerrada' AND estado_candado = 'desbloqueado' THEN 1 ELSE 0 END) as cerradas_sin_seguro")
            ->selectRaw("SUM(CASE WHEN estado_puerta = 'cerrada' AND estado_candado = 'bloqueado' THEN 1 ELSE 0 END) as cerradas_con_seguro")
            ->selectRaw('SUM(CASE WHEN vibracion_detectada = 1 THEN 1 ELSE 0 END) as vibraciones')
            ->selectRaw("SUM(CASE WHEN tipo_evento = 'vibracion_suave' OR tipo_evento = 'llamado_puerta' THEN 1 ELSE 0 END) as llamados")
            ->selectRaw("SUM(CASE WHEN tipo_evento = 'intento_forzado' OR tipo_evento = 'posible_forzamiento' THEN 1 ELSE 0 END) as posibles_forzamientos")
            ->selectRaw("SUM(CASE WHEN tipo_evento = 'lectura_anomala' THEN 1 ELSE 0 END) as lecturas_anomalas")
            ->first()
            ?->getAttributes() ?? [];

        $ultimoEvento = (clone $consultaEventos)
            ->orderByDesc('detectado_en')
            ->orderByDesc('id')
            ->first();

        return Inertia::render('Dashboard', [
            'eventos' => $eventos,
            'titulo' => 'Eventos de puerta',
            'puertas' => Puerta::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'activa']),
            'puertaSeleccionada' => [
                'id' => $puerta->id,
                'nombre' => $puerta->nombre,
                'activa' => $puerta->activa,
            ],
            'estadisticas' => [
                'total' => (int) ($atributosEstadisticas['total'] ?? 0),
                'abiertas' => (int) ($atributosEstadisticas['abiertas'] ?? 0),
                'cerradas_sin_seguro' => (int) ($atributosEstadisticas['cerradas_sin_seguro'] ?? 0),
                'cerradas_con_seguro' => (int) ($atributosEstadisticas['cerradas_con_seguro'] ?? 0),
                'vibraciones' => (int) ($atributosEstadisticas['vibraciones'] ?? 0),
                'llamados' => (int) ($atributosEstadisticas['llamados'] ?? 0),
                'posibles_forzamientos' => (int) ($atributosEstadisticas['posibles_forzamientos'] ?? 0),
                'lecturas_anomalas' => (int) ($atributosEstadisticas['lecturas_anomalas'] ?? 0),
            ],
            'ultimoEvento' => $ultimoEvento ? $this->datosEvento($ultimoEvento) : null,
            'evento' => $evento ? $this->datosEvento($evento) : null,
        ]);
    }

    /**
     * @return array{
     *     id: int,
     *     estado_puerta: string,
     *     estado_candado: string,
     *     vibracion_detectada: bool,
     *     cantidad_vibracion: int,
     *     tipo_evento: string,
     *     detectado_en: string,
     *     created_at: string|null
     * }
     */
    private function datosEvento(EventoPuerta $evento): array
    {
        return [
            'id' => $evento->id,
            'estado_puerta' => $evento->estado_puerta,
            'estado_candado' => $evento->estado_candado,
            'vibracion_detectada' => $evento->vibracion_detectada,
            'cantidad_vibracion' => $evento->cantidad_vibracion,
            'tipo_evento' => $evento->tipo_evento,
            'detectado_en' => $evento->detectado_en->toISOString(),
            'created_at' => $evento->created_at?->toISOString(),
        ];
    }
}

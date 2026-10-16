<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEventoPuertaRequest;
use App\Models\DispositivoIot;
use App\Models\EventoPuerta;
use App\Models\Puerta;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use JsonException;

class EventoPuertaController extends Controller
{
    /**
     * @throws JsonException
     */
    public function store(StoreEventoPuertaRequest $request, Puerta $puerta): JsonResponse
    {
        $dispositivo = $request->attributes->get('dispositivo_iot');

        if (! $dispositivo instanceof DispositivoIot) {
            return response()->json(['message' => 'Dispositivo no autorizado.'], 403);
        }

        $validated = $request->validated();
        $detectadoEn = CarbonImmutable::parse($validated['detectado_en'])->utc();
        $distancia = array_key_exists('distancia_pasador_cm', $validated)
            && $validated['distancia_pasador_cm'] !== null
            ? number_format((float) $validated['distancia_pasador_cm'], 2, '.', '')
            : null;

        $payload = [
            'estado_puerta' => $validated['estado_puerta'] ?? null,
            'estado_candado' => $validated['estado_candado'] ?? null,
            'tipo_evento' => $validated['tipo_evento'],
            'vibracion_detectada' => $validated['vibracion_detectada'],
            'detectado_en' => $detectadoEn->format('Y-m-d\TH:i:sP'),
            'distancia_pasador_cm' => $distancia,
        ];

        $idDispositivo = strtolower($validated['id_evento_dispositivo']);
        $hashPayload = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use (
                $dispositivo,
                $puerta,
                $payload,
                $idDispositivo,
                $hashPayload,
                $detectadoEn,
                $distancia,
            ): JsonResponse {
                $existente = EventoPuerta::query()
                    ->where('dispositivo_iot_id', $dispositivo->id)
                    ->where('id_evento_dispositivo', $idDispositivo)
                    ->first();

                if ($existente) {
                    return $this->respuestaDuplicada($existente, $hashPayload);
                }

                $evento = $puerta->eventos()->create([
                    ...$payload,
                    'puerta_id' => $puerta->id,
                    'origen' => 'dispositivo_real',
                    'dispositivo_iot_id' => $dispositivo->id,
                    'id_evento_dispositivo' => $idDispositivo,
                    'hash_payload' => $hashPayload,
                    'distancia_pasador_cm' => $distancia,
                    'cantidad_vibracion' => null,
                    'detectado_en' => $detectadoEn,
                ]);

                return response()->json([
                    'data' => [
                        'id' => $evento->id,
                        'puerta_id' => $evento->puerta_id,
                        'tipo_evento' => $evento->tipo_evento,
                        'origen' => $evento->origen,
                        'detectado_en' => $evento->detectado_en->toISOString(),
                    ],
                ], 201);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existente = EventoPuerta::query()
                ->where('dispositivo_iot_id', $dispositivo->id)
                ->where('id_evento_dispositivo', $idDispositivo)
                ->first();

            if (! $existente) {
                throw $exception;
            }

            return $this->respuestaDuplicada($existente, $hashPayload);
        }
    }

    private function respuestaDuplicada(EventoPuerta $evento, string $hashPayload): JsonResponse
    {
        if (! hash_equals((string) $evento->hash_payload, $hashPayload)) {
            return response()->json([
                'message' => 'El identificador del evento ya fue usado con otro contenido.',
            ], 409);
        }

        return response()->json([
            'data' => [
                'id' => $evento->id,
                'puerta_id' => $evento->puerta_id,
                'tipo_evento' => $evento->tipo_evento,
                'origen' => $evento->origen,
                'detectado_en' => $evento->detectado_en->toISOString(),
            ],
            'duplicado' => true,
        ], 200);
    }
}

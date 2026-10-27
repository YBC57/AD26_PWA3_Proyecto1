<?php

namespace App\Http\Middleware;

use App\Models\DispositivoIot;
use App\Models\Puerta;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureDispositivoPuedeReportar
{
    public function handle(Request $request, Closure $next): Response
    {
        $principal = $request->user();
        $puertaEnRuta = $request->route('puerta');
        $puertaId = $puertaEnRuta instanceof Puerta ? $puertaEnRuta->getKey() : $puertaEnRuta;

        if (
            ! $principal
            || $principal->getMorphClass() !== (new DispositivoIot)->getMorphClass()
        ) {
            return response()->json([
                'message' => 'El dispositivo no está autorizado para esta puerta.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (! is_int($puertaId) && (! is_string($puertaId) || ! ctype_digit($puertaId))) {
            return response()->json(['message' => 'Puerta no encontrada.'], Response::HTTP_NOT_FOUND);
        }

        $puerta = Puerta::query()->whereKey($puertaId)->first();

        if (! $puerta) {
            return response()->json(['message' => 'Puerta no encontrada.'], Response::HTTP_NOT_FOUND);
        }

        $dispositivo = DispositivoIot::query()
            ->whereKey($principal->getAuthIdentifier())
            ->first();
        $accessToken = PersonalAccessToken::findToken((string) $request->bearerToken());

        if (
            ! $dispositivo
            || ! $dispositivo->activo
            || (int) $dispositivo->puerta_id !== $puerta->id
            || ! $puerta->activa
            || $puerta->tipo !== 'real'
            || ! $accessToken
            || $accessToken->tokenable_type !== $dispositivo->getMorphClass()
            || (int) $accessToken->tokenable_id !== $dispositivo->id
            || ! $accessToken->can('events:create')
        ) {
            return response()->json([
                'message' => 'El dispositivo no está autorizado para esta puerta.',
            ], Response::HTTP_FORBIDDEN);
        }

        $request->attributes->set('dispositivo_iot', $dispositivo);

        return $next($request);
    }
}

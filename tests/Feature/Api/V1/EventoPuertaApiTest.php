<?php

namespace Tests\Feature\Api\V1;

use App\Models\DispositivoIot;
use App\Models\EventoPuerta;
use App\Models\Puerta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventoPuertaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_without_a_device_token_are_rejected_as_json(): void
    {
        $puerta = $this->crearPuerta();

        $this->postJson($this->endpoint($puerta))
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_web_session_authentication_cannot_be_used_for_device_api(): void
    {
        $puerta = $this->crearPuerta();

        $this->actingAs(User::factory()->create())
            ->postJson($this->endpoint($puerta), $this->payload())
            ->assertForbidden()
            ->assertJsonStructure(['message']);
    }

    public function test_invalid_and_revoked_device_tokens_are_rejected(): void
    {
        $puerta = $this->crearPuerta();
        $dispositivo = $this->crearDispositivo($puerta);
        $token = $dispositivo->createToken('test', ['events:create']);
        $this->artisan('iot:revocar-token', [
            'dispositivo_id' => $dispositivo->id,
            'token_id' => $token->accessToken->id,
        ])->assertExitCode(0);

        $this->withToken('invalid-token')
            ->postJson($this->endpoint($puerta), $this->payload())
            ->assertUnauthorized();

        $this->withToken($token->plainTextToken)
            ->postJson($this->endpoint($puerta), $this->payload())
            ->assertUnauthorized();
    }

    public function test_device_cannot_report_for_another_door(): void
    {
        $puertaAutorizada = $this->crearPuerta('Puerta autorizada');
        $otraPuerta = $this->crearPuerta('Otra puerta');
        $dispositivo = $this->crearDispositivo($puertaAutorizada);
        $token = $dispositivo->createToken('test', ['events:create']);

        $this->withToken($token->plainTextToken)
            ->postJson($this->endpoint($otraPuerta), $this->payload())
            ->assertForbidden()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('eventos_puerta', 0);
    }

    public function test_inactive_device_cannot_report_events(): void
    {
        $puerta = $this->crearPuerta();
        $dispositivo = $this->crearDispositivo($puerta);
        $dispositivo->update(['activo' => false]);
        $token = $dispositivo->createToken('test', ['events:create']);

        $this->withToken($token->plainTextToken)
            ->postJson($this->endpoint($puerta), $this->payload())
            ->assertForbidden()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('eventos_puerta', 0);
    }

    public function test_inactive_door_cannot_receive_events(): void
    {
        $puerta = $this->crearPuerta();
        $puerta->update(['activa' => false]);
        $dispositivo = $this->crearDispositivo($puerta);
        $token = $dispositivo->createToken('test', ['events:create']);

        $this->withToken($token->plainTextToken)
            ->postJson($this->endpoint($puerta), $this->payload())
            ->assertForbidden()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('eventos_puerta', 0);
    }

    public function test_expired_device_token_cannot_report_events(): void
    {
        $puerta = $this->crearPuerta();
        $dispositivo = $this->crearDispositivo($puerta);
        $token = $dispositivo->createToken('test', ['events:create']);
        $token->accessToken->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->withToken($token->plainTextToken)
            ->postJson($this->endpoint($puerta), $this->payload())
            ->assertUnauthorized();

        $this->assertDatabaseCount('eventos_puerta', 0);
    }

    public function test_device_token_without_required_ability_cannot_report_events(): void
    {
        $puerta = $this->crearPuerta();
        $dispositivo = $this->crearDispositivo($puerta);
        $token = $dispositivo->createToken('test', []);

        $this->withToken($token->plainTextToken)
            ->postJson($this->endpoint($puerta), $this->payload())
            ->assertForbidden()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('eventos_puerta', 0);
    }

    public function test_device_associated_with_a_test_door_cannot_report_events(): void
    {
        $puertaPrueba = $this->crearPuerta('Puerta de prueba', 'prueba');
        $dispositivo = $this->crearDispositivo($puertaPrueba);
        $token = $dispositivo->createToken('test', ['events:create']);

        $this->withToken($token->plainTextToken)
            ->postJson($this->endpoint($puertaPrueba), $this->payload())
            ->assertForbidden()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('eventos_puerta', 0);
    }

    public function test_device_creation_rejects_test_door_without_creating_device_or_token(): void
    {
        $puertaPrueba = $this->crearPuerta('Puerta de prueba', 'prueba');

        $this->artisan('iot:crear-dispositivo', [
            'puerta_id' => $puertaPrueba->id,
            'nombre' => 'ESP8266 de prueba',
        ])->assertExitCode(1);

        $this->assertDatabaseCount('dispositivos_iot', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_device_creation_allows_active_real_door(): void
    {
        $puertaReal = $this->crearPuerta('Puerta principal', 'real');

        $this->artisan('iot:crear-dispositivo', [
            'puerta_id' => $puertaReal->id,
            'nombre' => 'ESP8266 principal',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('dispositivos_iot', [
            'puerta_id' => $puertaReal->id,
            'nombre' => 'ESP8266 principal',
            'activo' => true,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_invalid_or_unexpected_payload_fields_are_rejected(): void
    {
        $puerta = $this->crearPuerta();
        $dispositivo = $this->crearDispositivo($puerta);
        $token = $dispositivo->createToken('test', ['events:create']);
        $payload = $this->payload();
        $payload['puerta_id'] = 999;
        $payload['dispositivo_iot_id'] = 999;
        $payload['origen'] = 'prueba';
        $payload['vibracion_detectada'] = 'true';

        $this->withToken($token->plainTextToken)
            ->postJson($this->endpoint($puerta), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'puerta_id',
                'dispositivo_iot_id',
                'origen',
                'vibracion_detectada',
            ]);

        $this->assertDatabaseCount('eventos_puerta', 0);
    }

    public function test_historical_event_types_are_not_accepted_by_the_api_contract(): void
    {
        $puerta = $this->crearPuerta();
        $dispositivo = $this->crearDispositivo($puerta);
        $token = $dispositivo->createToken('test', ['events:create']);
        $payload = $this->payload();
        $payload['tipo_evento'] = 'vibracion_fuerte';

        $this->withToken($token->plainTextToken)
            ->postJson($this->endpoint($puerta), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo_evento']);

        $this->assertDatabaseCount('eventos_puerta', 0);
    }

    public function test_existing_api_event_types_remain_accepted(): void
    {
        $puerta = $this->crearPuerta();
        $dispositivo = $this->crearDispositivo($puerta);
        $token = $dispositivo->createToken('test', ['events:create']);
        $eventos = [
            [
                'id_evento_dispositivo' => '00000000-0000-4000-8000-000000000001',
                'estado_puerta' => 'abierta',
                'estado_candado' => null,
                'tipo_evento' => 'puerta_abierta',
                'vibracion_detectada' => false,
            ],
            [
                'id_evento_dispositivo' => '00000000-0000-4000-8000-000000000002',
                'estado_puerta' => 'cerrada',
                'estado_candado' => 'desbloqueado',
                'tipo_evento' => 'puerta_cerrada_sin_seguro',
                'vibracion_detectada' => false,
            ],
            [
                'id_evento_dispositivo' => '00000000-0000-4000-8000-000000000003',
                'estado_puerta' => 'cerrada',
                'estado_candado' => 'bloqueado',
                'tipo_evento' => 'puerta_cerrada_con_seguro',
                'vibracion_detectada' => false,
            ],
            [
                'id_evento_dispositivo' => '00000000-0000-4000-8000-000000000004',
                'estado_puerta' => null,
                'estado_candado' => null,
                'tipo_evento' => 'llamado_puerta',
                'vibracion_detectada' => true,
            ],
            [
                'id_evento_dispositivo' => '00000000-0000-4000-8000-000000000005',
                'estado_puerta' => null,
                'estado_candado' => null,
                'tipo_evento' => 'posible_forzamiento',
                'vibracion_detectada' => true,
            ],
            [
                'id_evento_dispositivo' => '00000000-0000-4000-8000-000000000006',
                'estado_puerta' => null,
                'estado_candado' => null,
                'tipo_evento' => 'lectura_anomala',
                'vibracion_detectada' => false,
            ],
        ];

        foreach ($eventos as $evento) {
            $this->withToken($token->plainTextToken)
                ->postJson($this->endpoint($puerta), [
                    ...$this->payload(),
                    ...$evento,
                ])
                ->assertCreated()
                ->assertJsonPath('data.tipo_evento', $evento['tipo_evento']);
        }

        $this->assertDatabaseCount('eventos_puerta', count($eventos));
    }

    public function test_valid_event_is_stored_as_device_origin_without_sensor_fabrication(): void
    {
        $puerta = $this->crearPuerta();
        $dispositivo = $this->crearDispositivo($puerta);
        $token = $dispositivo->createToken('test', ['events:create']);
        $payload = $this->payload();
        $payload['vibracion_detectada'] = true;

        $this->withToken($token->plainTextToken)
            ->postJson($this->endpoint($puerta), $payload)
            ->assertCreated()
            ->assertJsonPath('data.puerta_id', $puerta->id)
            ->assertJsonPath('data.tipo_evento', 'lectura_anomala')
            ->assertJsonPath('data.origen', 'dispositivo_real')
            ->assertJsonStructure(['data' => ['id', 'puerta_id', 'tipo_evento', 'origen', 'detectado_en']]);

        $this->assertDatabaseHas('eventos_puerta', [
            'puerta_id' => $puerta->id,
            'dispositivo_iot_id' => $dispositivo->id,
            'id_evento_dispositivo' => $payload['id_evento_dispositivo'],
            'tipo_evento' => 'lectura_anomala',
            'estado_puerta' => null,
            'estado_candado' => null,
            'vibracion_detectada' => true,
            'origen' => 'dispositivo_real',
            'distancia_pasador_cm' => null,
            'cantidad_vibracion' => null,
        ]);

        $this->assertDatabaseCount('eventos_puerta', 1);
    }

    public function test_unclassified_vibration_requires_detected_vibration(): void
    {
        $puerta = $this->crearPuerta();
        $dispositivo = $this->crearDispositivo($puerta);
        $token = $dispositivo->createToken('test', ['events:create']);
        $payload = [
            ...$this->payload(),
            'tipo_evento' => 'vibracion_detectada',
            'vibracion_detectada' => false,
        ];

        $this->withToken($token->plainTextToken)
            ->postJson($this->endpoint($puerta), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vibracion_detectada']);

        $this->assertDatabaseCount('eventos_puerta', 0);
    }

    public function test_api_event_appears_first_in_the_authenticated_web_history(): void
    {
        $puerta = $this->crearPuerta();
        $dispositivo = $this->crearDispositivo($puerta);
        $token = $dispositivo->createToken('test', ['events:create']);

        EventoPuerta::factory()
            ->for($puerta)
            ->abierta()
            ->create([
                'origen' => 'prueba',
                'detectado_en' => now()->subDay(),
            ]);

        $payload = $this->payload();
        $payload['detectado_en'] = now()->subMinute()->format('Y-m-d\TH:i:sP');
        $payload['tipo_evento'] = 'vibracion_detectada';
        $payload['vibracion_detectada'] = true;

        $apiResponse = $this->withToken($token->plainTextToken)
            ->postJson($this->endpoint($puerta), $payload)
            ->assertCreated()
            ->assertJsonPath('data.puerta_id', $puerta->id)
            ->assertJsonPath('data.tipo_evento', 'vibracion_detectada')
            ->assertJsonPath('data.origen', 'dispositivo_real');

        $eventoId = $apiResponse->json('data.id');
        $fechaEsperada = now()->parse($payload['detectado_en'])->utc()->toISOString();

        $this->assertDatabaseHas('eventos_puerta', [
            'id' => $eventoId,
            'puerta_id' => $puerta->id,
            'dispositivo_iot_id' => $dispositivo->id,
            'tipo_evento' => 'vibracion_detectada',
            'estado_puerta' => null,
            'estado_candado' => null,
            'vibracion_detectada' => true,
            'cantidad_vibracion' => null,
            'origen' => 'dispositivo_real',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('eventos_puerta.index', ['puerta_id' => $puerta->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('puertaSeleccionada.id', $puerta->id)
                ->where('estadisticas.total', 2)
                ->where('estadisticas.vibraciones', 1)
                ->where('estadisticas.vibraciones_sin_clasificar', 1)
                ->where('eventos.total', 2)
                ->where('eventos.data.0.id', $eventoId)
                ->where('eventos.data.0.tipo_evento', 'vibracion_detectada')
                ->where('eventos.data.0.estado_puerta', null)
                ->where('eventos.data.0.estado_candado', null)
                ->where('eventos.data.0.detectado_en', $fechaEsperada)
                ->where('ultimoEvento.id', $eventoId));
    }

    public function test_api_converts_an_offset_timestamp_to_the_same_utc_instant(): void
    {
        $puerta = $this->crearPuerta();
        $dispositivo = $this->crearDispositivo($puerta);
        $token = $dispositivo->createToken('test', ['events:create']);
        $payload = [
            ...$this->payload(),
            'detectado_en' => '2026-10-15T18:30:00-06:00',
        ];
        $instanteUtcEsperado = '2026-10-16T00:30:00.000000Z';

        $response = $this->withToken($token->plainTextToken)
            ->postJson($this->endpoint($puerta), $payload)
            ->assertCreated()
            ->assertJsonPath('data.detectado_en', $instanteUtcEsperado);

        $evento = EventoPuerta::query()->findOrFail($response->json('data.id'));

        $this->assertSame($instanteUtcEsperado, $evento->detectado_en->toISOString());
        $this->assertSame('UTC', $evento->detectado_en->timezoneName);
        $this->assertSame('UTC', $evento->created_at->timezoneName);
        $this->assertSame('2026-10-16 00:30:00', $evento->getRawOriginal('detectado_en'));

        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() === 'mysql') {
            $timezone = \Illuminate\Support\Facades\DB::selectOne(
                'SELECT @@session.time_zone AS timezone',
            );
            $epoch = \Illuminate\Support\Facades\DB::selectOne(
                'SELECT UNIX_TIMESTAMP(detectado_en) AS epoch FROM eventos_puerta WHERE id = ?',
                [$evento->id],
            );

            $this->assertSame('+00:00', $timezone->timezone);
            $this->assertSame(
                \Carbon\CarbonImmutable::parse($payload['detectado_en'])->timestamp,
                (int) $epoch->epoch,
            );
        }

        $this->assertDatabaseCount('eventos_puerta', 1);
    }

    public function test_retransmissions_are_idempotent_and_key_reuse_with_other_payload_conflicts(): void
    {
        $puerta = $this->crearPuerta();
        $dispositivo = $this->crearDispositivo($puerta);
        $token = $dispositivo->createToken('test', ['events:create']);
        $payload = $this->payload();

        $firstResponse = $this->withToken($token->plainTextToken)
            ->postJson($this->endpoint($puerta), $payload)
            ->assertCreated();
        $eventoId = $firstResponse->json('data.id');

        $this->postJson($this->endpoint($puerta), $payload)
            ->assertOk()
            ->assertJsonPath('duplicado', true)
            ->assertJsonPath('data.id', $eventoId);

        $differentPayload = [...$payload, 'vibracion_detectada' => true];

        $this->postJson($this->endpoint($puerta), $differentPayload)
            ->assertStatus(409)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('eventos_puerta', 1);
    }

    public function test_api_rate_limit_returns_too_many_requests_after_sixty_requests(): void
    {
        $puerta = $this->crearPuerta();
        $dispositivo = $this->crearDispositivo($puerta);
        $token = $dispositivo->createToken('test', ['events:create']);
        $payload = $this->payload();

        $this->withToken($token->plainTextToken);

        for ($request = 1; $request <= 60; $request++) {
            $response = $this->postJson($this->endpoint($puerta), $payload);

            $this->assertSame($request === 1 ? 201 : 200, $response->status());
        }

        $this->postJson($this->endpoint($puerta), $payload)
            ->assertTooManyRequests()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('eventos_puerta', 1);
    }

    public function test_web_dashboard_remains_available_to_authenticated_users(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk();
    }

    private function crearPuerta(string $nombre = 'Puerta de prueba', string $tipo = 'real'): Puerta
    {
        return Puerta::query()->create([
            'nombre' => $nombre,
            'tipo' => $tipo,
        ]);
    }

    private function crearDispositivo(Puerta $puerta): DispositivoIot
    {
        return DispositivoIot::query()->create([
            'puerta_id' => $puerta->id,
            'nombre' => 'ESP8266 de prueba',
            'activo' => true,
        ]);
    }

    private function endpoint(Puerta $puerta): string
    {
        return "/api/v1/puertas/{$puerta->id}/eventos";
    }

    /**
     * @return array<string, bool|string|null>
     */
    private function payload(): array
    {
        return [
            'id_evento_dispositivo' => '8b28c196-e61d-4bf5-b287-93c981d24c46',
            'estado_puerta' => null,
            'estado_candado' => null,
            'tipo_evento' => 'lectura_anomala',
            'vibracion_detectada' => false,
            'detectado_en' => '2026-10-15T18:30:00-06:00',
            'distancia_pasador_cm' => null,
        ];
    }
}

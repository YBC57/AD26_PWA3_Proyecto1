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

    public function test_invalid_or_unexpected_payload_fields_are_rejected(): void
    {
        $puerta = $this->crearPuerta();
        $dispositivo = $this->crearDispositivo($puerta);
        $token = $dispositivo->createToken('test', ['events:create']);
        $payload = $this->payload();
        $payload['puerta_id'] = 999;
        $payload['vibracion_detectada'] = 'true';

        $this->withToken($token->plainTextToken)
            ->postJson($this->endpoint($puerta), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['puerta_id', 'vibracion_detectada']);

        $this->assertDatabaseCount('eventos_puerta', 0);
    }

    public function test_valid_event_is_stored_as_device_origin_without_sensor_fabrication(): void
    {
        $puerta = $this->crearPuerta();
        $dispositivo = $this->crearDispositivo($puerta);
        $token = $dispositivo->createToken('test', ['events:create']);
        $payload = $this->payload();

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
            'origen' => 'dispositivo_real',
            'distancia_pasador_cm' => null,
            'cantidad_vibracion' => null,
        ]);

        $this->assertDatabaseCount('eventos_puerta', 1);
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

        $apiResponse = $this->withToken($token->plainTextToken)
            ->postJson($this->endpoint($puerta), $payload)
            ->assertCreated()
            ->assertJsonPath('data.puerta_id', $puerta->id)
            ->assertJsonPath('data.tipo_evento', 'lectura_anomala')
            ->assertJsonPath('data.origen', 'dispositivo_real');

        $eventoId = $apiResponse->json('data.id');
        $fechaEsperada = now()->parse($payload['detectado_en'])->utc()->toISOString();

        $this->assertDatabaseHas('eventos_puerta', [
            'id' => $eventoId,
            'puerta_id' => $puerta->id,
            'dispositivo_iot_id' => $dispositivo->id,
            'tipo_evento' => 'lectura_anomala',
            'origen' => 'dispositivo_real',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('eventos_puerta.index', ['puerta_id' => $puerta->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('puertaSeleccionada.id', $puerta->id)
                ->where('estadisticas.total', 2)
                ->where('eventos.total', 2)
                ->where('eventos.data.0.id', $eventoId)
                ->where('eventos.data.0.tipo_evento', 'lectura_anomala')
                ->where('eventos.data.0.detectado_en', $fechaEsperada)
                ->where('ultimoEvento.id', $eventoId));
    }

    public function test_retransmissions_are_idempotent_and_key_reuse_with_other_payload_conflicts(): void
    {
        $puerta = $this->crearPuerta();
        $dispositivo = $this->crearDispositivo($puerta);
        $token = $dispositivo->createToken('test', ['events:create']);
        $payload = $this->payload();

        $this->withToken($token->plainTextToken)
            ->postJson($this->endpoint($puerta), $payload)
            ->assertCreated()
            ->assertJsonPath('data.id', 1);

        $this->postJson($this->endpoint($puerta), $payload)
            ->assertOk()
            ->assertJsonPath('duplicado', true)
            ->assertJsonPath('data.id', 1);

        $differentPayload = [...$payload, 'vibracion_detectada' => true];

        $this->postJson($this->endpoint($puerta), $differentPayload)
            ->assertStatus(409)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('eventos_puerta', 1);
    }

    public function test_web_dashboard_remains_available_to_authenticated_users(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk();
    }

    private function crearPuerta(string $nombre = 'Puerta de prueba'): Puerta
    {
        return Puerta::query()->create(['nombre' => $nombre]);
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

<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEventoPuertaRequest extends FormRequest
{
    private const CAMPOS_PERMITIDOS = [
        'id_evento_dispositivo',
        'estado_puerta',
        'estado_candado',
        'tipo_evento',
        'vibracion_detectada',
        'detectado_en',
        'distancia_pasador_cm',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'id_evento_dispositivo' => ['required', 'uuid'],
            'estado_puerta' => ['sometimes', 'nullable', Rule::in(['abierta', 'cerrada'])],
            'estado_candado' => ['sometimes', 'nullable', Rule::in(['bloqueado', 'desbloqueado'])],
            'tipo_evento' => [
                'required',
                Rule::in([
                    'puerta_abierta',
                    'puerta_cerrada_sin_seguro',
                    'puerta_cerrada_con_seguro',
                    'llamado_puerta',
                    'posible_forzamiento',
                    'vibracion_detectada',
                    'lectura_anomala',
                ]),
            ],
            'vibracion_detectada' => ['required', 'boolean'],
            'detectado_en' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'distancia_pasador_cm' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:400'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $json = $this->json()->all();

                foreach (array_diff(array_keys($json), self::CAMPOS_PERMITIDOS) as $campo) {
                    $validator->errors()->add($campo, 'Este campo no está permitido.');
                }

                if (
                    array_key_exists('vibracion_detectada', $json)
                    && ! is_bool($json['vibracion_detectada'])
                ) {
                    $validator->errors()->add(
                        'vibracion_detectada',
                        'El campo debe ser un booleano JSON.',
                    );
                }

                $tipo = $this->input('tipo_evento');
                $estadoPuerta = $this->input('estado_puerta');
                $estadoCandado = $this->input('estado_candado');

                if ($tipo === 'puerta_abierta' && $estadoPuerta !== 'abierta') {
                    $validator->errors()->add('estado_puerta', 'El evento requiere estado abierta.');
                }

                if ($tipo === 'vibracion_detectada' && $this->input('vibracion_detectada') !== true) {
                    $validator->errors()->add(
                        'vibracion_detectada',
                        'El evento requiere que se haya detectado vibración.',
                    );
                }

                if (
                    $tipo === 'puerta_cerrada_sin_seguro'
                    && ($estadoPuerta !== 'cerrada' || $estadoCandado !== 'desbloqueado')
                ) {
                    $validator->errors()->add(
                        'estado_puerta',
                        'El evento requiere puerta cerrada y seguro desbloqueado.',
                    );
                }

                if (
                    $tipo === 'puerta_cerrada_con_seguro'
                    && ($estadoPuerta !== 'cerrada' || $estadoCandado !== 'bloqueado')
                ) {
                    $validator->errors()->add(
                        'estado_puerta',
                        'El evento requiere puerta cerrada y seguro bloqueado.',
                    );
                }

                if (
                    $tipo === 'lectura_anomala'
                    && ($estadoPuerta !== null || $estadoCandado !== null)
                ) {
                    $validator->errors()->add(
                        'estado_puerta',
                        'Una lectura anómala debe dejar ambos estados indeterminados.',
                    );
                }

                if (
                    array_key_exists('distancia_pasador_cm', $json)
                    && $json['distancia_pasador_cm'] !== null
                    && ! is_int($json['distancia_pasador_cm'])
                    && ! is_float($json['distancia_pasador_cm'])
                ) {
                    $validator->errors()->add(
                        'distancia_pasador_cm',
                        'La distancia debe ser un número JSON en centímetros.',
                    );
                }
            },
        ];
    }
}

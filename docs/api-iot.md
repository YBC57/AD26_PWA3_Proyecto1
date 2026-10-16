# API IoT v1

## Endpoint y autenticacion

`POST /api/v1/puertas/{puerta}/eventos`

La ruta recibe exclusivamente tokens Sanctum emitidos para modelos `DispositivoIot`. Cada dispositivo pertenece a una puerta, y el middleware compara esa puerta con el identificador de la ruta. El token solo tiene la habilidad `events:create`, vence a los 90 dias y se puede revocar por token:

```powershell
php artisan iot:crear-dispositivo <puerta_id> <nombre>
php artisan iot:revocar-token <dispositivo_id> <token_id>
```

El comando de creacion muestra el bearer token una sola vez en la terminal. Guardarlo fuera del repositorio y transmitirlo solamente sobre HTTPS. Nunca enviarlo en el cuerpo JSON ni en query strings.

El limite inicial es de 60 solicitudes por minuto. La API no utiliza la sesion web de Fortify.

## Contrato JSON

```json
{
    "id_evento_dispositivo": "8b28c196-e61d-4bf5-b287-93c981d24c46",
    "estado_puerta": null,
    "estado_candado": null,
    "tipo_evento": "lectura_anomala",
    "vibracion_detectada": false,
    "detectado_en": "2026-10-15T18:30:00-06:00",
    "distancia_pasador_cm": null
}
```

- `id_evento_dispositivo`: UUID generado y conservado por el dispositivo para retransmitir el mismo evento sin duplicarlo.
- `estado_puerta`: `abierta`, `cerrada` o `null` si no se conoce con suficiente evidencia.
- `estado_candado`: `bloqueado`, `desbloqueado` o `null` si no se conoce con suficiente evidencia.
- `tipo_evento`: `puerta_abierta`, `puerta_cerrada_sin_seguro`, `puerta_cerrada_con_seguro`, `llamado_puerta`, `posible_forzamiento` o `lectura_anomala`. Los tres tipos de estado requieren estados coherentes; `lectura_anomala` requiere ambos estados en `null`.
- `vibracion_detectada`: booleano JSON; representa la deteccion indicada por el dispositivo, no demuestra por si sola un intento de forzamiento.
- `detectado_en`: fecha ISO 8601 con offset horario.
- `distancia_pasador_cm`: valor numerico en centimetros (se almacena con dos decimales) o `null` si no hay una medida valida. La validacion acepta de 0 a 400 cm como rango amplio del sensor; no es un umbral de puerta, seguro ni calibracion. La clasificacion del pasador no se deriva automaticamente de la distancia.

No se aceptan campos adicionales. El servidor fija `origen=dispositivo_real` a partir de la credencial; el cliente no puede elegir `origen` ni `puerta_id`. Los datos crudos del MPU-6050 no forman parte de esta version; `cantidad_vibracion` no se reutiliza como medicion de aceleracion.

Las respuestas satisfactorias incluyen el identificador del evento. Una repeticion con el mismo UUID y contenido devuelve el evento existente; reutilizar el UUID con contenido diferente devuelve `409`. Los errores de validacion son JSON `422`, la falta de credencial es `401`, la falta de autorizacion es `403` y el limite excedido es `429`.

## Almacenamiento y alcance

`origen` distingue los eventos de prueba existentes (`prueba`, valor predeterminado) de los recibidos por API (`dispositivo_real`). El identificador y hash de idempotencia son nullable para conservar los eventos previos; el indice unico solo aplica a eventos vinculados a un dispositivo. Los estados admiten `null` para registrar una lectura sin clasificar, sin reinterpretar las filas historicas.

La distancia se almacena como `distancia_pasador_cm`. En eventos IoT, `cantidad_vibracion` queda en `null` porque esta API no recibe un conteo con definicion compatible. No se persisten muestras crudas del MPU-6050 y no se han definido umbrales de clasificacion. HTTPS, calibracion del montaje, comportamiento ante ausencia de eco y configuracion electrica del HC-SR04/ESP8266 deben verificarse antes de conectar el hardware.

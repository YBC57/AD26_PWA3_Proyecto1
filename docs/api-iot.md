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
    "tipo_evento": "vibracion_detectada",
    "vibracion_detectada": true,
    "detectado_en": "2026-10-15T18:30:00-06:00",
    "distancia_pasador_cm": null
}
```

- `id_evento_dispositivo`: UUID generado y conservado por el dispositivo para retransmitir el mismo evento sin duplicarlo.
- `estado_puerta`: `abierta`, `cerrada` o `null` si no se conoce con suficiente evidencia.
- `estado_candado`: `bloqueado`, `desbloqueado` o `null` si no se conoce con suficiente evidencia.
- `tipo_evento`: `puerta_abierta`, `puerta_cerrada_sin_seguro`, `puerta_cerrada_con_seguro`, `llamado_puerta`, `posible_forzamiento`, `vibracion_detectada` o `lectura_anomala`. `puerta_abierta` requiere `estado_puerta=abierta`; las dos variantes de puerta cerrada requieren `estado_puerta=cerrada` y el estado de seguro indicado por su nombre. La puerta y el seguro son estados independientes: estar cerrada no implica estar bloqueada. `lectura_anomala` requiere ambos estados en `null`. `vibracion_detectada` requiere `vibracion_detectada=true` y significa exclusivamente que se detectó vibración sin una clasificación concluyente. `llamado_puerta` y `posible_forzamiento` describen clasificaciones del evento, no estados inferidos automáticamente por vibración.
- `vibracion_detectada`: booleano JSON; para el tipo `vibracion_detectada` debe ser `true`. El SW-420 detecta activaciones digitales, no mide directamente la fuerza del golpe ni demuestra por sí solo que alguien llamó o intentó forzar la puerta.
- `detectado_en`: fecha ISO 8601 con offset horario. El servidor conserva el instante indicado, lo normaliza a UTC y responde en formato ISO UTC con `Z`.
- `distancia_pasador_cm`: valor numerico de 0 a 400 centimetros (se almacena con dos decimales) o `null`. Este es el rango de validacion actual del servidor, no una especificacion confirmada del HC-SR04 ni un umbral o calibracion. La clasificacion del pasador no se deriva automaticamente de la distancia; confirmar el rango y las condiciones de lectura del sensor antes de definir el cliente.

No se aceptan campos adicionales. El servidor fija `origen=dispositivo_real` a partir de la credencial; el cliente no puede elegir `origen` ni `puerta_id`. El conteo de activaciones del SW-420 no forma parte de este contrato y no se almacena en `cantidad_vibracion`, que queda en `null` para eventos IoT.

Las respuestas satisfactorias incluyen el identificador del evento. Una repeticion con el mismo UUID y contenido devuelve el evento existente; reutilizar el UUID con contenido diferente devuelve `409`. Los errores de validacion son JSON `422`, la falta de credencial es `401`, la falta de autorizacion es `403` y el limite excedido es `429`.

## Almacenamiento y alcance

`origen` distingue los eventos de prueba existentes (`prueba`, valor predeterminado) de los recibidos por API (`dispositivo_real`). El identificador y hash de idempotencia son nullable para conservar los eventos previos; el indice unico solo aplica a eventos vinculados a un dispositivo. Los estados admiten `null` para registrar una lectura sin clasificar, sin reinterpretar las filas historicas.

Laravel y PHP almacenan e interpretan fechas en UTC. Las conexiones MySQL/MariaDB de Laravel usan `DB_TIMEZONE` (predeterminado `+00:00`) para que las columnas `TIMESTAMP` se lean y escriban en UTC; el servidor de base de datos y otras aplicaciones no se modifican. El dashboard presenta las fechas en `America/Mexico_City`. No se deben reescribir fechas históricas para compensar cambios de presentación.

La distancia se almacena como `distancia_pasador_cm`. En eventos IoT, `cantidad_vibracion` queda en `null` porque esta API no recibe un conteo con definicion compatible. `vibracion_detectada` es una categoría neutral y no altera los estados de puerta o seguro; se dejan en `null` cuando no hay evidencia independiente para determinarlos. HTTPS, calibracion del montaje, comportamiento ante ausencia de eco y configuracion electrica del HC-SR04/ESP8266 deben verificarse antes de conectar el hardware.

Los nombres historicos de factories y seeders (`puerta_cerrada`, `candado_activado`, `candado_desactivado`, `vibracion_suave`, `vibracion_fuerte` e `intento_forzado`) siguen en sus registros y en la presentacion del historial; no son valores aceptados por este endpoint. El endpoint no redefine ni migra esos eventos.

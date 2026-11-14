# Arquitectura

Sistema de registro de eventos para Budismo Camino del Diamante Colombia (DWB), en
PHP 8.3 + CodeIgniter 4.7 + MariaDB. La aplicación está pensada **parametrizable
por datos**: cada evento es un documento JSON (semilla) — nada de un evento
específico fijo en el código.

## Visión general

```text
                 ┌────────────────────────────────────────────┐
   Participante │  Wizard público  /e/{slug}                  │
   (navegador)  │  formulario → cotizar (AJAX) → link Bold     │
                │  → paga → sube comprobante → recibo          │
                └──────────────────────────────────────────────┘
                 ┌────────────────────────────────────────────┐
   Bold (API)   │  /webhook/bold  → valida firma → asocia →   │
                │  marca el pago (PAID)                       │
                └──────────────────────────────────────────────┘
                 ┌────────────────────────────────────────────┐
   Responsable  │  Backoffice  /admin                         │
                │  eventos, inscritos, usuarios, integraciones│
                │  webhooks, recibos                          │
                └──────────────────────────────────────────────┘
```

## Capas

La estructura sigue la separación típica de CodeIgniter 4 ampliada con un núcleo
de dominio aislado (testeable sin base de datos).

| Capa | Ubicación | Responsabilidad |
|------|-----------|-----------------|
| Controllers | `app/Controllers/` | Periferia HTTP: leer request, orquestar, devolver views/redirect/JSON. |
| Domain | `app/Domain/` | Modelo puro de negocio, sin dependencias del framework ni de BD. |
| Persistencia | `app/Persistencia/` | Repositorios sobre MariaDB y sobre la API de Bold, correo. |
| Views | `app/Views/inscripcion/`, `app/Views/admin/` | Presentación HTML pública y administrativa. |
| Database | `app/Database/Migrations/`, `Seeds/eventos/*.json` | Esquema y semillas de eventos. |

### Controllers

- `Inscripcion` — flujo público: `index` (formulario), `cotizar` (AJAX total),
  `linkPago` (AJAX link Bold), `enviar` (confirmar), `recibo`, `pagar`.
- `Admin` — backoffice (login, eventos, inscritos, usuarios, integraciones, webhooks).
- `Webhook` — endpoint público `POST /webhook/bold`.
- `BaseController` — utilidades comunes.

### Domain (núcleo)

- `ProgramaEvento` / `ArchivoPrograma` — el evento importado desde JSON (ficha,
  cursos, comidas, formas de pago, sesiones, alojamiento, equipos).
- `AsistenteInscripcion` — valida el POST, arma la cotización y congela el recibo.
- `Cotizador` / `Cotizacion` — cálculo del total (el servidor decide el monto,
  usar el navegador como única fuente es un riesgo).
- `EditorEvento` — guarda/valida un evento desde el backoffice.
- `ValidadorFicha` — reglas de campos (documento, correo, edad, consentimiento, …).

El dominio se prueba con PHPUnit **sin** levantar CodeIgniter (`composer test:domain`).

### Persistencia

- `RepositorioEventos` — eventos (semilla JSON + tabla `eventos`), inscripciones,
  webhooks, intentos de envío. Las migraciones de tablas/columnas nuevas se
  resuelven **por auto-migración** (`asegurarColumnasPago`, `asegurarTablaWebhooks`,
  `asegurarTablaIntentos`) para no depender de correr migrations.
- `RepositorioConfiguracion` — tabla clave/valor (`configuracion`).
- `BoldClient` — cliente de la API Link de pagos de Bold (crear/consultar link,
  validar firma del webhook).
- `CorreoConfigurado` — envío por SMTP configurado.

## Modelo de datos

### `eventos`
`id`, `slug` (único), `nombre`, `programa_json` (JSON completo del evento), `creado_en`.

### `inscripciones`
| Columna | Notas |
|---------|-------|
| `id` | PK |
| `evento_id` | FK → `eventos.id` |
| `documento` | Documento del participante |
| `documento_activo` | Marcador de "documento en uso". Índice **único** `(evento_id, documento_activo)` → una sola inscripción activa por documento en cada evento |
| `estado` | `activa` (por el momento la inscripción nace activa al confirmar; se gestiona anulación desde backoffice) |
| `total` | Monto COP calculado en servidor |
| `cotizacion_json` / `entrada_json` / `notas_json` | Copia congelada del recibo, la entrada y las notas |
| `comprobante` | Nombre del archivo subido (guardado fuera de la carpeta pública) |
| `payment_link` | LNK_… de Bold |
| `payment_status` | `ACTIVE` / `PAID` / `REJECTED` / `CANCELLED` |
| `transaction_id` | ID del pago en Bold |
| `bold_reference` | Referencia del link (REG-…) enviada al crear el link; permite asociar el webhook |
| `pagado_en` | Cuándo se confirmó `PAID` |
| `creado_en` | Timestamp |

### `webhook_entradas`
Bandeja de notificaciones de Bold: `id`, `proveedor`, `uuid` (`id` del CloudEvents,
único → idempotencia por reintento), `recibido_en`, `tipo` (`SALE_APPROVED`, …),
`payment_id`, `payment_link`, `referencia`, `firma_valida`, `firma_recibida`,
`inscripcion_id`, `payment_status`, `asociada`, `nota`, `payload_json`.

### `intentos_enviados`
Idempotencia del envío del formulario: `id`, `nonce` (único), `inscripcion_id`,
`terminado`, `creado_en`.

### `configuracion`
Clave/valor: `correo_*` (SMTP) y `bold_*` (`bold_api_key`, `bold_secret_key`,
`bold_ambiente = sandbox|produccion`).

### Otras
`usuarios` (backoffice), `ci_sessions` (sesiones en BD).

## Flujos principales

### 1. Inscripción estándar (transferencia / no cobra en sitio)
1. `GET /e/{slug}` → render del formulario (con un `idempotencia` nonce).
2. El participante cambia opciones → `POST /e/{slug}/cotizar` (AJAX) recalcula el total **en servidor**.
3. `POST /e/{slug}` con `accion=confirmar`:
   1. Se reclama el `nonce` (si ya se procesó → no duplica; redirige al recibo de la inscripción creada si la hay).
   2. Se valida la ficha/curso/consentimiento. Si algo falla → re-render con el campo marcado en rojo.
   3. `guardar()` → inserta la inscripción (estado `activa`, `documento_activo`) respetando el índice único (duplicado real → mensaje, sin crear fila).
   4. Notifica por correo y hace `redirect` a `/e/{slug}/recibo/{id}`.

### 2. Pago por tarjeta (Bold, Link de pagos)
1. En el formulario, el participante elige **tarjeta** y pulsa “Generar enlace de pago” → `POST /e/{slug}/link-pago` (AJAX): el servidor calcula el total y crea un link Bold con `reference = REG-xxxx` (único). Devuelve `url` (checkout), `payment_link` (LNK) y `reference`.
2. El participante paga en la pasarela (Bold), baja el comprobante y lo sube.
3. Al confirmar, `enviar()` guarda la inscripción y persiste `payment_link` (LNK) y `bold_reference` (`REG-xxxx`).
4. Bold notifica el estado → `POST /webhook/bold`:
   - Registra **siempre** la entrada en `webhook_entradas` (idempotente por `id` del CloudEvents).
   - Valida la firma `HMAC-SHA256(base64(body))` (clave secreta en producción; **clave vacía en sandbox**).
   - Asocia por orden: `payment_link` (LNK) → `metadata.reference` (`INS-{id}` o `bold_reference`) → marca `payment_status = PAID/REJECTED/CANCELLED`.
   - Responde `200` siempre (Bold no reintenta ni duplica).

> **Sandbox (pruebas)**: Bold ofusca los identificadores del webhook (`XXXX`,
> monto `0`, `reference` vacío), por lo que en pruebas la firma valida pero la
> asociación automática no es posible. Se valida end-to-end en producción.

## Decisiones de diseño relevantes

- **Total en el servidor**: el navegador nunca define montos.
- **Una inscripción = una persona**: índice único `(evento_id, documento_activo)`.
- **Idempotencia de envío** con `nonce` (evita el “se guardó + dijo duplicado” por doble POST).
- **Webhook idempotente** por `id` de CloudEvents y respuesta `200` siempre.
- **Auto-migración** de tablas/columnas de pago para no depender de correr migrations.
- **Comprobantes fuera de la carpeta pública** (`writable/uploads/`), accesibles solo vía backoffice.
- **Consentimiento de datos** (Ley 1581/2012) requerido para confirmar.
- **Zona horaria** `America/Bogota`; montos COP sin decimales.
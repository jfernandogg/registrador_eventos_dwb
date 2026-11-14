# Documentación técnica

Requisitos, stack, estructura de código, rutas, seguridad e integraciones.

## Stack y versiones

- PHP **8.3** (requiere ≥ 8.2). Extensiones: `intl`, `mbstring`, `mysqlnd/MySQLi`, `curl`.
- CodeIgniter **4.7** (`composer.json`).
- MariaDB **10.11** (compatible MySQL).
- Apache (dev: imagen `php:8.3-apache`; prod: cPanel).
- Paquetes dev: `phpunit/phpunit ^10.5`, `fakerphp/faker`, `mikey179/vfsstream`.

## Variables de entorno (`.env`)

| Variable | Uso |
|----------|-----|
| `CI_ENVIRONMENT` | `development` (dev) / `production` (prod) |
| `app.baseURL` | URL pública (en dev incluye `/index.php`) |
| `database.default.*` | Conexión MariaDB (hostname, database, username, password, `DBDriver = MySQLi`, port) |

Los secretos de integración **no** van en `.env`:
- `correo_*` → se guardan en la tabla `configuracion` (página **Integraciones**).
- `bold_api_key`, `bold_secret_key`, `bold_ambiente` → también en `configuracion`.

## Estructura de `app/`

```text
Controllers/   Inscripcion.php   Admin.php   Webhook.php   BaseController.php
Domain/        ProgramaEvento, AsistenteInscripcion, Cotizador, Cotizacion,
               EditorEvento, ValidadorFicha, ResumenInscripcion, … (+ tour365 legacy)
Persistencia/  RepositorioEventos, RepositorioConfiguracion,
               BoldClient, CorreoConfigurado
Views/         inscripcion/ (campo, formulario, portada, recibo, pagar-placeholder)
               admin/ (entrar, eventos, inscritos, detalle-inscrito, usuarios,
                      cambiar-clave, integraciones, webhooks, webhook-detalle, …)
Database/      Migrations/, Seeds/eventos/<slug>.json
Filters/       Filtro de autenticación del backoffice
Config/        Routes.php, Database.php, Email.php, …
```

### Persistencia remota (Bold)

`BoldClient`:

- Base: `https://integrations.api.bold.co`.
- `POST /online/link/v1` → crea el link (`amount_type: CLOSE`, `payment_methods = [CREDIT_CARD, PSE, BOTON_BANCOLOMBIA, NEQUI]`, `callback_url` solo `https://`). Respondo `payment_link` (LNK…) y `url` (checkout).
- `GET /online/link/v1/{LNK}` → consulta el estado.
- Firma del webhook: header `x-bold-signature` = `HMAC-SHA256( base64(body) )` en **hex**.
  - **Producción**: con la llave secreta configurada.
  - **Sandbox/pruebas**: Bold firma con **clave vacía** (`''`).

## Rutas (`app/Config/Routes.php`)

Públicas:

| Método | Ruta | Controlador |
|--------|------|-------------|
| GET | `/` | `Inscripcion::portada` |
| GET | `/e/(:segment)` | `Inscripcion::index` (formulario) |
| POST | `/e/(:segment)/cotizar` | `Inscripcion::cotizar` (AJAX total) |
| POST | `/e/(:segment)/link-pago` | `Inscripcion::linkPago` (AJAX link Bold) |
| POST | `/e/(:segment)` | `Inscripcion::enviar` (confirmar inscripción) |
| GET | `/e/(:segment)/recibo/(:num)` | `Inscripcion::recibo` |
| GET | `/pagar/(:segment)/(:num)` | `Inscripcion::pagar` (flow post-inscripción) |
| POST | `/webhook/bold` | `Webhook::bold` |

Backoffice (filtro `admin`):

| Ruta | Controlador → método |
|------|----------------------|
| `/admin` | `Admin::eventos` |
| `/admin/eventos/nuevo` · `POST /admin/eventos` | `Admin::nuevo` / `Admin::crear` |
| `/admin/eventos/(:segment)[/inscritos][/exportar][/(:num)]` | listado/edición/export/comprobante |
| `/admin/usuarios…` | gestión de usuarios y roles |
| `/admin/cambiar-clave` | `Admin::cambiarClave` |
| `/admin/integraciones…` | `Admin::guardarCorreo` · `guardarBold` · probar |
| `/admin/webhooks` · `/admin/webhooks/(:num)` | bandeja y detalle de webhooks |

En dev la base es `http://localhost:8080/index.php` (la `baseURL` incluye `/index.php`).

## Seguridad

- **Backoffice**: filtro de autenticación; sesiones en BD (`ci_sessions`).
- **Webhook**: se valida la firma (`x-bold-signature`) antes de procesar; se registra
  siempre la entrada y se responde `200` para que Bold no reintente/duplique.
- **Idempotencia de envío**: nonce por formulario (`intentos_enviados`).
- **Total** y referencia de pago: decididos en el servidor.
- **Datos personales**: consentimiento Ley 1581/2012 requerido; no se loguean documento ni salud.
- **Comprobantes**: guardados en `writable/uploads/comprobantes`, no accesibles públicamente; solo tesorería vía backoffice.

## Pruebas

```bash
# Test del modelo de dominio (sin CodeIgniter/BD)
composer test:domain

# Suite completa
XDEBUG_MODE=off php vendor/bin/phpunit
```

Lint rápido: `php -l app/Controllers/Inscripcion.php`.

## Integraciones (página `/admin/integraciones`)

### Correo
- `correo_protocolo` (smtp), `correo_smtp_host` (p. ej. `smtp.gmail.com`), puerto,
  cripto (TLS), usuario y clave, remitente.
- Botón **Probar correo** envía un mensaje de prueba.

### Bold
- `bold_api_key` (llave de identidad), `bold_secret_key` (llave de **firma del webhook**;
  en sandbox Bold firma con clave vacía, así que el valor puede ser cualquiera en pruebas),
  `bold_ambiente` (`sandbox` o `produccion`).
- Botones de prueba de conexión.

### Webhooks (bandeja `/admin/webhooks`)
Cada notificación recibida aparece con estado de firma (`Firma válida` / `Firma
inválida`), asociación, inscripción vinculada y el payload completo.
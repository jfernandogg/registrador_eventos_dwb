# Registrador de eventos — Budismo Camino del Diamante Colombia (DWB)

Sistema de **inscripción en línea** y **backoffice** para los eventos de Budismo
Camino del Diamante Colombia (DWB). Combina un *wizard* público de inscripción y
un panel de administración para gestionar eventos, inscritos, formas de pago,
integraciones (correo y pasarela Bold) y recibos.

> **Parametrizable por datos**: ningún evento está fijo en el código. Cada evento
> se define como un documento JSON (semilla) y se administra desde el backoffice —
> la app se programa en general, no para un evento específico.

---

## Tabla de contenidos

- [Características](#características)
- [Stack tecnológico](#stack-tecnológico)
- [Estructura del repositorio](#estructura-del-repositorio)
- [Inicio rápido (desarrollo)](#inicio-rápido-desarrollo)
- [Producción (GoDaddy)](#producción-godaddy)
- [Documentación](#documentación)
- [Pruebas](#pruebas)

---

## Características

- **Wizard público de inscripción** (`/e/{slug}`): formulario por evento con
  cursos, comidas, transporte, alojamiento, sesiones y equipos.
- **Total calculado en el servidor** — el navegador nunca decide el monto.
- **Una inscripción = una persona**: índice único por documento + evento;
  una persona no abre dos inscripciones activas al mismo evento.
- **Idempotencia de envío**: un nonce por formulario evita duplicados por doble
  envío (tabla `intentos_enviados`).
- **Pago por tarjeta (Bold)** con Link de pagos: generación por AJAX antes de
  confirmar, alta de comprobante y **webhook** que actualiza el pago (firma
  validada; idempotente por `id` de CloudEvents).
- **Backoffice** (`/admin`): CRUD de eventos, listado/exportación de inscritos,
  usuarios y roles, y páginas **Integraciones** (SMTP + credenciales Bold) y
  **Webhooks** (bandeja de notificaciones de pago).
- **Correo SMTP configurable** (Gmail u otro) y aviso de inscripción al
  participante.
- **Consentimiento de tratamiento de datos** (Ley 1581/2012).
- **Diseño DWB**: logo SVG, rojo `#be0f34`, texto negro, Myriad Pro, fondo blanco.

## Stack tecnológico

| Capa      | Tecnología                                                |
|-----------|-----------------------------------------------------------|
| Lenguaje  | PHP 8.3                                                   |
| Framework | CodeIgniter 4.7 (estructura de capas de aplicación)       |
| Base      | MariaDB 10.11 (compatible MySQL; driver `MySQLi`)         |
| Servidor  | Apache (dev en Docker; cPanel en producción)              |
| Pasarela  | Bold — API Link de pagos (sandbox / producción, webhooks) |
| Correo    | SMTP (Gmail por defecto)                                  |
| Dev       | Podman Compose (app + MariaDB + Mailpit)                  |

## Estructura del repositorio

```text
app/
  Controllers/   Inscripcion, Admin, Webhook, BaseController
  Domain/        Modelo de dominio (ProgramaEvento, AsistenteInscripcion, Cotizacion, …)
  Persistencia/  RepositorioEventos, RepositorioConfiguracion, BoldClient, CorreoConfigurado
  Views/         inscripcion/ (público) y admin/ (backoffice)
  Database/
    Migrations/  Creación de tablas
    Seeds/eventos/*.json   ← definición de cada evento (semilla)
  Config/        Routes.php, filtros, sesiones…
public/          Documento raíz (index.php, css, js, images)
scripts/         Scripts de utilidad (forwards, túnel de pruebas)
writable/        Comprobantes (“uploads” fuera de la carpeta pública), logs, sesiones
tests/           Pruebas PHPUnit
```

La documentación completa vive en [`doc/`](doc/README.md).

## Inicio rápido (desarrollo)

Requisitos: **Podman** (con Compose) y, para acceder desde Windows, los
reenviadores de la carpeta `scripts/`.

```bash
# 1. Levantar el stack (app + db + mailpit)
podman compose up -d
#    (en Windows/Podman: podman machine start podman-machine-default antes, si hace falta)

# 2. Acceso desde Windows (puente hacia el contenedor en WSL)
bash scripts/levantar-tunel-prueba.sh      # forward de la app y túnel del webhook

# 3. Abrir la app
#    http://localhost:8080/index.php            → portada /e/{slug}
#    http://localhost:8080/index.php/admin      → backoffice
#    http://localhost:8025                       → Mailpit (correos capturados, opcional)
```

Detalle en [`doc/operacion-contenedores.md`](doc/operacion-contenedores.md).

## Producción (GoDaddy)

GoDaddy cPanel (PHP 8.3 vía MultiPHP, MariaDB). Guía paso a paso (build, subida,
base de datos, `.env`, permisos, verificación) en
[`doc/operacion-godaddy.md`](doc/operacion-godaddy.md) y
[`DESPLIEGUE.md`](DESPLIEGUE.md).

## Documentación

| Documento | Contenido |
|-----------|-----------|
| [`doc/README.md`](doc/README.md) | Índice de la documentación técnica |
| [`doc/arquitectura.md`](doc/arquitectura.md) | Arquitectura, capas, datos y flujos |
| [`doc/tecnica.md`](doc/tecnica.md) | Stack, código, rutas, seguridad e integraciones |
| [`doc/operacion-contenedores.md`](doc/operacion-contenedores.md) | Operación dev/Podman, forwards y túnel |
| [`doc/operacion-godaddy.md`](doc/operacion-godaddy.md) | Despliegue y operación en cPanel |
| [`DESPLIEGUE.md`](DESPLIEGUE.md) | Guía de despliegue GoDaddy (resumen práctico) |

## Pruebas

```bash
# Pruebas del modelo de dominio (sin levantar CodeIgniter)
composer test:domain

# Toda la suite PHPUnit
XDEBUG_MODE=off php vendor/bin/phpunit
```

Lint de un archivo: `php -l app/Controllers/Inscripcion.php`.

---

*Budismo Camino del Diamante Colombia — Registrador de eventos.*

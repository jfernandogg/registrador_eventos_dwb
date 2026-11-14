# Operación — entorno de contenedores (desarrollo)

La aplicación se desarrolla con **Podman Compose** (`docker-compose.yml`): tres
servicios — `app` (PHP 8.3 + Apache), `db` (MariaDB 10.11) y `mailpit` (catcher de
correo). En esta máquina el Podman corre en una VM de WSL, por lo que hay que
“puentear” hacia el navegador de Windows con los scripts de `scripts/`.

## Servicios

| Servicio | Imagen | Puertos | Volúmenes |
|----------|--------|---------|-----------|
| `app` | `registrador-eventos:dev` (build) | `8080:80` | código montado (`.:/var/www/html`) + `docker/uploads.ini` |
| `db` | `mariadb:10.11` | `3306:3306` | `dbdata` (persistente) |
| `mailpit` | `axllent/mailpit:v1.21` | `8025:8025` (UI) · `1025:1025` (SMTP) | — |

Credenciales dev por defecto: usuario `registrador`, clave `registrador`, BD `registrador` (root `registrador`).

## Levantar el stack

```bash
# Si la VM de Podman está apagada (tras reinicios / wsl --shutdown)
podman machine start podman-machine-default

# Definir el entorno actual de Podman
podman machine info            # o: eval (podman env)  — según instalación

# Levantar los servicios
podman compose up -d

# ¿Querés seguir los logs de la app?
podman compose logs -f app
```

La app queda en `http://localhost:8080/index.php` dentro del entorno de Podman
(cómo acceder desde Windows: ver la sección “Puente desde Windows”).

Si cambiás código y no se ve reflejado (por ejemplo, PHP con opcache), reiniciá el
contenedor:

```bash
podman restart registrador_eventos_dwb-app-1
```

## Puente desde Windows (reenviadores)

La app corre en el contenedor de WSL, pero el navegador y las pruebas del usuario
usan `localhost` de Windows. Hay dos formas de alcanzarla desde `localhost:8080`
además del acceso directo del contenedor:

1. `<IP-WSL>:8080` (p. ej. `http://172.29.128.92:8080/index.php`) — acceso directo dentro de WSL.
2. `http://localhost:8080/index.php` → requiere el **forward local** `scripts/forward-8080.py`
   (proxy `127.0.0.1:8080 → 172.29.128.92:8080`).

### Scripts de la carpeta `scripts/`

- `forward-8080.py` — forward de la app (`127.0.0.1:8080 → 172.29.128.92:8080`).
- `forward-8025.py` — forward de Mailpit (`127.0.0.1:8025 → 172.29.128.92:8025`).
  **No es necesario** si el correo se envía por SMTP directo (p. ej. Gmail); solo hace
  falta si querés inspeccionar correos capturados por Mailpit.
- `levantar-tunel-prueba.sh` — levanta (si hace falta) el forward de la app **y**
  el túnel Cloudflare para exponer el webhook de Bold como URL pública HTTPS.
  Idempotente: detecta si ya hay un listener o si `cloudflared` ya corre.

```bash
bash scripts/levantar-tunel-prueba.sh
```

## Túnel del webhook (Cloudflare)

Para que **Bold** pueda avisar por `POST https://…/index.php/webhook/bold`, hace falta
una URL pública HTTPS. En dev se usa un túnel **efímero** de Cloudflare:

```bash
cloudflared tunnel --url http://localhost:8080
# → imprime algo como https://xxxx-xxxx.trycloudflare.com
```

- El webhook queda en `https://xxxx-xxxx.trycloudflare.com/index.php/webhook/bold`.
- **La URL es efímera**: cambia si se reinicia el túnel. Al cambiarla, hay que
  actualizarla en el **panel de Bold** (Integraciones → Webhook).
- El `levantar-tunel-prueba.sh` deja la URL en `writable/logs/pruebas/cloudflared.log`.

## Base de datos dev

```bash
# Consola MySQL dentro del contenedor
podman exec -it registrador_eventos_dwb-db-1 mysql -uregistrador -p registrador

# Consulta rápida sin interactivo
podman exec registrador_eventos_dwb-db-1 mysql -uregistrador -pregistrador registrador -e "SELECT id, documento, total, estado FROM inscripciones ORDER BY id DESC LIMIT 5;"
```

## Mailpit (opcional)

Si querés usar Mailpit como catcher local (en lugar de SMTP real), configurá en
Integraciones `correo_protocolo = smtp`, `host = mailpit` (o `127.0.0.1`), `puerto = 1025`.
La UI está en `http://localhost:8025` (requiere `forward-8025.py` desde Windows).

## Pruebas dentro del entorno

```bash
podman compose run --rm --no-deps --entrypoint php app vendor/bin/phpunit -c phpunit.domain.xml
```

## Solución de problemas

| Síntoma | Causa probable | Solución |
|---------|----------------|----------|
| `localhost:8080` no responde (000) en Windows | forward caído tras reinicio | `bash scripts/levantar-tunel-prueba.sh` |
| La URL del webhook cambió | túnel efímero reiniciado | actualizar la URL en el panel de Bold |
| Cambios en `app/` no se reflejan | opcache de PHP-FPM | `podman restart registrador_eventos_dwb-app-1` |
| WSL/podman apagado | `wsl --shutdown` / reinicio | `podman machine start podman-machine-default`.
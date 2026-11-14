# Operación — GoDaddy cPanel (producción)

La producción se publica en GoDaddy Web Hosting (cPanel, PHP 8.3, MariaDB), **sin
contenedores**. Resumen rápido: [`DESPLIEGUE.md`](../DESPLIEGUE.md). Este documento
agrega la operación de las **integraciones** (correo y, sobre todo, el webhook de
Bold) en producción.

## 1. Requisitos en cPanel

- PHP **8.3** en **MultiPHP Manager** (o *Select PHP Version*).
- Una base MariaDB y su usuario (ALL PRIVILEGES).

## 2. Estructura en el hosting

```text
/home/cpaneluser/
├── app/
├── vendor/
├── writable/            ← comprobantes, logs, sesiones (escritura: 755/775)
├── public/              ← (archivos de la app que NO van a public_html)
└── public_html/
    ├── index.php        ← el contenido de public/
    ├── css/  js/  dwb-logo.svg
    └── uploads/
```

- El **documento raíz** del sitio es `public_html/`.
- `writable/uploads/comprobantes` se guarda **fuera** de `public_html` → los
  comprobantes no se descargan públicamente.

## 3. `.env` de producción

Partir de `.env.production.example`:

```ini
CI_ENVIRONMENT = production
app.baseURL = 'https://tudominio.com.co/'
database.default.hostname = 'localhost'
database.default.database = 'cpaneluser_eventos'
database.default.username = 'cpaneluser_eventos'
database.default.password = 'CAMBIAR_ESTA_CLAVE'
database.default.DBDriver = 'MySQLi'
database.default.port = 3306
```

> **No subir `.env` al repositorio**: se crea a mano en la raíz del sitio.

## 4. Primer acceso

1. Abrir `https://tudominio.com.co/` (portada /e/{slug}).
2. `…/index.php/admin/entrar` → usuario inicial `admin@localhost` / `cambiala`.
3. **Cambiar la clave de inmediato** (Usuarios → Cambiar clave).

Las tablas (`eventos`, `inscripciones`, `usuarios`, `ci_sessions`, y las de
pago/webhook/bold) se crean automáticamente en el primer request.

## 5. Integraciones

### Correo (página `/admin/integraciones`)
- Si GoDaddy **bloquea SMTP saliente**, configurá `correo_protocolo = smtp` con las
  credenciales del hosting, o cambiá a `protocol = mail` en `app/Config/Email.php`.
- Botón **Probar correo** para validar.

### Bold (pago por tarjeta)
La configuración de Bold vive en `configuracion` (vía *Integraciones → Bold*):

| Clave | Producción |
|-------|------------|
| `bold_ambiente` | `produccion` |
| `bold_api_key` | La llave de identidad (public) del comercio |
| `bold_secret_key` | La llave **secreta de firma del webhook** (DEBE coincidir con la del panel de Bold) |

> En **producción** la firma del webhook se valida con esta `bold_secret_key`.
> (En sandbox Bold firma con clave vacía; en producción usa la llave).

### Webhook de Bold (crítico para marcar los pagos PAID)
1. En el **panel de Bold** → Integraciones → Webhook, registrar como URL:
   `https://tudominio.com.co/index.php/webhook/bold`
2. El **secret** configurado en el panel deve ser **idéntico** a `bold_secret_key`.
3. Verificación: hacer un pago de prueba y revisar `/admin/webhooks` → la nueva
   entrada debe tener **Firma válida** y **Asociada**, y la inscripción en
   **PAID**.

### Asociación del webhook (en producción)
El servidor asocia la notificación por, en orden:
1. `data.payment_link` (el LNK de la inscripción);
2. `data.metadata.reference` si es `INS-{id}` → inscripción por id;
3. `data.metadata.reference` en otro caso → por `inscripciones.bold_reference`
   (la referencia `REG-…` del link generado por el formulario).

## 6. Permisos

- `writable/` → 755 (o 775 si el servidor lo exige).
- `public/uploads/…` → 755.

## 7. Operación habitual

- **Nuevo evento**: copiar un JSON de `app/Database/Seeds/eventos/` o crear desde
  el backoffice (`/admin/eventos/nuevo`).
- **Inscritos**: listar/exportar desde `/admin/eventos/{slug}/inscritos`.
- **Recibos/pagos**: bandeja en `/admin/webhooks` (detalle del payload y estado).
- **Backups**: exportar la BD desde cPanel (phpMyAdmin) y respaldar `writable/uploads/comprobantes`.

## Solución de problemas

| Problema | Causa | Solución |
|----------|-------|----------|
| Error 500 / pantalla blanca | PHP < 8.2 o `.env` mal | revisar versión PHP y `.env` |
| “Page not found” en /admin | falta `index.php` en `public_html` | mover contenido de `public/` |
| Webhook “Firma inválida” | `bold_secret_key` ≠ secret del panel (producción) | alinear el secret |
| Webhook “Sin referencia” / no asociada | el pago aún no confirmó la inscripción o es sandbox ofuscado | confirmar la inscripción con el link; en producción trae `reference` |
| No llegan correos | SMTP bloqueado por GoDaddy | `protocol = mail` o SMTP del hosting |
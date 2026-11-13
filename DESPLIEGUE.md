# Despliegue en GoDaddy cPanel

Guía paso a paso para publicar el registrador de eventos en GoDaddy Web Hosting (cPanel, PHP 8.3, MariaDB).

---

## Antes de empezar

1. **Verificar la versión de PHP** en cPanel → **Software** → **MultiPHP Manager** o **Select PHP Version**. Seleccionar **8.3**.
2. **Tener a mano** el dominio (ej. `tudominio.com.co`) y las credenciales de cPanel.
3. **No subas `.env`** al servidor. Se configura manualmente.

---

## 1. Preparar el build de producción (en tu máquina)

```bash
cd /c/src/registrador_eventos_dwb

# Instalar dependencias de producción (sin dev)
composer install --no-dev --optimize-autoloader

# Verificar que no hay errores
php -l app/Controllers/Admin.php
```

## 2. Subir los archivos a cPanel

**Opción A: File Manager (cPanel)**

1. Comprimí la carpeta del proyecto en un ZIP (sin `.git`, sin `.env`, sin `vendor/` si ya corriste `composer install` en el servidor).
2. En cPanel → **File Manager**, navegar a `public_html/` o la carpeta del subdominio.
3. Subí el ZIP y descomprimilo.
4. **Importante:** mover el contenido de `public/` a `public_html/` (o la carpeta raíz del dominio), y las carpetas `app/`, `writable/`, `vendor/` quedan al mismo nivel (NO dentro de `public_html/`).

Estructura esperada:

```
/home/cpaneluser/
├── app/
├── vendor/
├── writable/
├── public/          ← el contenido de esto va a public_html/
└── public_html/
    ├── index.php
    ├── css/
    ├── js/
    ├── dwb-logo.svg
    └── uploads/
```

**Opción B: FTP (FileZilla)**

1. Conectar con las credenciales FTP de cPanel.
2. Subir `app/`, `vendor/`, `writable/` a `/home/cpaneluser/`.
3. Subir el contenido de `public/` a `/home/cpaneluser/public_html/`.

---

## 3. Crear la base de datos en cPanel

1. cPanel → **Database** → **MariaDB Databases** (o **MySQL Databases**).
2. Crear la base: `cpaneluser_eventos` (el prefijo `cpaneluser_` es automático).
3. Crear el usuario: `cpaneluser_eventos` con una clave segura.
4. **Añadir el usuario a la base** con **ALL PRIVILEGES**.

---

## 4. Configurar `.env`

1. En cPanel → **File Manager** → raíz del sitio (donde está `app/`).
2. Crear `.env` con el contenido de `.env.production.example`, cambiando:
   - `app.baseURL` → tu dominio real
   - `database.default.database` → el nombre de BD que creaste
   - `database.default.username` → el usuario que creaste
   - `database.default.password` → la clave que pusiste
   - `CI_ENVIRONMENT = production`
3. Guardar.

---

## 5. Ajustar permisos

En **File Manager** (clic derecho → Permissions):

- `writable/` → **755** (o 775 si el servidor lo pide)
- `public/uploads/` → **755**
- `public/uploads/comprobantes/` → **755**
- `public/uploads/eventos/` → **755**

---

## 6. Verificar

1. Abrir `https://tudominio.com.co/` → debería mostrar la lista de eventos (o "No hay eventos abiertos").
2. Abrir `https://tudominio.com.co/index.php/admin/entrar` → login.
3. Entrar con `admin@localhost` / `cambiala`.
4. **Inmediatamente** cambiar la clave desde Usuarios → Cambiar clave.

---

## 7. Post-deploy

- **PHP 8.3**: verificar en cPanel → **MultiPHP Manager** que el dominio usa 8.3.
- **Migrations**: las tablas `eventos`, `inscripciones`, `usuarios`, `ci_sessions` se crean automáticamente al primer request.
- **Correo**: si GoDaddy bloquea SMTP saliente, usar `protocol = mail` en `app/Config/Email.php` o configurar SMTP del hosting.

---

## Solución de problemas

| Problema | Causa | Solución |
|----------|-------|----------|
| Pantalla blanca / error 500 | `.env` mal configurado o PHP < 8.2 | Verificar `.env` y versión PHP |
| "Page not found" en /admin | `index.php` no está en `public_html` | Mover el contenido de `public/` a `public_html/` |
| No se guardan sesiones | `ci_sessions` no existe | La tabla se crea sola; verificar que la BD tiene permisos |
| No llegan correos | SMTP bloqueado | Cambiar a `protocol = mail` o SMTP de GoDaddy |
| Error de base de datos | Credenciales incorrectas | Revisar `.env` |

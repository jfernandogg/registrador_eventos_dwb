# Tareas del registrador

Estado al 13 de noviembre de 2026. Lo marcado como hecho se probó. Lo demás no.

## Hecho

- [x] Modelo de dominio y reglas del ejemplo Tour 365
- [x] CodeIgniter 4.7, PHP 8.3, Compose con MariaDB 10.11
- [x] Cotización probada: 390.000, 240.000, 367.500, 283.500 y 190.000
- [x] Formulario público en `/e/{slug}`. El total lo calcula el servidor y se actualiza por Ajax
- [x] Sesión en MariaDB (`ci_sessions`), para que el comprobante no muera por un `chmod`
- [x] Inscripción confirmada en MariaDB (`eventos`, `inscripciones`). El recibo se abre por número
- [x] El mismo documento no abre dos inscripciones activas en el mismo evento
- [x] La página pública es `/e/{slug}`. `/tour-365` solo redirige. El evento sale de la fila o del JSON de semilla
- [x] La ficha se pinta desde el esquema del evento
- [x] Diseñador de eventos en `/admin`: cursos, precios, comidas, franjas, alojamiento, pagos, equipos y ficha
- [x] Listado de inscritos con tabla hoja de cálculo + paginación + export CSV/ZIP
- [x] Tema visual DWB: logo SVG, rojo #be0f34, Myriad Pro, fondo blanco
- [x] Header admin: logo full-width arriba, menú debajo alineado a la izquierda
- [x] Imagen de evento + portada como tarjetas Bootstrap
- [x] Gestión de usuarios: roles (admin/editor/tesorero/invitado), invitación por token, cambiar clave
- [x] Instrucciones de pago dinámicas: cuenta bancaria, Bre-b, Bold URL
- [x] Integración con correo: invitaciones + notificación de inscripción (Mailpit en dev)
- [x] Build de producción (`composer install --no-dev`) + guía de despliegue `DESPLIEGUE.md`
- [x] Integración Bold por **API Link de pagos**: `BoldClient` (crear/consultar link, validar firma), webhook `/webhook/bold`, endpoint `/pagar/{slug}/{id}`, sección Bold en `/admin/integraciones`, botón de pago en el recibo, columnas de pago en `inscripciones`. Probada contra la API sandbox (crea link de $1.000 y consulta estado)
- [x] Generación del link de pago por AJAX en el formulario (`POST /e/{slug}/link-pago`, `target=_blank`): el participante genera el enlace, paga, baja el comprobante y lo sube antes de terminar. Resuelve el círculo de tarjeta (cobra + pide comprobante). Dato de prueba: inscripción N° 6 (`88888777`, total 88.000) guardada con `payment_link` + `ACTIVE` + comprobante

## Siguiente

- [ ] Al terminar una inscripción con éxito, el formulario pasa a **solo lectura** (campos deshabilitados) y el botón *Terminar inscripción* se reemplaza por **Nueva inscripción** (inicia en blanco)
- [ ] Publicar en GoDaddy: subir archivos, crear BD, configurar `.env`
- [ ] Probar flujo completo en producción: inscripción → pago → webhook → confirmación
- [ ] Definir modo produccion: cambiar `bold_ambiente` y cargar las llaves de producción (la llave provista es de pruebas)

## No bloquea el código

- Confirmar `SELECT VERSION()` en la cuenta de GoDaddy. La tabla pública dice MariaDB 10.6; en la cuenta se vio 10.11.19
- Calendario fino de comidas, si alguna de las 6 de Bucaramanga o 5 de KPL no se sirve
- Tarifa de la charla de Bogotá, hoy en 0 porque no se indicó otra
- Reenvío de invitaciones desde el admin (el admin ya puede copiar el enlace)
- Restablecimiento de clave por correo (cambiar clave requiere estar logueado)

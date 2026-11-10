# Tareas del registrador

Estado al 10 de noviembre de 2026. Lo marcado como hecho se probó. Lo demás no.

## Hecho

- [x] Modelo de dominio y reglas del ejemplo Tour 365
- [x] CodeIgniter 4.7, PHP 8.3, Compose con MariaDB 10.11
- [x] Cotización probada: 390.000, 240.000, 367.500, 283.500 y 190.000
- [x] Formulario público en `/tour-365`. El total lo calcula el servidor y se actualiza por Ajax
- [x] Sesión en MariaDB (`ci_sessions`), para que el comprobante no muera por un `chmod`
- [x] Inscripción confirmada en MariaDB (`eventos`, `inscripciones`). El recibo se abre por número: `/e/tour-365/recibo/1`
- [x] El mismo documento no abre dos inscripciones activas en el mismo evento. La segunda vez el formulario avisa y no inserta otra fila
- [x] Git en `main`. Punto anterior: `aa7c994`. Corte con la base de datos: `532ac48`
- [x] La página pública es `/e/{slug}`. `/tour-365` solo redirige. El evento sale de la fila o del JSON de semilla, no de una clase del controlador
- [x] La ficha se pinta desde el esquema del evento. El navegador exige los campos marcados como obligatorios, sin una lista fija de nombres

## Siguiente

- [ ] Login del backoffice: invitación, contraseña y roles
- [ ] Listado de inscritos, comprobantes y notas de alojamiento
- [ ] Textos de cuenta, llave Bre-b y enlace Bold por el total de cada persona
- [ ] Diseñador visual para crear otro evento sin tocar código
- [ ] Publicar en GoDaddy con el selector de PHP en 8.3

## No bloquea el código

- Confirmar `SELECT VERSION()` en la cuenta de GoDaddy. La tabla pública dice MariaDB 10.6; en la cuenta se vio 10.11.19
- Calendario fino de comidas, si alguna de las 6 de Bucaramanga o 5 de KPL no se sirve
- Tarifa de la charla de Bogotá, hoy en 0 porque no se indicó otra

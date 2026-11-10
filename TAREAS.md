# Tareas del registrador

Estado al 10 de noviembre de 2026. Esto es lo que falta, en orden.

## Hecho

- [x] Modelo de dominio y reglas del Tour 365
- [x] CodeIgniter 4.7, PHP 8.3, Compose con MariaDB 10.11
- [x] Cotización probada: 7 casos, incluidos 390.000, 240.000, 367.500 y 283.500
- [x] Formulario público en `/tour-365`. El total lo calcula el servidor. El recibo de esa visita queda en la sesión

## Siguiente

- [ ] Guardar la inscripción confirmada en MariaDB, no solo en la sesión
- [ ] Impedir dos inscripciones activas con el mismo documento en el mismo evento
- [ ] Login del backoffice: invitación, contraseña y roles
- [ ] Listado de inscritos, comprobantes y notas de alojamiento
- [ ] Textos de cuenta, llave Bre-b y enlace Bold por el total de cada persona
- [ ] Diseñador visual para crear otro evento sin tocar código
- [ ] Publicar en GoDaddy con el selector de PHP en 8.3

## No bloquea el código

- Confirmar `SELECT VERSION()` en la cuenta de GoDaddy. La tabla pública dice MariaDB 10.6; en la cuenta se vio 10.11.19
- Calendario fino de comidas, si alguna de las 6 de Bucaramanga o 5 de KPL no se sirve
- Tarifa de la charla de Bogotá, hoy en 0 porque no se indicó otra

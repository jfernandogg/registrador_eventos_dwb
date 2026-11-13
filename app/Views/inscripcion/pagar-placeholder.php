<?php
/** @var string $slug */
/** @var \App\Domain\ProgramaEvento $programa */
/** @var mixed $id */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pagar · <?= esc($programa->nombre) ?></title>
    <link rel="stylesheet" href="<?= base_url('css/publico.css') ?>">
</head>
<body>
<header class="marca-cabecera">
    <img class="logo-dwb" src="<?= base_url('dwb-logo.svg') ?>" alt="DWB Colombia">
    <p class="organizacion">Budismo Camino del Diamante Colombia</p>
</header>
<main class="contenedor" style="max-width: 520px;">
    <h1>Pagar inscripción</h1>
    <p class="subtitulo">Evento: <strong><?= esc($programa->nombre) ?></strong></p>
    <div class="alerta aviso" style="display: flex; gap: 0.8rem; align-items: flex-start; margin-top: 1rem;">
        <span style="font-size: 1.5rem;">⏳</span>
        <div>
            <strong>Integración con Bold en desarrollo.</strong>
            <p style="margin: 0.3rem 0 0;">Cuando esté lista la integración, acá se generará el enlace de pago con el monto exacto de tu inscripción. Por ahora, si tenés el total podés pagar con tarjeta en Bold o transferir a los datos de cuenta.</p>
        </div>
    </div>
    <p style="margin-top: 1rem;"><a class="boton-primario" href="<?= site_url('e/' . $slug) ?>" style="display: inline-block; text-decoration: none; padding: 0.5rem 1rem; background: var(--color-primario); color: #fff; border-radius: var(--radio);">Volver a la inscripción</a></p>
</main>
</body>
</html>
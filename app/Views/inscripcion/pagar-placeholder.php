<?php
/** @var string $slug */
/** @var \App\Domain\ProgramaEvento $programa */
/** @var mixed $id */
/** @var string $error */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pagar · <?= esc($programa->nombre) ?></title>
    <link rel="stylesheet" href="<?= base_url('css/publico.css?v=' . filemtime(FCPATH . 'css/publico.css')) ?>">
</head>
<body>
<header class="marca-cabecera">
    <img class="logo-dwb" src="<?= base_url('dwb-logo.svg') ?>" alt="DWB Colombia">
    <p class="organizacion">Budismo Camino del Diamante Colombia</p>
</header>
<main class="contenedor" style="max-width: 520px;">
    <h1>Pagar inscripción</h1>
    <p class="subtitulo">Evento: <strong><?= esc($programa->nombre) ?></strong></p>

    <?php if (($error ?? '') !== ''): ?>
        <div class="alerta error" style="display: flex; gap: 0.8rem; align-items: flex-start; margin-top: 1rem;">
            <span style="font-size: 1.5rem;">⚠️</span>
            <div>
                <strong>No se pudo crear el enlace de pago.</strong>
                <p style="margin: 0.3rem 0 0; word-break: break-all;"><?= esc($error) ?></p>
            </div>
        </div>
    <?php else: ?>
        <div class="alerta aviso" style="display: flex; gap: 0.8rem; align-items: flex-start; margin-top: 1rem;">
            <span style="font-size: 1.5rem;">⏳</span>
            <div>
                <strong>Integración con Bold pendiente de configurar.</strong>
                <p style="margin: 0.3rem 0 0;">El administrador aún no cargó las llaves de Bold. Mientras tanto podés pagar por transferencia y subir el comprobante.</p>
            </div>
        </div>
    <?php endif ?>

    <?php if ($id !== null): ?>
        <p style="margin-top: 1rem;"><a class="boton-primario" href="<?= site_url('e/' . $slug . '/recibo/' . $id) ?>" style="display: inline-block; text-decoration: none; padding: 0.5rem 1rem; background: var(--color-primario); color: #fff; border-radius: var(--radio);">Ver mi recibo</a></p>
    <?php endif ?>
    <p style="margin-top: 0.5rem;"><a class="boton" href="<?= site_url('e/' . $slug) ?>" style="display: inline-block; text-decoration: none; padding: 0.5rem 1rem; background: #fff; color: var(--color-primario); border: 1px solid var(--color-primario); border-radius: var(--radio);">Volver a la inscripción</a></p>
</main>
</body>
</html>
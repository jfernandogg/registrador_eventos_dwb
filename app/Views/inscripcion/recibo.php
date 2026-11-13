<?php
$pesos = static fn (int $monto): string => number_format($monto, 0, ',', '.');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Inscripción a <?= esc($evento) ?> · Budismo Camino del Diamante Colombia">
    <title>Recibo · <?= esc($evento) ?></title>
    <link rel="icon" href="<?= base_url('favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= base_url('css/publico.css') ?>">
</head>
<body>
<header class="marca-cabecera">
    <img class="logo-dwb" src="<?= base_url('dwb-logo.svg') ?>" alt="DWB Colombia">
    <p class="organizacion">Budismo Camino del Diamante Colombia</p>
</header>
<main class="contenedor">
    <h1><?= esc($evento) ?></h1>
    <p class="subtitulo">Inscripción número <?= esc((string) $recibo['id']) ?> de <?= esc($recibo['nombre']) ?>, documento <?= esc($recibo['documento']) ?>. Registrada el <?= esc($recibo['momento']) ?>.</p>

    <div class="recibo-caja">
        <p>Este resumen quedó congelado cuando terminaste la inscripción. Un cambio posterior del catálogo no lo reescribe.</p>
        <table class="recibo-tabla">
        <?php foreach ($recibo['lineas'] as $linea): ?>
            <tr>
                <td><?= esc($linea['descripcion']) ?></td>
                <td>$ <?= $pesos((int) $linea['subtotal']) ?></td>
            </tr>
        <?php endforeach ?>
        <tr>
            <td><strong>Total en línea</strong></td>
            <td><strong>$ <?= $pesos((int) $recibo['total']) ?></strong></td>
        </tr>
    </table>
    <p>Forma de pago: <strong><?= esc($recibo['forma']) ?></strong>.</p>
    <?php if (($recibo['comprobante'] ?? '') !== ''): ?>
        <p>Comprobante recibido: <?= esc($recibo['comprobante']) ?>.</p>
    <?php endif ?>
    <div class="recibo-nota">
        <?= $recibo['cobra'] ? 'Quedó pendiente de confirmación de tesorería.' : 'Esta forma no se cobra en el registro. El total es informativo.' ?>
    </div>
    </div>

    <?php foreach ($recibo['notas'] as $nota): ?>
        <p class="recibo-nota"><?= esc($nota) ?></p>
    <?php endforeach ?>

    <div class="acciones">
        <a class="boton-secundario" href="<?= site_url() ?>">← Volver a eventos</a>
        <a class="boton-primario" href="<?= site_url('e/' . $slug) ?>">Nueva inscripción</a>
    </div>
</main>
</body>
</html>

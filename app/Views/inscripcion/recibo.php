<?php
$pesos = static fn (int $monto): string => number_format($monto, 0, ',', '.');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recibo <?= esc($evento) ?></title>
    <style>
        body { margin: 0; font: 16px/1.45 Georgia, serif; color: #1c1915; background: #f6f1e8; }
        main { max-width: 720px; margin: 0 auto; padding: 32px 20px 64px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 8px 0; border-bottom: 1px solid #ddd4c6; }
        td:last-child { text-align: right; }
    </style>
</head>
<body>
<main>
    <h1><?= esc($evento) ?></h1>
    <p>Inscripción de <?= esc($recibo['nombre']) ?>, documento <?= esc($recibo['documento']) ?>.</p>
    <p>Este resumen quedó congelado el <?= esc($recibo['momento']) ?>. Un cambio posterior del catálogo no lo reescribe.</p>
    <table>
        <?php foreach ($recibo['lineas'] as $linea): ?>
            <tr>
                <td><?= esc($linea['descripcion']) ?></td>
                <td>$ <?= $pesos((int) $linea['subtotal']) ?></td>
            </tr>
        <?php endforeach ?>
        <tr>
            <td>Total en línea</td>
            <td>$ <?= $pesos((int) $recibo['total']) ?></td>
        </tr>
    </table>
    <p>Forma de pago: <?= esc($recibo['forma']) ?>.</p>
    <?php if (($recibo['comprobante'] ?? '') !== ''): ?>
        <p>Comprobante recibido: <?= esc($recibo['comprobante']) ?>.</p>
    <?php endif ?>
    <p><?= $recibo['cobra'] ? 'Quedó pendiente de confirmación de tesorería.' : 'Esta forma no se cobra en el registro. El total es informativo.' ?></p>
    <?php foreach ($recibo['notas'] as $nota): ?>
        <p><?= esc($nota) ?></p>
    <?php endforeach ?>
    <p><a href="<?= site_url('tour-365') ?>">Nueva inscripción</a></p>
</main>
</body>
</html>

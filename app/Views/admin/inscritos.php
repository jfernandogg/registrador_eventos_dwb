<?php
/** @var string $slug */
/** @var string $nombre */
/** @var list<array<string, mixed>> $filas */
/** @var string $ver */
$pesos = static fn (int $monto): string => number_format($monto, 0, ',', '.');
$enlace = static fn (string $filtro): string => site_url('admin/eventos/' . $slug . '/inscritos') . ($filtro === '' ? '' : '?ver=' . $filtro);
$detalleUrl = static fn (int $id): string => site_url('admin/eventos/' . $slug . '/inscritos/' . $id) . ($ver !== '' ? '?ver=' . $ver : '');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inscritos · <?= esc($nombre) ?></title>
    <link rel="stylesheet" href="<?= base_url('css/admin.css') ?>">
</head>
<body>
<header class="barra">
    <nav>
        <a class="marca" href="<?= site_url('admin') ?>">Eventos</a>
        <span class="miga">›</span>
        <span><?= esc($nombre) ?></span>
        <a href="<?= site_url('admin/eventos/' . $slug) ?>">Parametrizar</a>
        <a href="<?= site_url('e/' . $slug) ?>">Ver inscripción</a>
    </nav>
</header>
<main class="contenido" style="grid-template-columns: 1fr;">
    <section class="panel">
        <h1><?= esc($nombre) ?></h1>
        <p class="intro">El comprobante se abre con sesión. Las notas son solo de alojamiento: seguro y emergencia no salen.</p>
        <p class="filtros">
            <a class="<?= $ver === '' ? 'activo' : '' ?>" href="<?= $enlace('') ?>">Todas</a>
            <a class="<?= $ver === 'comprobante' ? 'activo' : '' ?>" href="<?= $enlace('comprobante') ?>">Con comprobante</a>
            <a class="<?= $ver === 'alojamiento' ? 'activo' : '' ?>" href="<?= $enlace('alojamiento') ?>">Con nota de alojamiento</a>
        </p>
        <?php if ($filas === []): ?>
            <div class="alerta aviso" style="display: flex; gap: 0.8rem; align-items: flex-start;">
                <span style="font-size: 1.5rem;">📋</span>
                <div>
                    <strong><?= $ver !== '' ? 'Ninguna inscripción coincide con este filtro.' : 'Aún no hay inscripciones.' ?></strong>
                    <?php if ($ver === ''): ?>
                        <p style="margin: 0.3rem 0 0;">Compartí el enlace para recibir las primeras.</p>
                    <?php endif ?>
                </div>
            </div>
        <?php else: ?>
            <table class="lista">
                <thead>
                    <tr>
                        <th>Persona</th>
                        <th>Pago</th>
                        <th>Comprobante</th>
                        <th>Alojamiento</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($filas as $fila): ?>
                    <tr class="fila-clicable" data-href="<?= $detalleUrl((int) $fila['id']) ?>" style="cursor: pointer;">
                        <td>
                            <strong><?= esc((string) $fila['nombre']) ?></strong><br>
                            <?= esc((string) $fila['documento']) ?><br>
                            <span class="ayuda"><?= esc((string) $fila['correo']) ?> · <?= esc((string) $fila['creado_en']) ?></span>
                        </td>
                        <td>
                            <?= esc((string) $fila['forma']) ?><br>
                            $ <?= $pesos((int) $fila['total']) ?><br>
                            <span class="ayuda"><?= esc((string) $fila['estado']) ?></span>
                        </td>
                        <td>
                            <?php if ($fila['comprobante'] !== null): ?>
                                <a href="<?= site_url('admin/eventos/' . $slug . '/comprobantes/' . $fila['id']) ?>" onclick="event.stopPropagation()">Abrir comprobante</a>
                            <?php else: ?>
                                <span class="ayuda">No envió soporte.</span>
                            <?php endif ?>
                        </td>
                        <td>
                            <?php if ($fila['alojamiento'] === []): ?>
                                <span class="ayuda">Sin nota.</span>
                            <?php else: ?>
                                <?php foreach ($fila['alojamiento'] as $nota): ?>
                                    <p><?= esc((string) $nota) ?></p>
                                <?php endforeach ?>
                            <?php endif ?>
                        </td>
                        <td style="text-align: center;">
                            <a class="boton claro" href="<?= $detalleUrl((int) $fila['id']) ?>" style="font-size: 0.85rem; padding: 0.3rem 0.6rem;">Ver detalle</a>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </section>
</main>
<script>
document.querySelectorAll('.fila-clicable').forEach(function(fila){fila.addEventListener('click',function(e){if(e.target.tagName==='A')return;window.location=fila.dataset.href})});
</script>
</body>
</html>
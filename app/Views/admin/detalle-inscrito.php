<?php
/** @var string $slug */
/** @var string $nombreEvento */
/** @var array<string, mixed> $detalle */
/** @var string $ver */
$pesos = static fn (int $monto): string => number_format($monto, 0, ',', '.');
$enlaceVolver = site_url('admin/eventos/' . $slug . '/inscritos') . ($ver !== '' ? '?ver=' . $ver : '');
$entrada = $detalle['entrada'] ?? [];
$cotizacion = $detalle['cotizacion'] ?? [];
$notas = $detalle['notas'] ?? [];
$cursosElegidos = array_values(array_filter(
    (array) ($entrada['cursos'] ?? []),
    static fn (mixed $codigo): bool => is_string($codigo),
));
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalle · <?= esc((string) $detalle['nombre']) ?> · <?= esc($nombreEvento) ?></title>
    <link rel="stylesheet" href="<?= base_url('css/admin.css') ?>">
</head>
<body>
<header>
    <div class="cabecera-logo">
        <a href="<?= site_url('admin') ?>"><img class="logo-dwb" src="<?= base_url('dwb-logo.svg') ?>" alt="DWB Colombia"></a>
    </div>
    <nav class="barra-nav">
        <span class="miga">›</span>
        <a href="<?= site_url('admin/eventos/' . $slug . '/inscritos') ?>"><?= esc($nombreEvento) ?></a>
        <span class="miga">›</span>
        <span><?= esc((string) $detalle['nombre']) ?></span>
    </nav>
</header>
<main class="contenido" style="grid-template-columns: 1fr;">
    <p><a class="boton claro" href="<?= $enlaceVolver ?>">← Volver a inscritos</a></p>

    <section class="panel">
        <h1><?= esc((string) $detalle['nombre']) ?></h1>
        <p class="intro">
            <?= esc((string) $detalle['documento']) ?> · <?= esc((string) $detalle['correo']) ?> ·
            Inscrito el <?= esc((string) $detalle['creado_en']) ?> ·
            <strong><?= esc((string) $detalle['estado']) ?></strong>
        </p>

        <?php if ($entrada !== []): ?>
            <h2>Datos de la ficha</h2>
            <table class="lista" style="margin-bottom: 1rem;">
                <?php foreach ($entrada as $clave => $valor): ?>
                    <?php if (in_array($clave, ['cursos', 'comidas', 'equipos', 'transporte_ida', 'transporte_regreso'], true)) { continue; } ?>
                    <?php $texto = is_array($valor) ? implode(', ', $valor) : (string) $valor; ?>
                    <?php if ($texto === '' || str_starts_with($clave, 'comprobante_')) { continue; } ?>
                    <tr>
                        <td style="font-weight: 650;"><?= esc(ucfirst(str_replace('_', ' ', $clave))) ?></td>
                        <td><?= esc($texto) ?></td>
                    </tr>
                <?php endforeach ?>
            </table>
        <?php endif ?>

        <?php $lineas = $cotizacion['lineas'] ?? []; $ajustes = $cotizacion['ajustes'] ?? []; ?>
        <h2>Cursos y cotización</h2>
        <?php if ($entrada !== []): ?>
            <p>Cursos elegidos: <strong><?= esc(implode(', ', array_map('strtoupper', $cursosElegidos)) ?: 'ninguno') ?></strong></p>
        <?php endif ?>

        <?php foreach ($cursosElegidos as $curso): ?>
            <h3 style="font-size: 1rem;">Curso <?= esc(strtoupper((string) $curso)) ?></h3>
            <table class="lista" style="margin-bottom: 0.5rem;">
                <?php $transporteIda = (string) ($entrada['transporte_ida'][$curso] ?? ''); ?>
                <?php $transporteRegreso = (string) ($entrada['transporte_regreso'][$curso] ?? ''); ?>
                <?php if ($transporteIda !== ''): ?>
                    <tr><td style="font-weight: 650;">Llegada</td><td><?= esc($transporteIda) ?><?= $transporteIda === 'curso' ? ' (' . esc((string) ($entrada['franja_ida'] ?? '')) . ')' : '' ?></td></tr>
                <?php endif ?>
                <?php if ($transporteRegreso !== ''): ?>
                    <tr><td style="font-weight: 650;">Salida</td><td><?= esc($transporteRegreso) ?><?= $transporteRegreso === 'curso' ? ' (' . esc((string) ($entrada['franja_regreso'] ?? '')) . ')' : '' ?></td></tr>
                <?php endif ?>
                <?php $comidasMarcadas = array_filter((array) ($entrada['comidas'] ?? []), static fn (mixed $c): bool => is_string($c) && str_starts_with($c, (string) $curso)); ?>
                <?php if ($comidasMarcadas !== []): ?>
                    <tr><td style="font-weight: 650;">Comidas</td><td><?= esc(implode(', ', $comidasMarcadas)) ?></td></tr>
                    <tr><td style="font-weight: 650;">Dieta</td><td><?= esc((string) ($entrada['dieta'] ?? 'sin especificar')) ?></td></tr>
                <?php endif ?>
                <?php $equiposCurso = array_filter((array) ($entrada['equipos'][$curso] ?? []), static fn (mixed $c): bool => is_string($c)); ?>
                <?php if ($equiposCurso !== []): ?>
                    <tr><td style="font-weight: 650;">Equipos</td><td><?= esc(implode(', ', $equiposCurso)) ?></td></tr>
                <?php endif ?>
                <?php $alojamiento = (string) ($entrada['alojamiento_' . $curso] ?? ''); ?>
                <?php if ($alojamiento !== ''): ?>
                    <tr><td style="font-weight: 650;">Alojamiento</td><td><?= esc($alojamiento) ?></td></tr>
                    <?php $condicion = (string) ($entrada['condicion_cama_' . $curso] ?? ''); ?>
                    <?php if ($condicion !== ''): ?><tr><td>Condición cama</td><td><?= esc($condicion) ?></td></tr><?php endif ?>
                    <?php $pernocta = (string) ($entrada['pernocta_extra_' . $curso] ?? ''); ?>
                    <?php if ($pernocta !== ''): ?><tr><td>Pernocta extra</td><td><?= esc($pernocta) ?></td></tr><?php endif ?>
                    <?php $montaje = (string) ($entrada['apoyo_montaje_' . $curso] ?? ''); ?>
                    <?php if ($montaje !== ''): ?><tr><td>Apoyo montaje</td><td><?= esc($montaje) ?></td></tr><?php endif ?>
                <?php endif ?>
            </table>
        <?php endforeach ?>

        <?php if (($lineas !== [] || $ajustes !== []) && is_array($lineas) && is_array($ajustes)): ?>
            <h3 style="font-size: 1rem;">Cotización congelada</h3>
            <table class="lista">
                <?php foreach ([...$lineas, ...$ajustes] as $linea): ?>
                    <tr>
                        <td><?= esc((string) $linea['descripcion']) ?></td>
                        <td style="text-align: right;">$ <?= $pesos((int) $linea['subtotal']) ?></td>
                    </tr>
                <?php endforeach ?>
                <tr>
                    <td><strong>Total</strong></td>
                    <td style="text-align: right;"><strong>$ <?= $pesos((int) $detalle['total']) ?></strong></td>
                </tr>
            </table>
        <?php endif ?>

        <p style="margin-top: 1rem;">Forma de pago: <strong><?= esc((string) ($cotizacion['forma'] ?? 'sin especificar')) ?></strong></p>

        <?php if ($detalle['comprobante'] !== ''): ?>
            <p><a href="<?= site_url('admin/eventos/' . $slug . '/comprobantes/' . $detalle['id']) ?>">Abrir comprobante</a></p>
        <?php endif ?>

        <?php if ($notas !== []): ?>
            <h2>Notas de alojamiento</h2>
            <?php foreach ($notas as $nota): ?>
                <p><?= esc(is_string($nota) ? $nota : json_encode($nota)) ?></p>
            <?php endforeach ?>
        <?php endif ?>
    </section>
</main>
</body>
</html>
<?php
/** @var list<array<string, mixed>> $webhooks */
$fecha = static fn (string $s): string => $s === '' ? '—' : date('d/m/Y H:i', strtotime($s));
$codigo = static fn (string $s): string => $s === '' ? '<span class="ayuda">&mdash;</span>' : '<code>' . esc($s) . '</code>';
$total = count($webhooks);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Webhooks · Backoffice</title>
    <link rel="stylesheet" href="<?= base_url('css/admin.css') ?>">
</head>
<body>
<header>
    <div class="cabecera-logo">
        <a href="<?= site_url('admin') ?>"><img class="logo-dwb" src="<?= base_url('dwb-logo.svg') ?>" alt="DWB Colombia"></a>
    </div>
    <nav class="barra-nav">
        <a href="<?= site_url('admin') ?>">Eventos</a>
        <a href="<?= site_url('admin/usuarios') ?>">Usuarios</a>
        <a href="<?= site_url('admin/cambiar-clave') ?>">Cambiar clave</a>
        <a href="<?= site_url('admin/integraciones') ?>">Integraciones</a>
        <a class="activo" href="<?= site_url('admin/webhooks') ?>">Webhooks</a>
        <a href="<?= site_url('admin/salir') ?>">Salir</a>
    </nav>
</header>
<main class="contenido" style="grid-template-columns: 1fr;">
    <section class="panel">
        <h1 style="display: flex; justify-content: space-between; align-items: baseline;">
            <span>Notificaciones de pago (webhooks)</span>
            <span style="font-size: 0.88rem; font-weight: 400; color: var(--muted);"><?= $total ?> recibidas</span>
        </h1>
        <p class="intro">Cada notificación que recibe la aplicación de la pasarela de pagos queda registrada aquí, para que el responsable pueda revisar los pagos aunque el webhook no se haya podido asociar a una inscripción.</p>

        <?php if ($webhooks === []): ?>
            <div class="alerta aviso" style="display: flex; gap: 0.8rem; align-items: flex-start;">
                <span style="font-size: 1.5rem;">📡</span>
                <div>
                    <strong>Aún no se recibió ninguna notificación.</strong>
                    <p style="margin: 0.3rem 0 0;">Cuando Bold envíe un webhook a <code><?= site_url('webhook/bold') ?></code>, aparecerá en esta lista.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="tabla-contenedor">
            <table class="hoja-datos">
                <thead>
                    <tr>
                        <th style="width: 16%;">Recibido</th>
                        <th style="width: 16%;">Evento</th>
                        <th style="width: 16%;">Pago</th>
                        <th style="width: 20%;">Link / Referencia</th>
                        <th style="width: 12%;">Inscripción</th>
                        <th style="width: 20%;">Resultado</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($webhooks as $w): ?>
                    <?php
                    $textoBadge = $w['firma_valida'] ? ($w['asociada'] ? 'Asociada' : 'Sin asociar') : 'Firma inválida';
                    $colorBadge = match (true) {
                        ! (bool) $w['firma_valida'] => '#c0392b',
                        (bool) $w['asociada'] => '#1e8e3e',
                        default => '#b7791f',
                    };
                    $fondoBadge = match (true) {
                        ! (bool) $w['firma_valida'] => '#fdecea',
                        (bool) $w['asociada'] => '#e6f4ea',
                        default => '#fdf3e0',
                    };
                    $inscripcion = $w['inscripcion_id'] ? (string) $w['inscripcion_id'] : '—';
                    ?>
                    <tr class="fila-clicable" data-href="<?= site_url('admin/webhooks/' . (int) $w['id']) ?>">
                        <td><?= esc($fecha((string) $w['recibido_en'])) ?></td>
                        <td>
                            <strong><?= esc((string) $w['tipo']) ?: '—' ?></strong>
                            <span class="ayuda"><?= esc((string) $w['proveedor']) ?></span>
                        </td>
                        <td><?= $codigo((string) $w['payment_id']) ?></td>
                        <td>
                            <?= $codigo((string) $w['payment_link']) ?>
                            <?php if ((string) $w['referencia'] !== '' && (string) $w['referencia'] !== (string) $w['payment_link']): ?>
                                <span class="ayuda">ref: <?= esc((string) $w['referencia']) ?></span>
                            <?php endif ?>
                        </td>
                        <td><?= $w['inscripcion_id'] ? '<strong>#' . esc($inscripcion) . '</strong>' : '<span class="ayuda">&mdash;</span>' ?></td>
                        <td>
                            <span style="background: <?= $fondoBadge ?>; color: <?= $colorBadge ?>; padding: 0.15rem 0.5rem; border-radius: 999px; font-size: 0.78rem; font-weight: 650; display: inline-block;"><?= esc($textoBadge) ?></span>
                            <?php if ((string) $w['payment_status'] !== ''): ?>
                                <span class="ayuda">→ <?= esc((string) $w['payment_status']) ?></span>
                            <?php endif ?>
                            <span class="ayuda" style="display:block;"><?= esc((string) $w['nota']) ?></span>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
            </div>
        <?php endif ?>
    </section>
</main>
<script>
document.querySelectorAll('.fila-clicable').forEach(function (fila) {
    fila.addEventListener('click', function (e) { if (e.target.tagName === 'A') return; window.location = fila.dataset.href; });
});
</script>
</body>
</html>
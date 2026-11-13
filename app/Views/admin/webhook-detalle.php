<?php
/** @var array<string, mixed> $webhook */
$fecha = static fn (string $s): string => $s === '' ? '—' : date('d/m/Y H:i:s', strtotime($s));
$colorBadge = match (true) {
    ! (bool) $webhook['firma_valida'] => '#c0392b',
    (bool) $webhook['asociada'] => '#1e8e3e',
    default => '#b7791f',
};
$fondoBadge = match (true) {
    ! (bool) $webhook['firma_valida'] => '#fdecea',
    (bool) $webhook['asociada'] => '#e6f4ea',
    default => '#fdf3e0',
};
$textoBadge = $webhook['firma_valida'] ? ($webhook['asociada'] ? 'Asociada' : 'Sin asociar') : 'Firma inválida';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Webhook #<?= (int) $webhook['id'] ?> · Backoffice</title>
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
            <span>Webhook #<?= (int) $webhook['id'] ?></span>
            <span style="font-size: 0.88rem; font-weight: 400; color: var(--muted);">
                <span style="background: <?= $fondoBadge ?>; color: <?= $colorBadge ?>; padding: 0.15rem 0.5rem; border-radius: 999px; font-size: 0.78rem; font-weight: 650; display: inline-block;"><?= esc($textoBadge) ?></span>
            </span>
        </h1>

        <div class="campo-grid">
            <label class="campo">Recibido<strong style="display:block;font-weight:650;"><?= esc($fecha((string) $webhook['recibido_en'])) ?></strong></label>
            <label class="campo">Proveedor<strong style="display:block;font-weight:650;"><?= esc((string) $webhook['proveedor']) ?></strong></label>
            <label class="campo">Tipo de evento<strong style="display:block;font-weight:650;"><?= esc((string) $webhook['tipo']) ?: '—' ?></strong></label>
            <label class="campo">UUID<strong style="display:block;font-weight:650;word-break:break-all;"><?= esc((string) $webhook['uuid']) ?: '—' ?></strong></label>
            <label class="campo">ID del pago<strong style="display:block;font-weight:650;"><?= esc((string) $webhook['payment_id']) ?: '—' ?></strong></label>
            <label class="campo">Link (payment_link)<strong style="display:block;font-weight:650;"><?= esc((string) $webhook['payment_link']) ?: '—' ?></strong></label>
            <label class="campo">Referencia<strong style="display:block;font-weight:650;"><?= esc((string) $webhook['referencia']) ?: '—' ?></strong></label>
            <label class="campo">Firma válida<strong style="display:block;font-weight:650;"><?= (bool) $webhook['firma_valida'] ? 'Sí' : 'No' ?></strong></label>
            <?php if ($webhook['inscripcion_id']): ?>
                <label class="campo">Inscripción asociada<strong style="display:block;font-weight:650;">#<?= (int) $webhook['inscripcion_id'] ?></strong></label>
            <?php endif ?>
            <?php if ((string) $webhook['payment_status'] !== ''): ?>
                <label class="campo">Nuevo estado de pago<strong style="display:block;font-weight:650;"><?= esc((string) $webhook['payment_status']) ?></strong></label>
            <?php endif ?>
            <label class="campo">Nota<strong style="display:block;font-weight:650;"><?= esc((string) $webhook['nota']) ?: '—' ?></strong></label>
        </div>
    </section>

    <section class="panel">
        <h2>Contenido recibido</h2>
        <p class="intro">Envío original de la pasarela, tal como llegó al webhook.</p>
        <?php if ($webhook['payload'] !== []): ?>
            <pre style="max-height: 28rem; overflow: auto; background: var(--fallo, #f7f7f7); border: 1px solid var(--linea); border-radius: 0.4rem; padding: 0.8rem; font-size: 0.8rem; white-space: pre-wrap; word-break: break-word;"><?= esc(json_encode($webhook['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
        <?php else: ?>
            <p class="ayuda">No se guardó contenido (JSON no válido).</p>
        <?php endif ?>
    </section>

    <p style="margin-top: 1rem;">
        <a class="boton claro" href="<?= site_url('admin/webhooks') ?>">&larr; Volver a la lista</a>
    </p>
</main>
</body>
</html>
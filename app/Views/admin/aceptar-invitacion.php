<?php
/** @var string $token */
/** @var bool $tokenValido */
/** @var string $nombre */
/** @var string $error */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aceptar invitación</title>
    <link rel="stylesheet" href="<?= base_url('css/admin.css') ?>">
</head>
<body>
<main class="contenido" style="grid-template-columns: 1fr; max-width: 440px;">
    <section class="panel">
        <h1>Aceptar invitación</h1>
        <?php if ($error !== ''): ?><p class="alerta error"><?= esc($error) ?></p><?php endif ?>
        <?php if (! $tokenValido): ?>
            <p>Este enlace ya no es válido. Puede que haya expirado o ya fue usado. Pedile a un administrador que te genere una nueva invitación.</p>
        <?php else: ?>
            <p>Hola <strong><?= esc($nombre) ?></strong>, elegí tu clave para acceder al backoffice.</p>
            <?= form_open('/admin/aceptar-invitacion') ?>
                <input type="hidden" name="token" value="<?= esc($token) ?>">
                <label class="campo">Clave (mínimo 6 caracteres) <input name="clave" type="password" required minlength="6"></label>
                <button class="boton" type="submit">Crear clave y activar cuenta</button>
            <?= form_close() ?>
        <?php endif ?>
    </section>
</main>
</body>
</html>
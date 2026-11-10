<?php
/** @var string $error */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar</title>
    <link rel="stylesheet" href="<?= base_url('css/admin.css') ?>">
</head>
<body>
<main class="contenido" style="grid-template-columns: 1fr; max-width: 440px;">
    <section class="panel">
    <h1>Backoffice</h1>
        <?php if ($error !== ''): ?><p class="alerta error"><?= esc($error) ?></p><?php endif ?>
        <?= form_open('/admin/entrar') ?>
        <label class="campo">Correo <input name="correo" type="email" required></label>
        <label class="campo">Clave <input name="clave" type="password" required>
            <p class="ayuda">Puerta temporal del backoffice. El sistema de invitación y roles todavía no está.</p>
        </label>
        <button class="boton" type="submit">Entrar</button>
    <?= form_close() ?>
</main>
</body>
</html>

<?php
/** @var string $error */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cambiar clave</title>
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
        <a class="activo" href="<?= site_url('admin/cambiar-clave') ?>">Cambiar clave</a>
        <a href="<?= site_url('admin/salir') ?>">Salir</a>
    </nav>
</header>
<main class="contenido" style="grid-template-columns: 1fr; max-width: 440px;">
    <section class="panel">
        <h1>Cambiar clave</h1>
        <?php if ($error !== ''): ?><p class="alerta error"><?= esc($error) ?></p><?php endif ?>
        <?= form_open('/admin/cambiar-clave') ?>
            <label class="campo">Clave actual <input name="clave_actual" type="password" required></label>
            <label class="campo">Clave nueva (mínimo 6 caracteres) <input name="clave_nueva" type="password" required minlength="6"></label>
            <button class="boton" type="submit">Actualizar clave</button>
        <?= form_close() ?>
    </section>
</main>
</body>
</html>
<?php
/** @var list<array{slug: string, nombre: string}> $eventos */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Eventos</title>
    <link rel="stylesheet" href="<?= base_url('css/admin.css') ?>">
</head>
<body>
<header>
    <div class="cabecera-logo">
        <a href="<?= site_url('admin') ?>"><img class="logo-dwb" src="<?= base_url('dwb-logo.svg') ?>" alt="DWB Colombia"></a>
    </div>
    <nav class="barra-nav">
        <a class="activo" href="<?= site_url('admin') ?>">Lista</a>
        <a href="<?= site_url('admin/usuarios') ?>">Usuarios</a>
        <a href="<?= site_url('admin/cambiar-clave') ?>">Cambiar clave</a>
        <a href="<?= site_url('admin/integraciones') ?>">Integraciones</a>
        <a href="<?= site_url('admin/salir') ?>">Salir</a>
    </nav>
</header>
<main class="contenido" style="grid-template-columns: 1fr; max-width: 760px;">
    <p><a class="boton" href="<?= site_url('admin/eventos/nuevo') ?>">Nuevo evento</a></p>
    <section class="panel">
        <h1>Eventos</h1>
        <p class="intro">Cada evento es un punto de inscripción propio. El enlace público es <code>/e/lo-que-elijas</code>.</p>
        <?php if ($eventos === []): ?>
            <div class="alerta aviso" style="display: flex; gap: 0.8rem; align-items: flex-start;">
                <span style="font-size: 1.5rem;">🎪</span>
                <div>
                    <strong>No hay eventos todavía.</strong>
                    <p style="margin: 0.3rem 0 0;">Creá el primero con el botón «Nuevo evento».</p>
                </div>
            </div>
        <?php else: ?>
            <ul class="lista-eventos-admin">
                <?php foreach ($eventos as $evento): ?>
                    <li class="fila-evento">
                        <a class="nombre-evento" href="<?= site_url('admin/eventos/' . $evento['slug']) ?>"><?= esc($evento['nombre']) ?></a>
                        <nav class="acciones-evento">
                            <a href="<?= site_url('admin/eventos/' . $evento['slug'] . '/inscritos') ?>">Inscritos</a>
                            <a href="<?= site_url('admin/eventos/' . $evento['slug']) ?>">Parametrizar</a>
                            <a href="<?= site_url('e/' . $evento['slug']) ?>">Ver inscripción</a>
                        </nav>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </section>
</main>
</body>
</html>
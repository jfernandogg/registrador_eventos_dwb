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
<main class="contenido" style="grid-template-columns: 1fr; max-width: 720px;">
    <section class="panel">
    <p><a class="boton claro" href="<?= site_url('admin/eventos/nuevo') ?>">Nuevo evento</a> · <a href="<?= site_url('admin/salir') ?>">Salir</a></p>
    <h1>Eventos</h1>
    <ul>
        <?php foreach ($eventos as $evento): ?>
            <li>
                <a href="<?= site_url('admin/eventos/' . $evento['slug']) ?>"><?= esc($evento['nombre']) ?></a>
                · <a href="<?= site_url('admin/eventos/' . $evento['slug'] . '/inscritos') ?>">Inscritos</a>
                · <a href="<?= site_url('admin/eventos/' . $evento['slug']) ?>">Parametrizar</a>
                · <a href="<?= site_url('e/' . $evento['slug']) ?>">ver inscripción</a>
            </li>
        <?php endforeach ?>
    </ul>
    </section>
</main>
</body>
</html>

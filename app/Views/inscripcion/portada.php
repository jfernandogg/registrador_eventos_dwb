<?php
/** @var list<array{slug: string, nombre: string}> $eventos */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Inscripciones a eventos de Budismo Camino del Diamante Colombia">
    <title>Inscripciones</title>
    <link rel="icon" href="<?= base_url('favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= base_url('css/publico.css') ?>">
</head>
<body>
<header class="marca-cabecera">
    <img class="logo-dwb" src="<?= base_url('dwb-logo.svg') ?>" alt="DWB Colombia">
    <p class="organizacion">Budismo Camino del Diamante Colombia</p>
</header>
<main class="contenedor">
    <h1>Inscripciones</h1>
    <p class="subtitulo">Elegí el evento en el que querés participar. Cada inscripción es individual: si venís en familia, llená una por persona.</p>
    <?php if ($eventos === []): ?>
        <p class="recibo-nota">No hay eventos abiertos ahora. Volvé pronto o escribí a tu centro.</p>
    <?php else: ?>
        <ul class="lista-eventos">
            <?php foreach ($eventos as $evento): ?>
                <li>
                    <a class="tarjeta-evento" href="<?= site_url('e/' . $evento['slug']) ?>">
                        <h2><?= esc($evento['nombre']) ?></h2>
                        <?php if (($evento['descripcion'] ?? '') !== ''): ?>
                            <p><?= esc($evento['descripcion']) ?></p>
                        <?php endif ?>
                    </a>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</main>
</body>
</html>

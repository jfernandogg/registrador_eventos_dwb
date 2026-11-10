<?php
/** @var list<array{slug: string, nombre: string}> $eventos */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Eventos</title>
    <style>
        body { margin: 0; font: 16px/1.45 Georgia, serif; background: #f6f1e8; color: #1c1915; }
        main { max-width: 760px; margin: 0 auto; padding: 32px 20px; }
        a { color: inherit; }
    </style>
</head>
<body>
<main>
    <p><a href="<?= site_url('admin/eventos/nuevo') ?>">Nuevo evento</a> · <a href="<?= site_url('admin/salir') ?>">Salir</a></p>
    <h1>Eventos</h1>
    <ul>
        <?php foreach ($eventos as $evento): ?>
            <li>
                <a href="<?= site_url('admin/eventos/' . $evento['slug']) ?>"><?= esc($evento['nombre']) ?></a>
                · <a href="<?= site_url('e/' . $evento['slug']) ?>">ver inscripción</a>
            </li>
        <?php endforeach ?>
    </ul>
</main>
</body>
</html>

<?php
/** @var list<array{slug: string, nombre: string}> $eventos */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inscripciones</title>
    <style>
        body { margin: 0; font: 16px/1.45 Georgia, serif; color: #1c1915; background: #f6f1e8; }
        main { max-width: 720px; margin: 0 auto; padding: 32px 20px 64px; }
        a { color: inherit; }
    </style>
</head>
<body>
<main>
    <h1>Inscripciones</h1>
    <?php if ($eventos === []): ?>
        <p>No hay eventos publicados.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($eventos as $evento): ?>
                <li><a href="<?= site_url('e/' . $evento['slug']) ?>"><?= esc($evento['nombre']) ?></a></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</main>
</body>
</html>

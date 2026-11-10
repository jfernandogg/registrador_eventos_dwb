<?php
/** @var string $error */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Entrar</title>
    <style>
        body { margin: 0; font: 16px/1.45 Georgia, serif; background: #f6f1e8; color: #1c1915; }
        main { max-width: 420px; margin: 48px auto; padding: 24px; background: #fffdf9; border: 1px solid #ddd4c6; }
        label { display: grid; gap: 4px; margin: 12px 0; }
        input, button { font: inherit; padding: 8px; }
        button { background: #1c1915; color: #f6f1e8; border: 0; }
        .error { color: #8a2b2b; }
    </style>
</head>
<body>
<main>
    <h1>Backoffice</h1>
    <?php if ($error !== ''): ?><p class="error"><?= esc($error) ?></p><?php endif ?>
    <?= form_open('/admin/entrar') ?>
        <label>Correo <input name="correo" type="email" required></label>
        <label>Clave <input name="clave" type="password" required></label>
        <button type="submit">Entrar</button>
    <?= form_close() ?>
</main>
</body>
</html>

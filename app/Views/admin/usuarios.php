<?php
/** @var list<array<string, mixed>> $usuarios */
/** @var string $error */
/** @var string $ok */
$roles = ['admin' => 'Admin', 'editor' => 'Editor', 'tesorero' => 'Tesorero', 'invitado' => 'Invitado'];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuarios · Backoffice</title>
    <link rel="stylesheet" href="<?= base_url('css/admin.css') ?>">
</head>
<body>
<header>
    <div class="cabecera-logo">
        <a href="<?= site_url('admin') ?>"><img class="logo-dwb" src="<?= base_url('dwb-logo.svg') ?>" alt="DWB Colombia"></a>
    </div>
    <nav class="barra-nav">
        <a href="<?= site_url('admin') ?>">Eventos</a>
        <a class="activo" href="<?= site_url('admin/usuarios') ?>">Usuarios</a>
        <a href="<?= site_url('admin/cambiar-clave') ?>">Cambiar clave</a>
        <a href="<?= site_url('admin/salir') ?>">Salir</a>
    </nav>
</header>
<main class="contenido" style="grid-template-columns: 1fr;">
    <section class="panel">
        <h1>Usuarios</h1>
        <?php if ($error !== ''): ?><p class="alerta error" style="word-break: break-all;"><?= esc($error) ?></p><?php endif ?>
        <?php if ($ok !== ''): ?><p class="alerta ok" style="word-break: break-all;"><?= esc($ok) ?></p><?php endif ?>

        <details style="margin-bottom: 1rem;">
            <summary style="cursor: pointer; font-weight: 650; color: var(--primario);">Invitar usuario</summary>
            <?= form_open('/admin/usuarios/crear', ['style' => 'margin-top: 0.75rem;']) ?>
                <label class="campo">Nombre <input name="nombre" required placeholder="Juan Pérez"></label>
                <label class="campo">Correo <input name="correo" type="email" required placeholder="juan@ejemplo.com"></label>
                <label class="campo">Rol
                    <select name="rol">
                        <option value="invitado">Invitado (solo lectura)</option>
                        <option value="editor">Editor (parametriza eventos)</option>
                        <option value="tesorero">Tesorero (ve inscritos y comprobantes)</option>
                        <option value="admin">Admin (acceso completo)</option>
                    </select>
                </label>
                <button class="boton" type="submit">Crear y generar invitación</button>
            <?= form_close() ?>
        </details>

        <table class="hoja-datos">
            <thead>
                <tr>
                    <th style="width: 25%;">Nombre</th>
                    <th style="width: 30%;">Correo</th>
                    <th style="width: 13%;">Rol</th>
                    <th style="width: 8%;">Activo</th>
                    <th style="width: 24%; text-align: right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td>
                        <strong><?= esc((string) $u['nombre']) ?></strong>
                        <span class="ayuda"><?= esc((string) ($u['creado_en'] ?? '')) ?></span>
                    </td>
                    <td>
                        <?= esc((string) $u['correo']) ?>
                        <?php if (($u['token'] ?? null) !== null): ?>
                            <span class="ayuda" style="color: #e67e22;">Pendiente de aceptar invitación</span>
                        <?php endif ?>
                    </td>
                    <td>
                        <?= form_open('/admin/usuarios/' . (int) $u['id'] . '/rol', ['style' => 'display: inline;']) ?>
                            <select name="rol" onchange="this.form.submit()" style="font-size: 0.82rem; padding: 0.2rem 0.3rem;">
                                <?php foreach ($roles as $codigo => $etiqueta): ?>
                                    <option value="<?= $codigo ?>" <?= ((string) ($u['rol'] ?? 'invitado')) === $codigo ? 'selected' : '' ?>><?= $etiqueta ?></option>
                                <?php endforeach ?>
                            </select>
                        <?= form_close() ?>
                    </td>
                    <td style="text-align: center;">
                        <?= empty($u['activo']) ? '<span style="color: var(--muted);">No</span>' : '<span style="color: #27ae60;">Sí</span>' ?>
                    </td>
                    <td class="celda-detalle">
                        <?= form_open('/admin/usuarios/' . (int) $u['id'] . '/toggle', ['style' => 'display: inline;']) ?>
                            <button class="boton claro" type="submit" style="font-size: 0.78rem; padding: 0.25rem 0.5rem;">
                                <?= empty($u['activo']) ? 'Activar' : 'Desactivar' ?>
                            </button>
                        <?= form_close() ?>
                        <?php if (($u['token'] ?? null) !== null): ?>
                            <button class="boton claro" onclick="navigator.clipboard.writeText('<?= site_url('admin/aceptar-invitacion?token=' . $u['token']) ?>'); this.textContent='Copiado'" style="font-size: 0.78rem; padding: 0.25rem 0.5rem; margin-left: 0.3rem;">Copiar enlace</button>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
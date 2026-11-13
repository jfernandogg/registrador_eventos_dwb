<?php
/** @var array<string, string> $valores */
/** @var string $error */
/** @var string $ok */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Integraciones · Backoffice</title>
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
        <a href="<?= site_url('admin/cambiar-clave') ?>">Cambiar clave</a>
        <a class="activo" href="<?= site_url('admin/integraciones') ?>">Integraciones</a>
        <a href="<?= site_url('admin/salir') ?>">Salir</a>
    </nav>
</header>
<main class="contenido" style="grid-template-columns: 1fr;">
    <section class="panel">
        <h1>Integraciones</h1>
        <?php if ($error !== ''): ?><p class="alerta error" style="word-break: break-all;"><?= esc($error) ?></p><?php endif ?>
        <?php if ($ok !== ''): ?><p class="alerta ok" style="word-break: break-all;"><?= esc($ok) ?></p><?php endif ?>

        <h2>Correo</h2>
        <p class="intro">Configurá los parámetros del servidor de correo para invitaciones y notificaciones. Si dejás un campo vacío, se usa el valor por defecto del archivo <code>app/Config/Email.php</code> (o de <code>.env</code>).</p>

        <div class="campo-grid">
            <label class="campo">Protocolo
                <select name="correo_protocolo" form="form-correo">
                    <option value="smtp" <?= $valores['correo_protocolo'] === 'smtp' ? 'selected' : '' ?>>SMTP</option>
                    <option value="mail" <?= $valores['correo_protocolo'] === 'mail' ? 'selected' : '' ?>>PHP mail()</option>
                    <option value="sendmail" <?= $valores['correo_protocolo'] === 'sendmail' ? 'selected' : '' ?>>Sendmail</option>
                </select>
            </label>
            <label class="campo">Servidor SMTP
                <input name="correo_smtp_host" form="form-correo" value="<?= esc($valores['correo_smtp_host']) ?>" placeholder="smtp.tudominio.com.co">
            </label>
            <label class="campo">Puerto SMTP
                <input name="correo_smtp_puerto" form="form-correo" inputmode="numeric" value="<?= esc($valores['correo_smtp_puerto']) ?>" placeholder="587">
            </label>
            <label class="campo">Cifrado
                <select name="correo_smtp_cripto" form="form-correo">
                    <option value="" <?= $valores['correo_smtp_cripto'] === '' ? 'selected' : '' ?>>Ninguno</option>
                    <option value="tls" <?= $valores['correo_smtp_cripto'] === 'tls' ? 'selected' : '' ?>>TLS</option>
                    <option value="ssl" <?= $valores['correo_smtp_cripto'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                </select>
            </label>
            <label class="campo">Usuario SMTP
                <input name="correo_smtp_usuario" form="form-correo" value="<?= esc($valores['correo_smtp_usuario']) ?>" placeholder="correo@tudominio.com.co">
            </label>
            <label class="campo">Clave SMTP
                <input name="correo_smtp_clave" form="form-correo" type="password" value="<?= esc($valores['correo_smtp_clave']) ?>" placeholder="••••••••">
            </label>
            <label class="campo">Remitente: correo
                <input name="correo_from_email" form="form-correo" type="email" value="<?= esc($valores['correo_from_email']) ?>" placeholder="no-reply@tudominio.com.co">
            </label>
            <label class="campo">Remitente: nombre
                <input name="correo_from_nombre" form="form-correo" value="<?= esc($valores['correo_from_nombre']) ?>" placeholder="DWB Colombia">
            </label>
        </div>

        <p style="margin-top: 1rem;">
            <button class="boton-primario" type="submit" form="form-correo">Guardar configuración</button>
        </p>
    </section>

    <section class="panel" id="probar-correo">
        <h2>Probar envío</h2>
        <p class="intro">Mandá un correo de prueba a una dirección para verificar que la configuración funciona.</p>
        <p style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
            <input name="destino" form="form-probar" type="email" placeholder="tucorreo@ejemplo.com" style="flex: 1; min-width: 240px; padding: 0.5rem 0.6rem; border: 1px solid var(--linea); border-radius: 0.3rem; font: inherit;">
            <button class="boton" type="submit" form="form-probar">Enviar prueba</button>
        </p>
    </section>
</main>

<form id="form-correo" action="<?= site_url('admin/integraciones/correo') ?>" method="post" style="display: none;"></form>
<form id="form-probar" action="<?= site_url('admin/integraciones/correo/probar') ?>" method="post" style="display: none;"></form>
</body>
</html>
<?php
/** @var string $slug */
/** @var string $nombre */
/** @var list<array<string, mixed>> $filas */
/** @var string $ver */
$pesos = static fn (int $monto): string => number_format($monto, 0, ',', '.');
$enlace = static fn (string $filtro): string => site_url('admin/eventos/' . $slug . '/inscritos') . ($filtro === '' ? '' : '?ver=' . $filtro);
$detalleUrl = static fn (int $id): string => site_url('admin/eventos/' . $slug . '/inscritos/' . $id) . ($ver !== '' ? '?ver=' . $ver : '');
$exportarUrl = site_url('admin/eventos/' . $slug . '/exportar');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inscritos · <?= esc($nombre) ?></title>
    <link rel="stylesheet" href="<?= base_url('css/admin.css') ?>">
</head>
<body>
<header class="barra">
    <nav>
        <a class="marca" href="<?= site_url('admin') ?>"><img class="logo-dwb" src="<?= base_url('dwb-logo.svg') ?>" alt=""></a>
        <span class="miga">›</span>
        <span><?= esc($nombre) ?></span>
        <a href="<?= site_url('admin/eventos/' . $slug) ?>">Parametrizar</a>
        <a href="<?= site_url('e/' . $slug) ?>">Ver inscripción</a>
    </nav>
</header>
<main class="contenido" style="grid-template-columns: 1fr;">
    <section class="panel">
        <h1><?= esc($nombre) ?></h1>
        <p class="intro">El comprobante se abre con sesión. Las notas son solo de alojamiento: seguro y emergencia no salen.</p>
        <p class="filtros">
            <a class="<?= $ver === '' ? 'activo' : '' ?>" href="<?= $enlace('') ?>">Todas</a>
            <a class="<?= $ver === 'comprobante' ? 'activo' : '' ?>" href="<?= $enlace('comprobante') ?>">Con comprobante</a>
            <a class="<?= $ver === 'alojamiento' ? 'activo' : '' ?>" href="<?= $enlace('alojamiento') ?>">Con nota de alojamiento</a>
            <button class="boton claro" id="btn-exportar" style="margin-left: 0.6rem;">Exportar CSV</button>
        </p>
        <?php if ($filas === []): ?>
            <div class="alerta aviso" style="display: flex; gap: 0.8rem; align-items: flex-start;">
                <span style="font-size: 1.5rem;">📋</span>
                <div>
                    <strong><?= $ver !== '' ? 'Ninguna inscripción coincide con este filtro.' : 'Aún no hay inscripciones.' ?></strong>
                    <?php if ($ver === ''): ?>
                        <p style="margin: 0.3rem 0 0;">Compartí el enlace para recibir las primeras.</p>
                    <?php endif ?>
                </div>
            </div>
        <?php else: ?>
            <table class="lista">
                <thead>
                    <tr>
                        <th>Persona</th>
                        <th>Pago</th>
                        <th>Comprobante</th>
                        <th>Alojamiento</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($filas as $fila): ?>
                    <tr class="fila-clicable" data-href="<?= $detalleUrl((int) $fila['id']) ?>" style="cursor: pointer;">
                        <td>
                            <strong><?= esc((string) $fila['nombre']) ?></strong><br>
                            <?= esc((string) $fila['documento']) ?><br>
                            <span class="ayuda"><?= esc((string) $fila['correo']) ?> · <?= esc((string) $fila['creado_en']) ?></span>
                        </td>
                        <td>
                            <?= esc((string) $fila['forma']) ?><br>
                            $ <?= $pesos((int) $fila['total']) ?><br>
                            <span class="ayuda"><?= esc((string) $fila['estado']) ?></span>
                        </td>
                        <td>
                            <?php if ($fila['comprobante'] !== null): ?>
                                <a href="<?= site_url('admin/eventos/' . $slug . '/comprobantes/' . $fila['id']) ?>" onclick="event.stopPropagation()">Abrir comprobante</a>
                            <?php else: ?>
                                <span class="ayuda">No envió soporte.</span>
                            <?php endif ?>
                        </td>
                        <td>
                            <?php if ($fila['alojamiento'] === []): ?>
                                <span class="ayuda">Sin nota.</span>
                            <?php else: ?>
                                <?php foreach ($fila['alojamiento'] as $nota): ?>
                                    <p><?= esc((string) $nota) ?></p>
                                <?php endforeach ?>
                            <?php endif ?>
                        </td>
                        <td style="text-align: center;">
                            <a class="boton claro" href="<?= $detalleUrl((int) $fila['id']) ?>" style="font-size: 0.85rem; padding: 0.3rem 0.6rem;">Ver detalle</a>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </section>
    </main>

    <div class="modal-fondo si-oculto" id="modal-exportar">
        <div class="modal-caja">
            <h2>Exportar a CSV</h2>
            <label class="campo">Separador de campo
                <select id="sep-csv">
                    <option value=",">Coma (,)</option>
                    <option value=";">Punto y coma (;)</option>
                    <option value="\t">Tabulacion</option>
                </select>
            </label>
            <label class="campo">Encerrador de valores
                <select id="enc-csv">
                    <option value="&quot;">Comillas dobles ("...")</option>
                    <option value="'">Comillas simples ('...')</option>
                    <option value="">Ninguno</option>
                </select>
            </label>
            <label class="check" style="margin-top: 0.5rem;">
                <input type="checkbox" id="inc-comprobantes"> Incluir comprobantes (genera un ZIP con el CSV y los archivos de soporte)
            </label>
            <p style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                <button class="boton-primario" id="btn-descargar">Descargar</button>
                <button class="boton claro" id="btn-cerrar-modal">Cancelar</button>
            </p>
        </div>
    </div>

    <script>
    document.querySelectorAll('.fila-clicable').forEach(function(fila){fila.addEventListener('click',function(e){if(e.target.tagName==='A')return;window.location=fila.dataset.href})});

    (function(){
        var modal = document.getElementById('modal-exportar');
        var btnAbrir = document.getElementById('btn-exportar');
        var btnCerrar = document.getElementById('btn-cerrar-modal');
        var sep = document.getElementById('sep-csv');
        var enc = document.getElementById('enc-csv');
        var baseUrl = <?= json_encode($exportarUrl) ?>;

        btnAbrir.addEventListener('click', function(){ modal.classList.remove('si-oculto'); });
        btnCerrar.addEventListener('click', function(){ modal.classList.add('si-oculto'); });
        modal.addEventListener('click', function(e){ if (e.target === modal) modal.classList.add('si-oculto'); });
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape') modal.classList.add('si-oculto'); });

        document.getElementById('btn-descargar').addEventListener('click', function(){
            var comp = document.getElementById('inc-comprobantes').checked ? '&comprobantes=1' : '';
            window.location = baseUrl + '?sep=' + encodeURIComponent(sep.value) + '&enc=' + encodeURIComponent(enc.value) + comp;
        });
    })();
    </script>
    </body>
    </html>
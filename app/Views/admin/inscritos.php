<?php
/** @var string $slug */
/** @var string $nombre */
/** @var list<array<string, mixed>> $filas */
/** @var string $ver */
$pesos = static fn (int $monto): string => number_format($monto, 0, ',', '.');
$enlace = static fn (string $filtro): string => site_url('admin/eventos/' . $slug . '/inscritos') . ($filtro === '' ? '' : '?ver=' . $filtro);
$detalleUrl = static fn (int $id): string => site_url('admin/eventos/' . $slug . '/inscritos/' . $id) . ($ver !== '' ? '?ver=' . $ver : '');
$exportarUrl = site_url('admin/eventos/' . $slug . '/exportar');
$total = count($filas);
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
<header>
    <div class="cabecera-logo">
        <a href="<?= site_url('admin') ?>"><img class="logo-dwb" src="<?= base_url('dwb-logo.svg') ?>" alt="DWB Colombia"></a>
    </div>
    <nav class="barra-nav">
        <span class="miga">›</span>
        <span><?= esc($nombre) ?></span>
        <a href="<?= site_url('admin/eventos/' . $slug) ?>">Parametrizar</a>
        <a href="<?= site_url('e/' . $slug) ?>">Ver inscripción</a>
    </nav>
</header>
<main class="contenido" style="grid-template-columns: 1fr;">
    <section class="panel">
        <h1 style="display: flex; justify-content: space-between; align-items: baseline;">
            <span><?= esc($nombre) ?></span>
            <span style="font-size: 0.88rem; font-weight: 400; color: var(--muted);"><?= $total ?> inscripciones</span>
        </h1>
        <div class="barra-tabla">
            <p class="filtros">
                <a class="<?= $ver === '' ? 'activo' : '' ?>" href="<?= $enlace('') ?>">Todas</a>
                <a class="<?= $ver === 'comprobante' ? 'activo' : '' ?>" href="<?= $enlace('comprobante') ?>">Con comprobante</a>
                <a class="<?= $ver === 'alojamiento' ? 'activo' : '' ?>" href="<?= $enlace('alojamiento') ?>">Con nota de alojamiento</a>
                <button class="boton claro" id="btn-exportar" style="margin-left: 0.6rem;">Exportar CSV</button>
            </p>
            <div class="paginacion-superior" id="paginacion-top"></div>
        </div>
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
            <div class="tabla-contenedor">
            <table class="hoja-datos" id="tabla-inscritos">
                <thead>
                    <tr>
                        <th style="width: 28%;">Persona</th>
                        <th style="width: 18%;">Pago</th>
                        <th style="width: 14%;">Comprobante</th>
                        <th style="width: 24%;">Alojamiento</th>
                        <th style="width: 16%; text-align: right;">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($filas as $fila): ?>
                    <tr class="fila-clicable" data-href="<?= $detalleUrl((int) $fila['id']) ?>">
                        <td>
                            <strong><?= esc((string) $fila['nombre']) ?></strong>
                            <span class="ayuda"><?= esc((string) $fila['documento']) ?></span>
                            <span class="ayuda"><?= esc((string) $fila['correo']) ?> &middot; <?= esc((string) ($fila['creado_en'] ?? '')) ?></span>
                        </td>
                        <td>
                            <strong>$ <?= $pesos((int) $fila['total']) ?></strong>
                            <span class="ayuda"><?= esc((string) $fila['forma']) ?> — <?= esc((string) $fila['estado']) ?></span>
                        </td>
                        <td>
                            <?php if ($fila['comprobante'] !== null): ?>
                                <a class="enlace-comprobante" href="<?= site_url('admin/eventos/' . $slug . '/comprobantes/' . $fila['id']) ?>" onclick="event.stopPropagation()">Abrir</a>
                            <?php else: ?>
                                <span class="sin-comprobante">&mdash;</span>
                            <?php endif ?>
                        </td>
                        <td>
                            <?php if ($fila['alojamiento'] === []): ?>
                                <span class="ayuda">&mdash;</span>
                            <?php else: ?>
                                <?= esc(implode(' &middot; ', $fila['alojamiento'])) ?>
                            <?php endif ?>
                        </td>
                        <td class="celda-detalle">
                            <a class="boton claro" href="<?= $detalleUrl((int) $fila['id']) ?>">Ver detalle</a>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
            </div>
        <?php endif ?>

        <nav class="paginacion" id="paginacion-inferior" aria-label="Paginación"></nav>
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
        <script>
        // Paginacion — se ejecuta despues del DOM listo
        (function(){
            var POR_PAGINA = 15;
            var tabla = document.getElementById('tabla-inscritos');
            if (!tabla) return;
            var filas = Array.from(tabla.querySelectorAll('tbody tr'));
            if (filas.length <= POR_PAGINA) return;

            var pagActual = 1;
            var totalPaginas = Math.ceil(filas.length / POR_PAGINA);

            function mostrarPagina(pag) {
                pagActual = pag;
                var inicio = (pag - 1) * POR_PAGINA;
                var fin = inicio + POR_PAGINA;
                filas.forEach(function(f, i) { f.style.display = (i >= inicio && i < fin) ? '' : 'none'; });
                renderizarControles();
            }

            function renderizarControles() {
                var html = '<span class=\"paginacion-info\">' + pagActual + ' de ' + totalPaginas + '</span>';
                html += '<button class=\"paginacion-btn\"' + (pagActual === 1 ? ' disabled' : '') + ' data-pag=\"1\">&laquo;</button>';
                html += '<button class=\"paginacion-btn\"' + (pagActual === 1 ? ' disabled' : '') + ' data-pag=\"' + (pagActual - 1) + '\">&lsaquo;</button>';

                var desde = Math.max(1, pagActual - 2);
                var hasta = Math.min(totalPaginas, pagActual + 2);
                for (var p = desde; p <= hasta; p++) {
                    html += '<button class=\"paginacion-btn' + (p === pagActual ? ' activo' : '') + '\" data-pag=\"' + p + '\">' + p + '</button>';
                }

                html += '<button class=\"paginacion-btn\"' + (pagActual === totalPaginas ? ' disabled' : '') + ' data-pag=\"' + (pagActual + 1) + '\">&rsaquo;</button>';
                html += '<button class=\"paginacion-btn\"' + (pagActual === totalPaginas ? ' disabled' : '') + ' data-pag=\"' + totalPaginas + '\">&raquo;</button>';

                document.getElementById('paginacion-inferior').innerHTML = html;
                var topEl = document.getElementById('paginacion-top');
                if (topEl) topEl.innerHTML = '<span class=\"paginacion-resumen\">' + ((pagActual - 1) * POR_PAGINA + 1) + '\u2013' + Math.min(pagActual * POR_PAGINA, filas.length) + ' de ' + filas.length + '</span>';
            }

            document.getElementById('paginacion-inferior').addEventListener('click', function(e) {
                var btn = e.target.closest('.paginacion-btn');
                if (btn && !btn.disabled) { mostrarPagina(parseInt(btn.dataset.pag)); }
            });

            mostrarPagina(1);
        })();
        </script>
        </body>
        </html>
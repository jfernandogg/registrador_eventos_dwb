<?php

/** @var \App\Domain\ProgramaEvento $programa */
/** @var array<string, mixed> $entrada */
/** @var array<string, string> $errores */
/** @var \App\Domain\Cotizacion|null $cotizacion */
/** @var list<string> $notas */

$valor = static fn (string $campo): string => esc((string) ($entrada[$campo] ?? ''));
$elegido = static function (string $campo, string $codigo) use ($entrada): string {
    $valores = $entrada[$campo] ?? [];

    return is_array($valores) && in_array($codigo, $valores, true) ? 'checked' : '';
};
$modo = static function (string $campo, string $curso) use ($entrada): string {
    $valores = $entrada[$campo] ?? [];

    return is_array($valores) ? (string) ($valores[$curso] ?? '') : '';
};
$pesos = static fn (int $monto): string => number_format($monto, 0, ',', '.');
$error = static fn (string $campo): string => isset($errores[$campo])
    ? '<p class="error-campo">' . esc($errores[$campo]) . '</p>'
    : '';
$cursosElegidos = array_values(array_filter(
    $entrada['cursos'] ?? [],
    static fn (mixed $codigo): bool => is_string($codigo),
));
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Inscripción a <?= esc($programa->nombre) ?> · Budismo Camino del Diamante Colombia">
    <title><?= esc($programa->nombre) ?></title>
    <link rel="icon" href="<?= base_url('favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= base_url('css/publico.css') ?>">
</head>
<body>
<a class="saltar-vista" href="#formulario-inicio">Saltar al formulario</a>

<header class="marca-cabecera">
    <img class="logo-dwb" src="<?= base_url('dwb-logo.svg') ?>" alt="DWB Colombia">
    <p class="organizacion">Budismo Camino del Diamante Colombia</p>
</header>

<main class="contenedor" id="formulario-inicio">
    <h1><?= esc($programa->nombre) ?></h1>
    <p class="subtitulo">Al marcar un curso aparecen sus preguntas. El total se actualiza solo: cada comida suma <?= $pesos($programa->catalogo->precioComida) ?> y cada bus del curso, <?= $pesos($programa->catalogo->precioFranjaBus) ?>. La dieta, los equipos y el alojamiento no cambian el valor.</p>
    <aside class="total-caja" id="total">
        <p>Total en línea</p>
        <div class="total-monto" id="total-monto"><?= $cotizacion !== null ? '$ ' . $pesos($cotizacion->total()) : '—' ?></div>
        <ul class="total-lineas" id="total-lineas">
            <?php if ($cotizacion !== null): ?>
                <?php foreach ([...$cotizacion->lineas, ...$cotizacion->ajustes] as $linea): ?>
                    <li><?= esc($linea->descripcion) ?>: $ <?= $pesos($linea->subtotal()) ?></li>
                <?php endforeach ?>
            <?php endif ?>
        </ul>
        <div class="total-notas" id="total-notas">
            <?php foreach ($notas as $nota): ?>
                <p><?= esc($nota) ?></p>
            <?php endforeach ?>
        </div>
    </aside>
    <div class="avisos si-oculto" id="avisos">
        <strong>Revisa estos datos antes de continuar:</strong>
        <ul id="avisos-lista"></ul>
    </div>
    <?php if ($errores !== []): ?>
        <div class="avisos" style="border-color: #be0f34;">
            <strong>La inscripción no se guardó.</strong>
            <p style="margin: 0.3rem 0 0;">Revisá los campos marcados en rojo y volvé a enviar. El comprobante que ya subiste se conserva.</p>
        </div>
    <?php endif; ?>
    <?= form_open_multipart('/e/' . $slug, ['id' => 'inscripcion', 'novalidate' => 'novalidate', 'data-cotizar' => site_url('e/' . $slug . '/cotizar'), 'data-link-pago' => site_url('e/' . $slug . '/link-pago'), 'data-evento' => esc($programa->nombre)]) ?>

    <h2>Tus datos</h2>
    <?php foreach ($programa->ficha as $campo): ?>
        <?= view('inscripcion/campo', ['campo' => $campo, 'entrada' => $entrada, 'errores' => $errores]) ?>
    <?php endforeach ?>

    <h2>Cursos</h2>
    <?= $error('cursos') ?>
    <?php foreach ($programa->cursos as $curso): ?>
        <label class="check"><input type="checkbox" name="cursos[]" value="<?= esc($curso->codigo) ?>" <?= $elegido('cursos', $curso->codigo) ?>> <?= esc($curso->nombre) ?><?= $curso->tarifa === 0 ? ' (sin tarifa)' : ' (' . $pesos($curso->tarifa) . ')' ?></label>
    <?php endforeach ?>

    <?php foreach ($programa->cursos as $curso): ?>
        <?php $activo = in_array($curso->codigo, $cursosElegidos, true); ?>
        <fieldset class="bloque-curso<?= $activo ? '' : ' oculto' ?>" data-curso="<?= esc($curso->codigo) ?>">
            <legend><?= esc($curso->nombre) ?></legend>
            <?php $comidas = $programa->comidasDe($curso->codigo); ?>
            <?php if ($comidas !== []): ?>
                <p>Comidas, 8.000 cada una. La dieta no cambia el precio.</p>
                <?php foreach ($comidas as $comida): ?>
                    <label class="check"><input type="checkbox" name="comidas[]" value="<?= esc($comida->id) ?>" <?= $elegido('comidas', $comida->id) ?>> <?= esc($comida->etiqueta) ?></label>
                <?php endforeach ?>
                <p class="alerta aviso si-oculto" id="aviso-sesiones-comidas" style="font-size: 0.92rem;">⚠️ Estás eligiendo sesiones individuales y comidas. Revisá que las fechas de las comidas coincidan con los días de tus sesiones. El sistema no valida esto automáticamente.</p>
                <label>Dieta
                    <select name="dieta">
                        <option value="">Elige</option>
                        <option value="vegetariano" <?= $valor('dieta') === 'vegetariano' ? 'selected' : '' ?>>Vegetariano</option>
                        <option value="carne" <?= $valor('dieta') === 'carne' ? 'selected' : '' ?>>Carne</option>
                    </select>
                    <?= $error('dieta') ?>
                </label>
            <?php endif ?>

            <?php foreach (['transporte_ida' => 'Llegada', 'transporte_regreso' => 'Salida'] as $campo => $titulo): ?>
                <?php $sentido = $campo === 'transporte_ida' ? 'ida' : 'regreso'; ?>
                <label><?= esc($titulo) ?>
                    <select name="<?= esc($campo) ?>[<?= esc($curso->codigo) ?>]">
                        <option value="">Elige</option>
                        <option value="carro" <?= $modo($campo, $curso->codigo) === 'carro' ? 'selected' : '' ?>>Carro particular</option>
                        <option value="publico" <?= $modo($campo, $curso->codigo) === 'publico' ? 'selected' : '' ?>>Transporte público</option>
                        <?php if ($programa->franjasDe($curso->codigo, $sentido) !== []): ?>
                            <option value="curso" <?= $modo($campo, $curso->codigo) === 'curso' ? 'selected' : '' ?>>Transporte del curso, 100.000</option>
                        <?php endif ?>
                    </select>
                </label>
            <?php endforeach ?>
            <?= $error('transporte_ida') ?><?= $error('transporte_regreso') ?>
            <?php if ($programa->franjasDe($curso->codigo, 'ida') !== []): ?>
                <div class="depende<?= $modo('transporte_ida', $curso->codigo) === 'curso' ? '' : ' si-oculto' ?>" data-si="transporte_ida[<?= esc($curso->codigo) ?>]=curso">
                <label>Franja de llegada
                    <select name="franja_ida">
                        <option value="">Elige si vienes en el transporte del curso</option>
                        <?php foreach ($programa->franjasDe($curso->codigo, 'ida') as $franja): ?>
                            <option value="<?= esc($franja->id) ?>" <?= $valor('franja_ida') === $franja->id ? 'selected' : '' ?>><?= esc($franja->etiqueta) ?></option>
                        <?php endforeach ?>
                    </select>
                    <?= $error('franja_ida') ?>
                </label>
                </div>
                <div class="depende<?= $modo('transporte_regreso', $curso->codigo) === 'curso' ? '' : ' si-oculto' ?>" data-si="transporte_regreso[<?= esc($curso->codigo) ?>]=curso">
                <label>Franja de salida
                    <select name="franja_regreso">
                        <option value="">Elige si sales en el transporte del curso</option>
                        <?php foreach ($programa->franjasDe($curso->codigo, 'regreso') as $franja): ?>
                            <option value="<?= esc($franja->id) ?>" <?= $valor('franja_regreso') === $franja->id ? 'selected' : '' ?>><?= esc($franja->etiqueta) ?></option>
                        <?php endforeach ?>
                    </select>
                    <?= $error('franja_regreso') ?>
                </label>
                </div>
            <?php endif ?>

            <?php if (isset($programa->alojamientos[$curso->codigo])): ?>
                <?php $alojamiento = $programa->alojamientos[$curso->codigo]; ?>
                <div data-alojamiento="1">
                <label>Alojamiento
                    <select name="alojamiento_<?= esc($curso->codigo) ?>">
                        <option value="">Elige</option>
                        <?php $costosAloj = $alojamiento['costos'] ?? []; ?>
                        <?php foreach ($alojamiento['opciones'] as $codigoOpcion => $etiquetaOpcion): ?>
                            <?php $costo = (int) ($costosAloj[$codigoOpcion] ?? 0); ?>
                            <option value="<?= esc($codigoOpcion) ?>" <?= $valor('alojamiento_' . $curso->codigo) === $codigoOpcion ? 'selected' : '' ?>><?= esc($etiquetaOpcion) ?><?= $costo > 0 ? ' — $ ' . $pesos($costo) : '' ?></option>
                        <?php endforeach ?>
                    </select>
                    <?= $error('alojamiento_' . $curso->codigo) ?>
                </label>
                <label class="depende<?= $valor('alojamiento_' . $curso->codigo) === 'cama' ? '' : ' si-oculto' ?>" data-si="alojamiento_<?= esc($curso->codigo) ?>=cama">Condición, si necesitas cama <input name="condicion_cama_<?= esc($curso->codigo) ?>" value="<?= $valor('condicion_cama_' . $curso->codigo) ?>"><?= $error('condicion_cama_' . $curso->codigo) ?></label>
                <?php if ($alojamiento['pernocta_extra'] ?? false): ?>
                <label>¿Pernoctas fuera de las fechas? Se paga en sitio.
                    <select name="pernocta_extra_<?= esc($curso->codigo) ?>">
                        <option value="">Elige</option>
                        <option value="no" <?= $valor('pernocta_extra_' . $curso->codigo) === 'no' || $valor('pernocta_extra') === 'no' ? 'selected' : '' ?>>No</option>
                        <option value="si" <?= $valor('pernocta_extra_' . $curso->codigo) === 'si' || $valor('pernocta_extra') === 'si' ? 'selected' : '' ?>>Sí</option>
                    </select>
                    <?= $error('pernocta_extra_' . $curso->codigo) ?>
                </label>
                <label class="depende<?= ($valor('pernocta_extra_' . $curso->codigo) === 'si' || $valor('pernocta_extra') === 'si') ? '' : ' si-oculto' ?>" data-si="pernocta_extra_<?= esc($curso->codigo) ?>=si">Si respondiste sí, ¿apoyas montaje o desmontaje?
                    <select name="apoyo_montaje_<?= esc($curso->codigo) ?>">
                        <option value="">Elige</option>
                        <?php foreach (['no' => 'No', 'montaje' => 'Montaje', 'desmontaje' => 'Desmontaje', 'ambos' => 'Ambos'] as $codigoApoyo => $etiquetaApoyo): ?>
                            <option value="<?= esc($codigoApoyo) ?>" <?= $valor('apoyo_montaje_' . $curso->codigo) === $codigoApoyo ? 'selected' : '' ?>><?= esc($etiquetaApoyo) ?></option>
                        <?php endforeach ?>
                    </select>
                    <?= $error('apoyo_montaje_' . $curso->codigo) ?>
                </label>
                <?php endif ?>
                </div>
            <?php endif ?>

            <?php $sesionesCurso = $programa->sesionesDe($curso->codigo); ?>
            <?php if ($sesionesCurso !== []): ?>
                <div data-sesiones="<?= esc($curso->codigo) ?>">
                <p style="margin-top: 0.5rem;">Modalidad del curso: elegí el curso completo o solo las sesiones que quieras. Las sesiones sueltas se cobran aparte; el curso completo tiene la tarifa de arriba.</p>
                <label class="check"><input type="radio" name="modo_curso[<?= esc($curso->codigo) ?>]" value="completo" class="modo-curso" data-curso="<?= esc($curso->codigo) ?>" checked> Curso completo (<?= $pesos($curso->tarifa) ?>)</label>
                <label class="check"><input type="radio" name="modo_curso[<?= esc($curso->codigo) ?>]" value="sesiones" class="modo-curso" data-curso="<?= esc($curso->codigo) ?>"> Elegir sesiones individuales</label>
                <div class="bloque-sesiones si-oculto" data-curso-sesiones="<?= esc($curso->codigo) ?>">
                    <?php foreach ($sesionesCurso as $sesion): ?>
                        <?php $marcada = in_array($sesion->id, (array) ($entrada['sesiones_elegidas'][$curso->codigo] ?? []), true); ?>
                        <label class="check"><input type="checkbox" name="sesiones_elegidas[<?= esc($curso->codigo) ?>][]" value="<?= esc($sesion->id) ?>" class="sesion-check" data-curso="<?= esc($curso->codigo) ?>" data-costo="<?= $sesion->costo ?>" <?= $marcada ? 'checked' : '' ?>> <?= esc($sesion->titulo) ?> (<?= esc($sesion->tipo) ?>, <?= esc($sesion->inicio) ?>)<?= $sesion->costo > 0 ? ' — $ ' . $pesos($sesion->costo) : ' (gratis)' ?></label>
                    <?php endforeach ?>
                </div>
                </div>
            <?php endif ?>

            <p>Equipos, opcional, máximo dos. No cambian el precio.</p>
            <?php foreach ($programa->equiposDe($curso->codigo) as $equipo): ?>
                <?php $marcados = $entrada['equipos'][$curso->codigo] ?? []; ?>
                <label class="check"><input type="checkbox" name="equipos[<?= esc($curso->codigo) ?>][]" value="<?= esc($equipo->codigo) ?>" <?= is_array($marcados) && in_array($equipo->codigo, $marcados, true) ? 'checked' : '' ?>> <?= esc($equipo->etiqueta) ?></label>
            <?php endforeach ?>
            <?= $error('equipos') ?>
        </fieldset>
    <?php endforeach ?>

    <h2>Pago</h2>
    <label>Forma de pago
        <select name="forma_pago">
            <option value="">Elige para terminar</option>
            <?php foreach ($programa->formasPago as $forma): ?>
                <option value="<?= esc($forma->codigo) ?>" data-pide="<?= $forma->pideComprobante ? '1' : '0' ?>" <?= $valor('forma_pago') === $forma->codigo ? 'selected' : '' ?>><?= esc($forma->etiqueta) ?></option>
            <?php endforeach ?>
        </select>
        <?= $error('forma_pago') ?>
    </label>
    <div id="instrucciones-pago"></div>
    <input type="hidden" name="payment_link_generado" id="payment_link_generado">
    <div id="bloque-link-pago" class="si-oculto" style="border: 1px solid var(--color-borde); border-radius: var(--radio); padding: 0.9rem 1rem; margin-bottom: 0.75rem; background: #fafafa;">
        <p style="font-weight: 650; margin-bottom: 0.4rem;">Pago con tarjeta:</p>
        <button type="button" id="btn-link-pago" class="boton-primario" style="margin: 0;">Generar enlace de pago</button>
        <p class="ayuda" id="link-pago-estado" style="margin: 0.5rem 0 0;"></p>
        <div id="link-pago-resultado" style="margin-top: 0.5rem;"></div>
    </div>
    <div id="bloque-comprobante" class="depende<?= in_array($valor('forma_pago'), ['transferencia', 'tarjeta'], true) ? '' : ' si-oculto' ?>">
    <label>Comprobante, solo si pagas por transferencia o tarjeta solas
        <input type="file" name="comprobante" accept=".pdf,.jpg,.jpeg,.png" id="input-comprobante">
        <?= $error('comprobante') ?>
    </label>
    <div id="nombre-archivo" class="archivo-nombre si-oculto">
        <span class="icono-doc">📄</span>
        <span id="nombre-archivo-texto"></span>
        <button type="button" id="quitar-archivo">Quitar</button>
    </div>
    <?php if ($valor('comprobante_guardado') !== ''): ?>
        <p class="recibo-nota">Comprobante guardado. No hace falta volver a subirlo.</p>
        <input type="hidden" name="comprobante_guardado" value="<?= $valor('comprobante_guardado') ?>">
    <?php endif ?>
    </div>

    <div class="acciones">
        <button class="boton-secundario" type="submit" name="accion" value="actualizar">Ver total</button>
        <button class="boton-primario" type="submit" name="accion" value="confirmar">Terminar inscripción</button>
    </div>
    <?= form_close() ?>
    <script>
    (function(){
        var CUENTA = <?= json_encode($programa->cuentaBancaria) ?>;
        var BREB = <?= json_encode($programa->breb) ?>;
        var formaPago = document.querySelector('select[name="forma_pago"]');
        var instrucciones = document.getElementById('instrucciones-pago');
        var bloqueLink = document.getElementById('bloque-link-pago');
        var btnLink = document.getElementById('btn-link-pago');
        var estadoLink = document.getElementById('link-pago-estado');
        var resultadoLink = document.getElementById('link-pago-resultado');
        var campoLink = document.getElementById('payment_link_generado');
        var form = document.getElementById('inscripcion');

        function mostrar() {
            var v = formaPago.value;
            var esTarjeta = v === 'tarjeta';
            bloqueLink.classList.toggle('si-oculto', !esTarjeta);
            if (!v || v === 'sitio' || v === 'ruta_budista') {
                instrucciones.innerHTML = '';
                return;
            }

            var html = '';
            if (v === 'transferencia') {
                html += '<div class="instrucciones-bloque" style="border: 1px solid var(--color-borde); border-radius: var(--radio); padding: 0.9rem 1rem; margin-bottom: 0.75rem; background: #fafafa;">';
                if (CUENTA) {
                    html += '<p style="font-weight: 650; margin-bottom: 0.3rem;">Datos para transferencia:</p>';
                    html += '<p style="white-space: pre-line; font-size: 0.88rem; margin: 0 0 0.4rem;">' + escapeHtml(CUENTA) + '</p>';
                }
                if (BREB) {
                    html += '<p style="font-size: 0.88rem; margin: 0 0 0.4rem;">Clave Bre-b: <strong>' + escapeHtml(BREB) + '</strong></p>';
                }
                html += '<p class="ayuda" style="margin: 0;">Consigná el total y después subí el comprobante abajo.</p>';
                html += '</div>';
            } else if (v === 'tarjeta') {
                html += '<div class="instrucciones-bloque" style="border: 1px solid var(--color-borde); border-radius: var(--radio); padding: 0.9rem 1rem; margin-bottom: 0.75rem; background: #fafafa;">';
                html += '<p style="font-size: 0.88rem; margin: 0;">Generá el enlace de pago para abrir la pasarela. Después del pago, bajá el comprobante y subilo abajo para terminar.</p>';
                html += '</div>';
            } else {
                html += '<div class="instrucciones-bloque" style="border: 1px solid var(--color-borde); border-radius: var(--radio); padding: 0.9rem 1rem; margin-bottom: 0.75rem; background: #fafafa;">';
                html += '<p style="font-size: 0.88rem; margin: 0;">Pagás en el sitio. Si querés, subí un comprobante abajo.</p>';
                html += '</div>';
            }
            instrucciones.innerHTML = html;
        }

        async function generarLink() {
            btnLink.disabled = true;
            estadoLink.textContent = 'Generando el enlace de pago…';
            resultadoLink.innerHTML = '';
            var datos = new FormData(form);
            datos.delete('comprobante');
            try {
                var respuesta = await fetch(form.dataset.linkPago, {
                    method: 'POST',
                    body: datos,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                var datosJson = await respuesta.json();
                if (!respuesta.ok || !datosJson.url) {
                    estadoLink.textContent = datosJson.error || 'No se pudo generar el enlace de pago.';
                    btnLink.disabled = false;
                    return;
                }
                campoLink.value = datosJson.payment_link || '';
                resultadoLink.innerHTML = '<a class="boton-primario" href="' + escapeHtml(datosJson.url) + '" target="_blank" rel="noopener" style="display: inline-block; text-decoration: none; padding: 0.5rem 1rem; background: var(--color-primario); color: #fff; border-radius: var(--radio); font-weight: 650;">Pagar con tarjeta (Bold)</a>';
                estadoLink.textContent = 'Pagaste? Bajá el comprobante del pago y subilo abajo para terminar la inscripción.';
            } catch (e) {
                estadoLink.textContent = 'No se pudo generar el enlace. Revisá la conexión.';
                btnLink.disabled = false;
            }
        }

        function escapeHtml(s) {
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        // Evita el doble envío de "Terminar inscripción": marca el form y
        // deshabilita los botones tras el primer submit de confirmación.
        var btnTerminar = form.querySelector('button[name="accion"][value="confirmar"]');
        form.addEventListener('submit', function (evento) {
            var disparador = document.activeElement;
            var esConfirmar = disparador && disparador.name === 'accion' && disparador.value === 'confirmar';
            if (!esConfirmar) {
                return; // "Ver total" sí vuelve a enviar (recalcula sin confirmar)
            }
            if (form.dataset.enviando === '1') {
                evento.preventDefault(); // segundo envío del mismo form → no repetir
                return;
            }
            // NO deshabilitar el botón aquí: en el submit, al deshabilitarlo el navegador
            // omite su name/value y el body llega sin "accion=confirmar", así que el
            // servidor no guarda. El flag enviando + preventDefault ya evita el doble envío.
            form.dataset.enviando = '1';
            btnTerminar.textContent = 'Enviando…';
        });

        btnLink.addEventListener('click', generarLink);
        formaPago.addEventListener('change', mostrar);
        mostrar();
    })();
    </script>
    <script src="<?= base_url('js/inscripcion.js') ?>"></script>
</main>
</body>
</html>

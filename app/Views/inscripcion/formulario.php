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
    ? '<p class="error">' . esc($errores[$campo]) . '</p>'
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
    <title><?= esc($programa->nombre) ?></title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; font: 16px/1.45 Georgia, "Times New Roman", serif; color: #1c1915; background: #f6f1e8; }
        main { max-width: 920px; margin: 0 auto; padding: 32px 20px 64px; }
        h1 { font-weight: 500; font-size: 2rem; margin-bottom: 0.2rem; }
        h2 { font-size: 1.25rem; margin: 2rem 0 0.8rem; }
        p.lead { color: #5c5348; }
        form { display: grid; gap: 8px; }
        fieldset { border: 1px solid #ddd4c6; background: #fffdf9; padding: 16px; margin: 0; }
        legend { padding: 0 6px; }
        label { display: grid; gap: 4px; margin: 8px 0; }
        input, select { font: inherit; padding: 8px; border: 1px solid #c8bfb2; background: white; }
        .fila { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .check { display: flex; gap: 8px; align-items: center; }
        .error { color: #8a2b2b; margin: 0; }
        .oculto { display: none !important; }
        #avisos { margin: 12px 0; padding-left: 1.2rem; color: #8a2b2b; }
        aside { position: sticky; top: 0; z-index: 2; margin: 16px 0; padding: 16px; background: #1c1915; color: #f6f1e8; }
        aside strong { font-size: 1.8rem; }
        button { font: inherit; padding: 10px 16px; border: 0; background: #1c1915; color: #f6f1e8; cursor: pointer; }
        button.secundario { background: transparent; color: #1c1915; border: 1px solid #1c1915; }
        .acciones { display: flex; gap: 8px; margin-top: 16px; }
        @media (max-width: 700px) { .fila { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main>
    <h1><?= esc($programa->nombre) ?></h1>
    <p class="lead">Al marcar un curso aparecen sus preguntas. El total se actualiza solo: cada comida suma 8.000 y cada bus del curso, 100.000. La dieta, los equipos y el alojamiento no cambian el valor.</p>
    <aside id="total">
        <p>Total en línea</p>
        <strong id="total-monto"><?= $cotizacion !== null ? '$ ' . $pesos($cotizacion->total()) : '—' ?></strong>
        <ul id="total-lineas">
            <?php if ($cotizacion !== null): ?>
                <?php foreach ([...$cotizacion->lineas, ...$cotizacion->ajustes] as $linea): ?>
                    <li><?= esc($linea->descripcion) ?>: $ <?= $pesos($linea->subtotal()) ?></li>
                <?php endforeach ?>
            <?php endif ?>
        </ul>
        <div id="total-notas">
            <?php foreach ($notas as $nota): ?>
                <p><?= esc($nota) ?></p>
            <?php endforeach ?>
        </div>
    </aside>
    <ul id="avisos" class="oculto"></ul>
    <?= form_open_multipart('/tour-365', ['id' => 'inscripcion', 'novalidate' => 'novalidate', 'data-cotizar' => site_url('tour-365/cotizar')]) ?>

    <h2>Tus datos</h2>
    <div class="fila">
        <label>Nombre completo <input name="nombre" value="<?= $valor('nombre') ?>" required><?= $error('nombre') ?></label>
        <label>Tipo de documento
            <select name="tipo_documento" required>
                <option value="">Elige</option>
                <?php foreach ($programa->tiposDocumento as $codigo => $etiqueta): ?>
                    <option value="<?= esc($codigo) ?>" <?= $valor('tipo_documento') === $codigo ? 'selected' : '' ?>><?= esc($etiqueta) ?></option>
                <?php endforeach ?>
            </select>
            <?= $error('tipo_documento') ?>
        </label>
    </div>
    <div class="fila">
        <label>Número de documento <input name="documento" value="<?= $valor('documento') ?>" required><?= $error('documento') ?></label>
        <label>Edad
            <select name="edad" required>
                <option value="">Elige</option>
                <?php foreach ($programa->bandasEdad as $codigo => $etiqueta): ?>
                    <option value="<?= esc($codigo) ?>" <?= $valor('edad') === $codigo ? 'selected' : '' ?>><?= esc($etiqueta) ?></option>
                <?php endforeach ?>
            </select>
            <?= $error('edad') ?>
        </label>
    </div>
    <div class="fila">
        <label>Teléfono <input name="telefono" value="<?= $valor('telefono') ?>" required><?= $error('telefono') ?></label>
        <label>Correo <input type="email" name="correo" value="<?= $valor('correo') ?>" required><?= $error('correo') ?></label>
    </div>
    <div class="fila">
        <label>Centro
            <select name="centro" required>
                <option value="">Elige</option>
                <?php foreach ($programa->centros as $codigo => $etiqueta): ?>
                    <option value="<?= esc($codigo) ?>" <?= $valor('centro') === $codigo ? 'selected' : '' ?>><?= esc($etiqueta) ?></option>
                <?php endforeach ?>
            </select>
            <?= $error('centro') ?>
        </label>
        <label class="depende<?= $valor('centro') === 'otro' ? '' : ' oculto' ?>" data-si="centro=otro">Si elegiste otro, ¿cuál? <input name="centro_otro" value="<?= $valor('centro_otro') ?>"><?= $error('centro_otro') ?></label>
    </div>
    <div class="fila">
        <label>Seguro médico
            <select name="seguro" required>
                <option value="">Elige</option>
                <?php foreach ($programa->seguros as $codigo => $etiqueta): ?>
                    <option value="<?= esc($codigo) ?>" <?= $valor('seguro') === $codigo ? 'selected' : '' ?>><?= esc($etiqueta) ?></option>
                <?php endforeach ?>
            </select>
            <?= $error('seguro') ?>
        </label>
        <label>Nombre de la EPS o datos del seguro <input name="datos_seguro" value="<?= $valor('datos_seguro') ?>" required><?= $error('datos_seguro') ?></label>
    </div>
    <fieldset>
        <legend>Contacto de emergencia</legend>
        <div class="fila">
            <label>Nombre <input name="emergencia_nombre" value="<?= $valor('emergencia_nombre') ?>" required><?= $error('emergencia_nombre') ?></label>
            <label>Parentesco
                <select name="emergencia_parentesco" required>
                    <option value="">Elige</option>
                    <?php foreach ($programa->parentescos as $codigo => $etiqueta): ?>
                        <option value="<?= esc($codigo) ?>" <?= $valor('emergencia_parentesco') === $codigo ? 'selected' : '' ?>><?= esc($etiqueta) ?></option>
                    <?php endforeach ?>
                </select>
                <?= $error('emergencia_parentesco') ?>
            </label>
        </div>
        <div class="fila">
            <label class="depende<?= $valor('emergencia_parentesco') === 'otro' ? '' : ' oculto' ?>" data-si="emergencia_parentesco=otro">Si elegiste otro, ¿cuál? <input name="emergencia_parentesco_otro" value="<?= $valor('emergencia_parentesco_otro') ?>"><?= $error('emergencia_parentesco_otro') ?></label>
            <label>Teléfono <input name="emergencia_telefono" value="<?= $valor('emergencia_telefono') ?>" required><?= $error('emergencia_telefono') ?></label>
        </div>
    </fieldset>
    <label class="check"><input type="checkbox" name="consentimiento" value="1" <?= $valor('consentimiento') === '1' ? 'checked' : '' ?>> Autorizo el tratamiento de mis datos para esta inscripción.<?= $error('consentimiento') ?></label>

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
                <div class="depende<?= $modo('transporte_ida', $curso->codigo) === 'curso' ? '' : ' oculto' ?>" data-si="transporte_ida[<?= esc($curso->codigo) ?>]=curso">
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
                <div class="depende<?= $modo('transporte_regreso', $curso->codigo) === 'curso' ? '' : ' oculto' ?>" data-si="transporte_regreso[<?= esc($curso->codigo) ?>]=curso">
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
                <label>Alojamiento. No se cobra aquí.
                    <select name="alojamiento_<?= esc($curso->codigo) ?>">
                        <option value="">Elige</option>
                        <?php foreach ($alojamiento['opciones'] as $codigoOpcion => $etiquetaOpcion): ?>
                            <option value="<?= esc($codigoOpcion) ?>" <?= $valor('alojamiento_' . $curso->codigo) === $codigoOpcion || ($curso->codigo === 'kpl' && $valor('alojamiento_kpl') === $codigoOpcion) ? 'selected' : '' ?>><?= esc($etiquetaOpcion) ?></option>
                        <?php endforeach ?>
                    </select>
                    <?= $error('alojamiento_' . $curso->codigo) ?>
                </label>
                <label class="depende<?= $valor('alojamiento_' . $curso->codigo) === 'cama' ? '' : ' oculto' ?>" data-si="alojamiento_<?= esc($curso->codigo) ?>=cama">Condición, si necesitas cama <input name="condicion_cama_<?= esc($curso->codigo) ?>" value="<?= $valor('condicion_cama_' . $curso->codigo) ?>"><?= $error('condicion_cama_' . $curso->codigo) ?></label>
                <?php if ($alojamiento['pernocta_extra'] ?? false): ?>
                <label>¿Pernoctas fuera de las fechas? Se paga en sitio.
                    <select name="pernocta_extra_<?= esc($curso->codigo) ?>">
                        <option value="">Elige</option>
                        <option value="no" <?= $valor('pernocta_extra_' . $curso->codigo) === 'no' || $valor('pernocta_extra') === 'no' ? 'selected' : '' ?>>No</option>
                        <option value="si" <?= $valor('pernocta_extra_' . $curso->codigo) === 'si' || $valor('pernocta_extra') === 'si' ? 'selected' : '' ?>>Sí</option>
                    </select>
                    <?= $error('pernocta_extra_' . $curso->codigo) ?>
                </label>
                <label class="depende<?= ($valor('pernocta_extra_' . $curso->codigo) === 'si' || $valor('pernocta_extra') === 'si') ? '' : ' oculto' ?>" data-si="pernocta_extra_<?= esc($curso->codigo) ?>=si">Si respondiste sí, ¿apoyas montaje o desmontaje?
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
    <div id="bloque-comprobante" class="depende<?= in_array($valor('forma_pago'), ['transferencia', 'tarjeta'], true) ? '' : ' oculto' ?>">
    <label>Comprobante, solo si pagas por transferencia o tarjeta solas
        <input type="file" name="comprobante" accept=".pdf,.jpg,.jpeg,.png">
        <?= $error('comprobante') ?>
    </label>
    <?php if ($valor('comprobante_guardado') !== ''): ?>
        <p>Comprobante guardado. No hace falta volver a subirlo.</p>
        <input type="hidden" name="comprobante_guardado" value="<?= $valor('comprobante_guardado') ?>">
    <?php endif ?>
    </div>

    <div class="acciones">
        <button class="secundario" type="submit" name="accion" value="actualizar">Ver total</button>
        <button type="submit" name="accion" value="confirmar">Terminar inscripción</button>
    </div>
    <?= form_close() ?>
    <script src="<?= base_url('js/inscripcion.js') ?>"></script>
</main>
</body>
</html>

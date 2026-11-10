<?php
/** @var string $slug */
/** @var bool $nuevo */
/** @var array<string, mixed> $post */
/** @var list<string> $errores */

$lista = static function (string $clave) use ($post): array {
    $valores = $post[$clave] ?? [];

    return is_array($valores) ? array_values($valores) : [];
};
$texto = static fn (string $clave): string => esc((string) ($post[$clave] ?? ''));
$filas = static function (array $columnas) use ($lista): int {
    $cantidad = 1;
    foreach ($columnas as $columna) {
        $cantidad = max($cantidad, count($lista($columna)));
    }

    return $cantidad;
};
$celda = static function (string $clave, int $i) use ($lista): string {
    return esc((string) ($lista($clave)[$i] ?? ''));
};
$siNo = static function (string $nombre, string $valor): string {
    $no = $valor === '1' ? '' : ' selected';
    $si = $valor === '1' ? ' selected' : '';

    return '<select name="' . esc($nombre) . '"><option value="0"' . $no . '>No</option><option value="1"' . $si . '>Sí</option></select>';
};
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?= $nuevo ? 'Nuevo evento' : 'Editar evento' ?></title>
    <style>
        body { margin: 0; font: 16px/1.45 Georgia, serif; background: #f6f1e8; color: #1c1915; }
        main { max-width: 980px; margin: 0 auto; padding: 24px 16px 64px; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0 20px; background: #fffdf9; }
        th, td { border-bottom: 1px solid #ddd4c6; padding: 6px; text-align: left; vertical-align: top; }
        input, select, textarea, button { font: inherit; padding: 6px; width: 100%; box-sizing: border-box; }
        textarea { min-height: 72px; }
        button, a.boton { background: #1c1915; color: #f6f1e8; border: 0; padding: 8px 12px; text-decoration: none; display: inline-block; }
        button.secundario { background: transparent; color: #1c1915; border: 1px solid #1c1915; width: auto; }
        .error { color: #8a2b2b; }
        h2 { margin-top: 28px; }
    </style>
</head>
<body>
<main>
    <p><a href="<?= site_url('admin') ?>">Eventos</a><?php if (! $nuevo): ?> · <a href="<?= site_url('e/' . $slug) ?>">ver inscripción</a><?php endif ?></p>
    <h1><?= $nuevo ? 'Nuevo evento' : esc((string) ($post['nombre'] ?? $slug)) ?></h1>
    <?php if ($errores !== []): ?>
        <ul class="error"><?php foreach ($errores as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul>
    <?php endif ?>
    <?= form_open($nuevo ? '/admin/eventos' : '/admin/eventos/' . $slug) ?>
        <label>Nombre <input name="nombre" value="<?= $texto('nombre') ?>" required></label>
        <?php if ($nuevo): ?>
            <label>Enlace <input name="slug" value="<?= esc($slug) ?>" placeholder="retiro-sabana" required></label>
        <?php else: ?>
            <p>Enlace: <code><?= esc($slug) ?></code></p>
        <?php endif ?>
        <label>Precio de cada comida, en pesos <input name="precio_comida" value="<?= $texto('precio_comida') ?>"></label>
        <label>Precio de cada franja de bus, en pesos <input name="precio_franja" value="<?= $texto('precio_franja') ?>"></label>

        <h2>Cursos</h2>
        <table data-filas>
            <tr><th>Código</th><th>Nombre</th><th>Tarifa</th></tr>
            <?php for ($i = 0; $i < $filas(['curso_codigo', 'curso_nombre', 'curso_tarifa']); $i++): ?>
                <tr>
                    <td><input name="curso_codigo[]" value="<?= $celda('curso_codigo', $i) ?>"></td>
                    <td><input name="curso_nombre[]" value="<?= $celda('curso_nombre', $i) ?>"></td>
                    <td><input name="curso_tarifa[]" value="<?= $celda('curso_tarifa', $i) ?>"></td>
                </tr>
            <?php endfor ?>
        </table>
        <button type="button" class="secundario" data-agregar>Agregar curso</button>

        <h2>Comidas</h2>
        <p>El código del curso tiene que coincidir con uno de arriba. El precio es el de cada comida.</p>
        <table data-filas>
            <tr><th>Id</th><th>Curso</th><th>Etiqueta</th></tr>
            <?php for ($i = 0; $i < $filas(['comida_id', 'comida_curso', 'comida_etiqueta']); $i++): ?>
                <tr>
                    <td><input name="comida_id[]" value="<?= $celda('comida_id', $i) ?>"></td>
                    <td><input name="comida_curso[]" value="<?= $celda('comida_curso', $i) ?>"></td>
                    <td><input name="comida_etiqueta[]" value="<?= $celda('comida_etiqueta', $i) ?>"></td>
                </tr>
            <?php endfor ?>
        </table>
        <button type="button" class="secundario" data-agregar>Agregar comida</button>

        <h2>Transporte del evento</h2>
        <table data-filas>
            <tr><th>Id</th><th>Curso</th><th>Sentido</th><th>Etiqueta</th></tr>
            <?php for ($i = 0; $i < $filas(['franja_id', 'franja_curso', 'franja_sentido', 'franja_etiqueta']); $i++): ?>
                <tr>
                    <td><input name="franja_id[]" value="<?= $celda('franja_id', $i) ?>"></td>
                    <td><input name="franja_curso[]" value="<?= $celda('franja_curso', $i) ?>"></td>
                    <td>
                        <select name="franja_sentido[]">
                            <?php $sentido = (string) ($lista('franja_sentido')[$i] ?? ''); ?>
                            <option value=""></option>
                            <option value="ida" <?= $sentido === 'ida' ? 'selected' : '' ?>>Llegada</option>
                            <option value="regreso" <?= $sentido === 'regreso' ? 'selected' : '' ?>>Salida</option>
                        </select>
                    </td>
                    <td><input name="franja_etiqueta[]" value="<?= $celda('franja_etiqueta', $i) ?>"></td>
                </tr>
            <?php endfor ?>
        </table>
        <button type="button" class="secundario" data-agregar>Agregar franja</button>

        <h2>Alojamiento por curso</h2>
        <p>Una opción por línea, <code>codigo=etiqueta</code>. Si lo dejas vacío, ese curso no pregunta alojamiento. Guarda el curso primero si acabas de crearlo.</p>
        <?php $alojamiento = is_array($post['alojamiento'] ?? null) ? $post['alojamiento'] : []; ?>
        <?php foreach ($lista('curso_codigo') as $codigoCurso): ?>
            <?php if ($codigoCurso === '') continue; ?>
            <?php $bloque = is_array($alojamiento[$codigoCurso] ?? null) ? $alojamiento[$codigoCurso] : []; ?>
            <label><?= esc((string) $codigoCurso) ?>
                <textarea name="alojamiento[<?= esc((string) $codigoCurso) ?>][opciones]"><?= esc((string) ($bloque['opciones'] ?? '')) ?></textarea>
            </label>
            <label>¿Preguntar pernocta extra? <?= $siNo('alojamiento[' . $codigoCurso . '][pernocta]', (string) ($bloque['pernocta'] ?? '0')) ?></label>
        <?php endforeach ?>

        <h2>Formas de pago</h2>
        <table data-filas>
            <tr><th>Código</th><th>Etiqueta</th><th>Cobra aquí</th><th>Pide comprobante</th></tr>
            <?php for ($i = 0; $i < $filas(['pago_codigo', 'pago_etiqueta']); $i++): ?>
                <tr>
                    <td><input name="pago_codigo[]" value="<?= $celda('pago_codigo', $i) ?>"></td>
                    <td><input name="pago_etiqueta[]" value="<?= $celda('pago_etiqueta', $i) ?>"></td>
                    <td><?= $siNo('pago_cobra[]', (string) ($lista('pago_cobra')[$i] ?? '0')) ?></td>
                    <td><?= $siNo('pago_comprobante[]', (string) ($lista('pago_comprobante')[$i] ?? '0')) ?></td>
                </tr>
            <?php endfor ?>
        </table>
        <button type="button" class="secundario" data-agregar>Agregar forma de pago</button>

        <h2>Equipos</h2>
        <p>Si el equipo es de un solo curso, escribe su código. Si no, déjalo vacío.</p>
        <table data-filas>
            <tr><th>Código</th><th>Etiqueta</th><th>Solo curso</th></tr>
            <?php for ($i = 0; $i < $filas(['equipo_codigo', 'equipo_etiqueta', 'equipo_curso']); $i++): ?>
                <tr>
                    <td><input name="equipo_codigo[]" value="<?= $celda('equipo_codigo', $i) ?>"></td>
                    <td><input name="equipo_etiqueta[]" value="<?= $celda('equipo_etiqueta', $i) ?>"></td>
                    <td><input name="equipo_curso[]" value="<?= $celda('equipo_curso', $i) ?>"></td>
                </tr>
            <?php endfor ?>
        </table>
        <button type="button" class="secundario" data-agregar>Agregar equipo</button>

        <h2>Ficha del participante</h2>
        <p>Una opción por línea, <code>codigo=etiqueta</code>. El padre es la clave del grupo, si este campo vive dentro de uno. Los roles que el sistema entiende son nombre, documento, correo, edad y consentimiento.</p>
        <table data-filas>
            <tr><th>Clave</th><th>Etiqueta</th><th>Tipo</th><th>Obligatorio</th><th>Opciones</th><th>Visible si</th><th>Rol</th><th>Padre</th></tr>
            <?php for ($i = 0; $i < $filas(['campo_clave', 'campo_etiqueta']); $i++): ?>
                <?php $tipo = (string) ($lista('campo_tipo')[$i] ?? 'texto'); ?>
                <tr>
                    <td><input name="campo_clave[]" value="<?= $celda('campo_clave', $i) ?>"></td>
                    <td><input name="campo_etiqueta[]" value="<?= $celda('campo_etiqueta', $i) ?>"></td>
                    <td>
                        <select name="campo_tipo[]">
                            <?php foreach (['texto', 'email', 'telefono', 'lista', 'si_no', 'texto_largo', 'grupo'] as $opcion): ?>
                                <option value="<?= esc($opcion) ?>" <?= $tipo === $opcion ? 'selected' : '' ?>><?= esc($opcion) ?></option>
                            <?php endforeach ?>
                        </select>
                    </td>
                    <td><?= $siNo('campo_requerido[]', (string) ($lista('campo_requerido')[$i] ?? '0')) ?></td>
                    <td><textarea name="campo_opciones[]"><?= $celda('campo_opciones', $i) ?></textarea></td>
                    <td><input name="campo_visible[]" value="<?= $celda('campo_visible', $i) ?>" placeholder="centro=otro"></td>
                    <td><input name="campo_rol[]" value="<?= $celda('campo_rol', $i) ?>"></td>
                    <td><input name="campo_padre[]" value="<?= $celda('campo_padre', $i) ?>"></td>
                    <input type="hidden" name="campo_mensaje[]" value="<?= $celda('campo_mensaje', $i) ?>">
                </tr>
            <?php endfor ?>
        </table>
        <button type="button" class="secundario" data-agregar>Agregar campo</button>

        <h2>Listas de apoyo</h2>
        <label>Tipos de documento <textarea name="tipos_documento"><?= $texto('tipos_documento') ?></textarea></label>
        <label>Centros <textarea name="centros"><?= $texto('centros') ?></textarea></label>
        <label>Seguros <textarea name="seguros"><?= $texto('seguros') ?></textarea></label>
        <label>Parentescos <textarea name="parentescos"><?= $texto('parentescos') ?></textarea></label>
        <label>Bandas de edad <textarea name="bandas_edad"><?= $texto('bandas_edad') ?></textarea></label>

        <p><button type="submit">Guardar evento</button></p>
    <?= form_close() ?>
</main>
<script src="<?= base_url('js/editor.js') ?>"></script>
</body>
</html>

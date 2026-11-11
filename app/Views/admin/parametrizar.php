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
$n = static function (array $columnas) use ($lista): int {
    $cantidad = 0;
    foreach ($columnas as $columna) {
        $cantidad = max($cantidad, count($lista($columna)));
    }

    return max(1, $cantidad);
};
$celda = static function (string $clave, int $i) use ($lista): string {
    return esc((string) ($lista($clave)[$i] ?? ''));
};
$siNo = static function (string $nombre, string $valor): string {
    $no = $valor === '1' ? '' : ' selected';
    $si = $valor === '1' ? ' selected' : '';

    return '<select name="' . esc($nombre) . '"><option value="0"' . $no . '>No</option><option value="1"' . $si . '>Sí</option></select>';
};
$tiposCampo = ['texto', 'email', 'telefono', 'lista', 'si_no', 'texto_largo', 'grupo'];
$guardado = service('request')->getGet('guardado') === '1';
$conteos = [
    'cursos' => count(array_filter($lista('curso_codigo'))),
    'comidas' => count(array_filter($lista('comida_id'))),
    'franjas' => count(array_filter($lista('franja_id'))),
    'sesiones' => count(array_filter($lista('sesion_id'))),
    'pagos' => count(array_filter($lista('pago_codigo'))),
    'equipos' => count(array_filter($lista('equipo_codigo'))),
    'campos' => count(array_filter($lista('campo_clave'))),
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $nuevo ? 'Nuevo evento' : 'Parametrizar evento' ?></title>
    <link rel="stylesheet" href="<?= base_url('css/admin.css') ?>">
</head>
<body>
<a class="saltar" href="#editor">Saltar al formulario</a>
<header class="barra">
    <nav>
        <a class="marca" href="<?= site_url('admin') ?>">Eventos</a>
        <?php if (! $nuevo): ?>
                            <a href="<?= site_url('e/' . $slug) ?>" target="_blank" rel="noopener">Previsualizar</a>
                            <a href="<?= site_url('admin/eventos/' . $slug . '/inscritos') ?>">Inscritos</a>
                            <a href="<?= site_url('e/' . $slug) ?>">Ver inscripción</a>
                        <?php endif ?>
    </nav>
    <button class="boton" type="submit" form="editor">Guardar</button>
</header>
<div class="contenido">
    <nav class="indice" aria-label="Secciones">
        <a href="#identidad">Identidad</a>
        <a href="#cursos">Cursos <span><?= $conteos['cursos'] ?></span></a>
        <a href="#comidas">Comidas <span><?= $conteos['comidas'] ?></span></a>
        <a href="#transporte">Transporte <span><?= $conteos['franjas'] ?></span></a>
        <a href="#sesiones">Sesiones <span><?= $conteos['sesiones'] ?></span></a>
        <a href="#alojamiento">Alojamiento</a>
        <a href="#pagos">Pagos <span><?= $conteos['pagos'] ?></span></a>
        <a href="#equipos">Equipos <span><?= $conteos['equipos'] ?></span></a>
        <a href="#ficha">Ficha <span><?= $conteos['campos'] ?></span></a>
        <a href="#listas">Listas</a>
    </nav>
    <div>
        <?php if ($guardado): ?><p class="alerta ok" role="status">Evento guardado. La inscripción pública ya usa esta versión. Los recibos anteriores no cambian.</p><?php endif ?>
        <?php if ($errores !== []): ?>
            <div class="alerta error" role="alert">
                <strong>No se guardó.</strong> Revisa esto:
                <ul><?php foreach ($errores as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul>
            </div>
        <?php endif ?>
        <?= form_open($nuevo ? '/admin/eventos' : '/admin/eventos/' . $slug, ['id' => 'editor']) ?>

        <section class="panel" id="identidad">
            <h2>Identidad y precios</h2>
            <p class="intro">El nombre lo ve el participante. Los precios son pesos enteros y los aplica el servidor, no el navegador.</p>
            <div class="rejilla">
                <label class="campo">Nombre del evento
                    <input name="nombre" value="<?= $texto('nombre') ?>" required>
                    <p class="ayuda">Título de la inscripción. Ejemplo: Tour 365 Colombia.</p>
                </label>
                <?php if ($nuevo): ?>
                    <label class="campo">Enlace público
                        <input name="slug" value="<?= esc($slug) ?>" placeholder="retiro-sabana" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*">
                        <p class="ayuda">Solo minúsculas, números y guiones. Queda en <code>/e/este-texto</code> y no se cambia después.</p>
                    </label>
                <?php else: ?>
                    <p class="campo">Enlace público
                        <strong><code>/e/<?= esc($slug) ?></code></strong>
                        <span class="ayuda">Ya está publicado. Para otro enlace, crea otro evento.</span>
                    </p>
                <?php endif ?>
                <label class="campo">Descripción del evento
                    <textarea name="descripcion" placeholder="Fechas, lugar, breve descripción. Aparece en la portada pública."><?= $texto('descripcion') ?></textarea>
                    <p class="ayuda">Texto corto para la tarjeta del evento en la portada. Menos de 200 caracteres.</p>
                </label>
                <label class="campo">Precio de cada comida
                    <input name="precio_comida" inputmode="numeric" value="<?= $texto('precio_comida') ?>">
                    <p class="ayuda">Pesos enteros, sin puntos. Se suma una vez por comida marcada. Cero si la comida no se cobra aquí. No lleva descuento de edad.</p>
                </label>
                <label class="campo">Precio de cada franja de bus
                    <input name="precio_franja" inputmode="numeric" value="<?= $texto('precio_franja') ?>">
                    <p class="ayuda">Pesos enteros por cada bus cuya franja eligió la persona. Cero si el bus no se cobra. Tampoco lleva descuento de edad.</p>
                </label>
            </div>
        </section>

        <section class="panel" id="cursos">
            <h2>Cursos</h2>
            <p class="intro">Cada curso es una opción del evento. La tarifa es el precio del curso. Si hay un campo con rol edad, el descuento solo toca esta tarifa.</p>
            <table class="tabla">
                <thead><tr><th>Código</th><th>Nombre</th><th>Tarifa</th><th>Inicio</th><th>Fin</th><th></th></tr></thead>
                <tbody id="cursos-filas">
                <?php for ($i = 0; $i < $n(['curso_codigo', 'curso_nombre', 'curso_tarifa', 'curso_inicio', 'curso_fin']); $i++): ?>
                    <tr>
                        <td data-etiqueta="Código"><input name="curso_codigo[]" value="<?= $celda('curso_codigo', $i) ?>" placeholder="kpl"></td>
                        <td data-etiqueta="Nombre"><input name="curso_nombre[]" value="<?= $celda('curso_nombre', $i) ?>" placeholder="Curso KPL"></td>
                        <td data-etiqueta="Tarifa"><input name="curso_tarifa[]" inputmode="numeric" value="<?= $celda('curso_tarifa', $i) ?>" placeholder="150000"></td>
                        <td data-etiqueta="Inicio"><input type="datetime-local" name="curso_inicio[]" value="<?= $celda('curso_inicio', $i) ?>"></td>
                        <td data-etiqueta="Fin"><input type="datetime-local" name="curso_fin[]" value="<?= $celda('curso_fin', $i) ?>"></td>
                        <td><button type="button" class="boton peligro" data-quitar>Quitar</button></td>
                    </tr>
                <?php endfor ?>
                </tbody>
            </table>
            <p class="ayuda">El código es interno: comidas, buses y equipos lo citan. El participante ve el nombre. Tarifa 0 significa gratis.</p>
            <button type="button" class="boton claro" data-agregar="cursos-filas">Agregar curso</button>
        </section>

        <section class="panel" id="comidas">
            <h2>Comidas</h2>
            <p class="intro">La persona marca casillas. No escribe un rango de fechas. Cada casilla marcada suma el precio de comida.</p>
            <table class="tabla">
                <thead><tr><th>Id</th><th>Curso</th><th>Etiqueta</th><th></th></tr></thead>
                <tbody id="comidas-filas">
                <?php for ($i = 0; $i < $n(['comida_id', 'comida_curso', 'comida_etiqueta']); $i++): ?>
                    <tr>
                        <td data-etiqueta="Id"><input name="comida_id[]" value="<?= $celda('comida_id', $i) ?>" placeholder="k28_almuerzo"></td>
                        <td data-etiqueta="Curso"><input name="comida_curso[]" value="<?= $celda('comida_curso', $i) ?>" placeholder="kpl"></td>
                        <td data-etiqueta="Etiqueta"><input name="comida_etiqueta[]" value="<?= $celda('comida_etiqueta', $i) ?>" placeholder="28 nov, almuerzo"></td>
                        <td><button type="button" class="boton peligro" data-quitar>Quitar</button></td>
                    </tr>
                <?php endfor ?>
                </tbody>
            </table>
            <p class="ayuda">El id no se repite. El curso tiene que ser un código de arriba. La etiqueta es el texto de la casilla.</p>
            <button type="button" class="boton claro" data-agregar="comidas-filas">Agregar comida</button>
        </section>

        <section class="panel" id="transporte">
            <h2>Transporte del evento</h2>
            <p class="intro">Solo las salidas que organiza el evento. Carro propio y transporte público no se parametrizan aquí: el formulario ya los ofrece y no suman.</p>
            <table class="tabla">
                <thead><tr><th>Id</th><th>Curso</th><th>Sentido</th><th>Etiqueta</th><th></th></tr></thead>
                <tbody id="franjas-filas">
                <?php for ($i = 0; $i < $n(['franja_id', 'franja_curso', 'franja_sentido', 'franja_etiqueta']); $i++): ?>
                    <?php $sentido = (string) ($lista('franja_sentido')[$i] ?? ''); ?>
                    <tr>
                        <td data-etiqueta="Id"><input name="franja_id[]" value="<?= $celda('franja_id', $i) ?>" placeholder="kpl_ida_1400"></td>
                        <td data-etiqueta="Curso"><input name="franja_curso[]" value="<?= $celda('franja_curso', $i) ?>" placeholder="kpl"></td>
                        <td data-etiqueta="Sentido">
                            <select name="franja_sentido[]">
                                <option value=""></option>
                                <option value="ida" <?= $sentido === 'ida' ? 'selected' : '' ?>>Llegada</option>
                                <option value="regreso" <?= $sentido === 'regreso' ? 'selected' : '' ?>>Salida</option>
                            </select>
                        </td>
                        <td data-etiqueta="Etiqueta"><input name="franja_etiqueta[]" value="<?= $celda('franja_etiqueta', $i) ?>" placeholder="27 nov, 14:00, Bogotá → KPL"></td>
                        <td><button type="button" class="boton peligro" data-quitar>Quitar</button></td>
                    </tr>
                <?php endfor ?>
                </tbody>
            </table>
            <p class="ayuda">Llegada es el inicio del curso. Salida es el regreso. La etiqueta debe decir fecha, hora, origen y destino. Si no elige franja, ese bus no entra al total.</p>
            <button type="button" class="boton claro" data-agregar="franjas-filas">Agregar franja</button>
        </section>

        <section class="panel" id="sesiones">
            <h2>Sesiones</h2>
            <p class="intro">Subdivisión de un curso: enseñanzas, meditaciones, trabajos. Elegir sesiones sueltas no necesariamente suma lo mismo que el curso completo.</p>
            <table class="tabla">
                <thead><tr><th>Curso</th><th>Id</th><th>Título</th><th>Tipo</th><th>Inicio</th><th>Fin</th><th>Costo</th><th></th></tr></thead>
                <tbody id="sesiones-filas">
                <?php for ($i = 0; $i < $n(['sesion_curso', 'sesion_id', 'sesion_titulo']); $i++): ?>
                    <?php $tipo = (string) ($lista('sesion_tipo')[$i] ?? ''); ?>
                    <tr>
                        <td data-etiqueta="Curso"><input name="sesion_curso[]" value="<?= $celda('sesion_curso', $i) ?>" placeholder="kpl"></td>
                        <td data-etiqueta="Id"><input name="sesion_id[]" value="<?= $celda('sesion_id', $i) ?>" placeholder="kpl_s1"></td>
                        <td data-etiqueta="Título"><input name="sesion_titulo[]" value="<?= $celda('sesion_titulo', $i) ?>" placeholder="Enseñanza de Lama Ole"></td>
                        <td data-etiqueta="Tipo">
                            <select name="sesion_tipo[]">
                                <option value=""></option>
                                <option value="ensenanza" <?= $tipo === 'ensenanza' ? 'selected' : '' ?>>Enseñanza</option>
                                <option value="meditacion" <?= $tipo === 'meditacion' ? 'selected' : '' ?>>Meditación</option>
                                <option value="trabajo" <?= $tipo === 'trabajo' ? 'selected' : '' ?>>Trabajo</option>
                                <option value="libre" <?= $tipo === 'libre' ? 'selected' : '' ?>>Libre</option>
                            </select>
                        </td>
                        <td data-etiqueta="Inicio"><input type="datetime-local" name="sesion_inicio[]" value="<?= $celda('sesion_inicio', $i) ?>" placeholder="2026-11-28T09:00"></td>
                        <td data-etiqueta="Fin"><input type="datetime-local" name="sesion_fin[]" value="<?= $celda('sesion_fin', $i) ?>" placeholder="2026-11-28T11:30"></td>
                        <td data-etiqueta="Costo"><input name="sesion_costo[]" inputmode="numeric" value="<?= $celda('sesion_costo', $i) ?>" placeholder="50000"></td>
                        <td><button type="button" class="boton peligro" data-quitar>Quitar</button></td>
                    </tr>
                <?php endfor ?>
                </tbody>
            </table>
            <p class="ayuda">El curso es el código del curso que ya creaste. El id es interno y no se repite dentro del mismo curso. Usa los selectores de fecha y hora para inicio y fin. Costo 0 = gratuita. Si el curso tiene sesiones, el participante puede elegir curso completo o sesiones sueltas.</p>
            <button type="button" class="boton claro" data-agregar="sesiones-filas">Agregar sesión</button>
        </section>

        <section class="panel" id="alojamiento">
            <h2>Alojamiento</h2>
            <p class="intro">Se pregunta y queda en la nota de logística. No entra al total: se paga en el sitio. Agregá las opciones que puede elegir el participante.</p>
            <?php $alojamiento = is_array($post['alojamiento'] ?? null) ? $post['alojamiento'] : []; ?>
            <?php $hayCurso = false; ?>
            <?php foreach ($lista('curso_codigo') as $codigoCurso): ?>
                <?php if ($codigoCurso === '') { continue; } $hayCurso = true; ?>
                <?php $bloque = is_array($alojamiento[$codigoCurso] ?? null) ? $alojamiento[$codigoCurso] : []; ?>
                <?php $codigosAloj = is_array($bloque['opciones']['codigo'] ?? null) ? $bloque['opciones']['codigo'] : []; ?>
                <?php $etiquetasAloj = is_array($bloque['opciones']['etiqueta'] ?? null) ? $bloque['opciones']['etiqueta'] : []; ?>
                <div style="margin-bottom: 1.2rem;">
                    <h3 style="margin: 0 0 0.4rem; font-size: 1rem;">Curso <?= esc(strtoupper((string) $codigoCurso)) ?></h3>
                    <table class="tabla">
                        <thead><tr><th>Código</th><th>Etiqueta</th><th></th></tr></thead>
                        <tbody id="aloj-<?= esc((string) $codigoCurso) ?>-filas">
                        <?php $nAloj = max(1, count($codigosAloj)); ?>
                        <?php for ($i = 0; $i < $nAloj; $i++): ?>
                            <tr>
                                <td data-etiqueta="Código"><input name="alojamiento[<?= esc((string) $codigoCurso) ?>][opciones][codigo][]" value="<?= esc((string) ($codigosAloj[$i] ?? '')) ?>" placeholder="no"></td>
                                <td data-etiqueta="Etiqueta"><input name="alojamiento[<?= esc((string) $codigoCurso) ?>][opciones][etiqueta][]" value="<?= esc((string) ($etiquetasAloj[$i] ?? '')) ?>" placeholder="No duermo en el curso"></td>
                                <td><button type="button" class="boton peligro" data-quitar>Quitar</button></td>
                            </tr>
                        <?php endfor ?>
                        </tbody>
                    </table>
                    <p class="ayuda">Vacío significa que este curso no pregunta alojamiento. Agregá tantas opciones como necesites.</p>
                    <button type="button" class="boton claro" data-agregar="aloj-<?= esc((string) $codigoCurso) ?>-filas">Agregar opción</button>
                </div>
                <label class="campo">¿Preguntar pernocta fuera de las fechas?
                    <?= $siNo('alojamiento[' . $codigoCurso . '][pernocta]', (string) ($bloque['pernocta'] ?? '0')) ?>
                    <p class="ayuda">Si respondes sí, el formulario pregunta también si apoya montaje o desmontaje. Ese costo tampoco entra aquí.</p>
                </label>
            <?php endforeach ?>
            <?php if (! $hayCurso): ?><p class="ayuda">Primero guarda al menos un curso. Después vuelves y defines su alojamiento.</p><?php endif ?>
        </section>

        <section class="panel" id="pagos">
            <h2>Formas de pago</h2>
            <p class="intro">Cobra aquí solo cuando el registro debe recibir ese dinero. Si la forma incluye un fondo externo, no cobres y no pidas soporte.</p>
            <table class="tabla">
                <thead><tr><th>Código</th><th>Etiqueta</th><th>Cobra aquí</th><th>Pide comprobante</th><th></th></tr></thead>
                <tbody id="pagos-filas">
                <?php for ($i = 0; $i < $n(['pago_codigo', 'pago_etiqueta']); $i++): ?>
                    <tr>
                        <td data-etiqueta="Código"><input name="pago_codigo[]" value="<?= $celda('pago_codigo', $i) ?>" placeholder="transferencia"></td>
                        <td data-etiqueta="Etiqueta"><input name="pago_etiqueta[]" value="<?= $celda('pago_etiqueta', $i) ?>" placeholder="Transferencia bancaria"></td>
                        <td data-etiqueta="Cobra aquí"><?= $siNo('pago_cobra[]', (string) ($lista('pago_cobra')[$i] ?? '0')) ?></td>
                        <td data-etiqueta="Pide comprobante"><?= $siNo('pago_comprobante[]', (string) ($lista('pago_comprobante')[$i] ?? '0')) ?></td>
                        <td><button type="button" class="boton peligro" data-quitar>Quitar</button></td>
                    </tr>
                <?php endfor ?>
                </tbody>
            </table>
            <p class="ayuda">La etiqueta es la opción del participante. Comprobante en Sí solo tiene sentido si también cobra aquí.</p>
            <button type="button" class="boton claro" data-agregar="pagos-filas">Agregar forma de pago</button>
        </section>

        <section class="panel" id="equipos">
            <h2>Equipos de trabajo</h2>
            <p class="intro">Colaboración voluntaria. No cambian el precio. El formulario ya limita a dos equipos por curso.</p>
            <table class="tabla">
                <thead><tr><th>Código</th><th>Etiqueta</th><th>Solo en el curso</th><th></th></tr></thead>
                <tbody id="equipos-filas">
                <?php for ($i = 0; $i < $n(['equipo_codigo', 'equipo_etiqueta', 'equipo_curso']); $i++): ?>
                    <tr>
                        <td data-etiqueta="Código"><input name="equipo_codigo[]" value="<?= $celda('equipo_codigo', $i) ?>" placeholder="gompa"></td>
                        <td data-etiqueta="Etiqueta"><input name="equipo_etiqueta[]" value="<?= $celda('equipo_etiqueta', $i) ?>" placeholder="Preparación de gompa"></td>
                        <td data-etiqueta="Solo en el curso"><input name="equipo_curso[]" value="<?= $celda('equipo_curso', $i) ?>" placeholder="vacío = todos"></td>
                        <td><button type="button" class="boton peligro" data-quitar>Quitar</button></td>
                    </tr>
                <?php endfor ?>
                </tbody>
            </table>
            <p class="ayuda">Si el equipo es de un solo curso, escribe su código. Si sirve en todos, déjalo vacío.</p>
            <button type="button" class="boton claro" data-agregar="equipos-filas">Agregar equipo</button>
        </section>

        <section class="panel" id="ficha">
            <h2>Ficha del participante</h2>
            <p class="intro">Estos campos se pintan solos en la inscripción. Un grupo agrupa campos hijos; el padre de esos hijos es la clave del grupo.</p>
            <table class="tabla">
                <thead><tr><th>Clave</th><th>Pregunta</th><th>Tipo</th><th>Obligatorio</th><th>Opciones</th><th>Visible si</th><th>Rol</th><th>Padre</th><th></th></tr></thead>
                <tbody id="campos-filas">
                <?php for ($i = 0; $i < $n(['campo_clave', 'campo_etiqueta']); $i++): ?>
                    <?php $tipo = (string) ($lista('campo_tipo')[$i] ?? 'texto'); ?>
                    <tr class="<?= $celda('campo_padre', $i) !== '' ? 'fila-hijo' : '' ?>">
                        <td data-etiqueta="Clave"><input name="campo_clave[]" value="<?= $celda('campo_clave', $i) ?>" placeholder="centro_otro"></td>
                        <td data-etiqueta="Pregunta"><input name="campo_etiqueta[]" value="<?= $celda('campo_etiqueta', $i) ?>" placeholder="¿Cuál centro?"></td>
                        <td data-etiqueta="Tipo">
                            <select name="campo_tipo[]">
                                <?php foreach ($tiposCampo as $opcion): ?>
                                    <option value="<?= esc($opcion) ?>" <?= $tipo === $opcion ? 'selected' : '' ?>><?= esc($opcion) ?></option>
                                <?php endforeach ?>
                            </select>
                        </td>
                        <td data-etiqueta="Obligatorio"><?= $siNo('campo_requerido[]', (string) ($lista('campo_requerido')[$i] ?? '0')) ?></td>
                        <td data-etiqueta="Opciones"><textarea name="campo_opciones[]" placeholder="bogota=Bogotá&#10;otro=Otro"><?= $celda('campo_opciones', $i) ?></textarea></td>
                        <td data-etiqueta="Visible si"><input name="campo_visible[]" value="<?= $celda('campo_visible', $i) ?>" placeholder="centro=otro"></td>
                        <td data-etiqueta="Rol"><input name="campo_rol[]" value="<?= $celda('campo_rol', $i) ?>" placeholder="documento"></td>
                        <td data-etiqueta="Padre"><input name="campo_padre[]" value="<?= $celda('campo_padre', $i) ?>" placeholder="emergencia"></td>
                        <input type="hidden" name="campo_mensaje[]" value="<?= $celda('campo_mensaje', $i) ?>">
                        <td><button type="button" class="boton peligro" data-quitar>Quitar</button></td>
                    </tr>
                <?php endfor ?>
                </tbody>
            </table>
            <p class="ayuda">La clave no se muestra. La pregunta sí. Las opciones van una por línea, <code>codigo=etiqueta</code>, solo si el tipo es lista. Visible si usa <code>clave=valor</code>; varios valores se separan con <code>|</code>. Deja el rol vacío salvo en nombre, documento, correo, edad o consentimiento: documento evita duplicados y edad aplica el descuento.</p>
            <button type="button" class="boton claro" data-agregar="campos-filas">Agregar campo</button>
        </section>

        <section class="panel" id="listas">
            <h2>Listas de apoyo</h2>
            <p class="intro">Quedan guardadas con el evento. La persona ve las opciones de cada campo de la ficha, no estas listas solas.</p>
            <div class="rejilla">
                <label class="campo">Tipos de documento
                    <textarea name="tipos_documento"><?= $texto('tipos_documento') ?></textarea>
                    <p class="ayuda">Una por línea: <code>CC=Cédula de ciudadanía</code>.</p>
                </label>
                <label class="campo">Centros
                    <textarea name="centros"><?= $texto('centros') ?></textarea>
                    <p class="ayuda">Incluye <code>otro=Otro</code> si vas a abrir un texto cuando elijan otro.</p>
                </label>
                <label class="campo">Seguros
                    <textarea name="seguros"><?= $texto('seguros') ?></textarea>
                    <p class="ayuda">El dato del seguro, si lo pides, es otro campo de la ficha.</p>
                </label>
                <label class="campo">Parentescos
                    <textarea name="parentescos"><?= $texto('parentescos') ?></textarea>
                    <p class="ayuda">Misma forma <code>codigo=etiqueta</code>.</p>
                </label>
                <label class="campo">Bandas de edad
                    <textarea name="bandas_edad"><?= $texto('bandas_edad') ?></textarea>
                    <p class="ayuda">Si el campo con rol edad usa estas claves, el descuento las entiende: <code>menor_7</code>, <code>de_7_a_14</code>, <code>mayor_14</code>.</p>
                </label>
            </div>
        </section>
        <?= form_close() ?>
    </div>
</div>
<div class="pie">
    <p id="sin-guardar" hidden>Hay cambios sin guardar.</p>
    <p>Nada se publica hasta que guardes. Un recibo ya emitido no se reescribe.</p>
    <button class="boton" type="submit" form="editor">Guardar evento</button>
</div>
<template id="tpl-cursos-filas"><tr>
    <td data-etiqueta="Código"><input name="curso_codigo[]" placeholder="kpl"></td>
    <td data-etiqueta="Nombre"><input name="curso_nombre[]" placeholder="Curso KPL"></td>
    <td data-etiqueta="Tarifa"><input name="curso_tarifa[]" inputmode="numeric" placeholder="150000"></td>
    <td data-etiqueta="Inicio"><input type="datetime-local" name="curso_inicio[]"></td>
    <td data-etiqueta="Fin"><input type="datetime-local" name="curso_fin[]"></td>
    <td><button type="button" class="boton peligro" data-quitar>Quitar</button></td>
</tr></template>
<template id="tpl-comidas-filas"><tr>
    <td data-etiqueta="Id"><input name="comida_id[]" placeholder="k28_almuerzo"></td>
    <td data-etiqueta="Curso"><input name="comida_curso[]" placeholder="kpl"></td>
    <td data-etiqueta="Etiqueta"><input name="comida_etiqueta[]" placeholder="28 nov, almuerzo"></td>
    <td><button type="button" class="boton peligro" data-quitar>Quitar</button></td>
</tr></template>
<template id="tpl-franjas-filas"><tr>
    <td data-etiqueta="Id"><input name="franja_id[]" placeholder="kpl_ida_1400"></td>
    <td data-etiqueta="Curso"><input name="franja_curso[]" placeholder="kpl"></td>
    <td data-etiqueta="Sentido"><select name="franja_sentido[]"><option value=""></option><option value="ida">Llegada</option><option value="regreso">Salida</option></select></td>
    <td data-etiqueta="Etiqueta"><input name="franja_etiqueta[]" placeholder="27 nov, 14:00, Bogotá → KPL"></td>
    <td><button type="button" class="boton peligro" data-quitar>Quitar</button></td>
</tr></template>
<template id="tpl-sesiones-filas"><tr>
    <td data-etiqueta="Curso"><input name="sesion_curso[]" placeholder="kpl"></td>
    <td data-etiqueta="Id"><input name="sesion_id[]" placeholder="kpl_s1"></td>
    <td data-etiqueta="Título"><input name="sesion_titulo[]" placeholder="Enseñanza de Lama Ole"></td>
    <td data-etiqueta="Tipo"><select name="sesion_tipo[]"><option value=""></option><option value="ensenanza">Enseñanza</option><option value="meditacion">Meditación</option><option value="trabajo">Trabajo</option><option value="libre">Libre</option></select></td>
    <td data-etiqueta="Inicio"><input type="datetime-local" name="sesion_inicio[]" placeholder="2026-11-28T09:00"></td>
    <td data-etiqueta="Fin"><input type="datetime-local" name="sesion_fin[]" placeholder="2026-11-28T11:30"></td>
    <td data-etiqueta="Costo"><input name="sesion_costo[]" inputmode="numeric" placeholder="50000"></td>
    <td><button type="button" class="boton peligro" data-quitar>Quitar</button></td>
</tr></template>
<template id="tpl-pagos-filas"><tr>
    <td data-etiqueta="Código"><input name="pago_codigo[]" placeholder="transferencia"></td>
    <td data-etiqueta="Etiqueta"><input name="pago_etiqueta[]" placeholder="Transferencia bancaria"></td>
    <td data-etiqueta="Cobra aquí"><select name="pago_cobra[]"><option value="0">No</option><option value="1">Sí</option></select></td>
    <td data-etiqueta="Pide comprobante"><select name="pago_comprobante[]"><option value="0">No</option><option value="1">Sí</option></select></td>
    <td><button type="button" class="boton peligro" data-quitar>Quitar</button></td>
</tr></template>
<template id="tpl-equipos-filas"><tr>
    <td data-etiqueta="Código"><input name="equipo_codigo[]" placeholder="gompa"></td>
    <td data-etiqueta="Etiqueta"><input name="equipo_etiqueta[]" placeholder="Preparación de gompa"></td>
    <td data-etiqueta="Solo en el curso"><input name="equipo_curso[]" placeholder="vacío = todos"></td>
    <td><button type="button" class="boton peligro" data-quitar>Quitar</button></td>
</tr></template>
<template id="tpl-campos-filas"><tr>
    <td data-etiqueta="Clave"><input name="campo_clave[]" placeholder="centro_otro"></td>
    <td data-etiqueta="Pregunta"><input name="campo_etiqueta[]" placeholder="¿Cuál centro?"></td>
    <td data-etiqueta="Tipo"><select name="campo_tipo[]"><?php foreach ($tiposCampo as $opcion): ?><option value="<?= esc($opcion) ?>"><?= esc($opcion) ?></option><?php endforeach ?></select></td>
    <td data-etiqueta="Obligatorio"><select name="campo_requerido[]"><option value="0">No</option><option value="1">Sí</option></select></td>
    <td data-etiqueta="Opciones"><textarea name="campo_opciones[]" placeholder="bogota=Bogotá"></textarea></td>
    <td data-etiqueta="Visible si"><input name="campo_visible[]" placeholder="centro=otro"></td>
    <td data-etiqueta="Rol"><input name="campo_rol[]" placeholder="documento"></td>
    <td data-etiqueta="Padre"><input name="campo_padre[]" placeholder="emergencia"></td>
    <input type="hidden" name="campo_mensaje[]" value="">
    <td><button type="button" class="boton peligro" data-quitar>Quitar</button></td>
</tr></template>
<?php foreach ($lista('curso_codigo') as $codigoCurso): ?><?php if ($codigoCurso === '') { continue; } ?>
<template id="tpl-aloj-<?= esc($codigoCurso) ?>-filas"><tr>
    <td data-etiqueta="Código"><input name="alojamiento[<?= esc($codigoCurso) ?>][opciones][codigo][]" placeholder="no"></td>
    <td data-etiqueta="Etiqueta"><input name="alojamiento[<?= esc($codigoCurso) ?>][opciones][etiqueta][]" placeholder="No duermo en el curso"></td>
    <td><button type="button" class="boton peligro" data-quitar>Quitar</button></td>
</tr></template>
<?php endforeach ?>
<script>
(function(){
    var cursos = <?= json_encode((function() use ($lista): array {
        $mapa = [];
        $codigos = $lista('curso_codigo');
        $inicios = $lista('curso_inicio');
        $fines = $lista('curso_fin');
        foreach ($codigos as $i => $codigo) {
            if ($codigo === '') { continue; }
            $mapa[$codigo] = [
                'inicio' => (string) ($inicios[$i] ?? ''),
                'fin' => (string) ($fines[$i] ?? ''),
            ];
        }
        return $mapa;
    })()) ?>;

    function constrenirSesion(fila) {
        var curso = fila.querySelector('[name="sesion_curso[]"]');
        var inicio = fila.querySelector('[name="sesion_inicio[]"]');
        var fin = fila.querySelector('[name="sesion_fin[]"]');
        if (!curso || !inicio || !fin) return;

        var datos = cursos[curso.value.trim()];
        if (datos) {
            if (datos.inicio) inicio.min = datos.inicio;
            else inicio.removeAttribute('min');
            if (datos.fin) fin.max = datos.fin;
            else fin.removeAttribute('max');
        } else {
            inicio.removeAttribute('min');
            fin.removeAttribute('max');
        }
    }

    document.getElementById('sesiones-filas').addEventListener('change', function(e){
        if (e.target.name === 'sesion_curso[]') {
            constrenirSesion(e.target.closest('tr'));
        }
    });

    // Apply to existing rows on load
    document.querySelectorAll('#sesiones-filas tr').forEach(constrenirSesion);
})();
</script>
<script src="<?= base_url('js/editor.js') ?>"></script>
</body>
</html>

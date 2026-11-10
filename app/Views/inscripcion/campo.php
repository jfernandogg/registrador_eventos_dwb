<?php
/** @var \App\Domain\DefinicionCampo $campo */
/** @var array<string, mixed> $entrada */
/** @var array<string, string> $errores */

$valor = static fn (string $clave): string => (string) ($entrada[$clave] ?? '');
$error = static fn (string $clave): string => isset($errores[$clave])
    ? '<p class="error">' . esc($errores[$clave]) . '</p>'
    : '';
$visible = $campo->visibleSi === '';
if (! $visible && str_contains($campo->visibleSi, '=')) {
    [$claveSi, $esperado] = explode('=', $campo->visibleSi, 2);
    $visible = in_array($valor($claveSi), explode('|', $esperado), true);
}
$atributos = '';
if ($campo->requerido) {
    $atributos .= ' data-requerido="1" data-mensaje="' . esc($campo->mensaje !== '' ? $campo->mensaje : 'Completa ' . $campo->etiqueta . '.') . '"';
}
?>
<?php if ($campo->tipo === 'grupo'): ?>
    <fieldset>
        <legend><?= esc($campo->etiqueta) ?></legend>
        <?php foreach ($campo->hijos as $hijo): ?>
            <?= view('inscripcion/campo', ['campo' => $hijo, 'entrada' => $entrada, 'errores' => $errores]) ?>
        <?php endforeach ?>
    </fieldset>
<?php else: ?>
    <label class="<?= $campo->visibleSi !== '' ? 'depende' : '' ?><?= $visible ? '' : ' oculto' ?>"<?= $campo->visibleSi !== '' ? ' data-si="' . esc($campo->visibleSi) . '"' : '' ?>>
        <?php if ($campo->tipo === 'si_no'): ?>
            <span class="check"><input type="checkbox" name="<?= esc($campo->clave) ?>" value="1"<?= $valor($campo->clave) === '1' ? ' checked' : '' ?><?= $atributos ?>> <?= esc($campo->etiqueta) ?></span>
        <?php elseif ($campo->tipo === 'lista'): ?>
            <?= esc($campo->etiqueta) ?>
            <select name="<?= esc($campo->clave) ?>"<?= $atributos ?>>
                <option value="">Elige</option>
                <?php foreach ($campo->opciones as $codigo => $etiqueta): ?>
                    <option value="<?= esc($codigo) ?>"<?= $valor($campo->clave) === $codigo ? ' selected' : '' ?>><?= esc($etiqueta) ?></option>
                <?php endforeach ?>
            </select>
        <?php elseif ($campo->tipo === 'texto_largo'): ?>
            <?= esc($campo->etiqueta) ?>
            <textarea name="<?= esc($campo->clave) ?>"<?= $atributos ?>><?= esc($valor($campo->clave)) ?></textarea>
        <?php else: ?>
            <?= esc($campo->etiqueta) ?>
            <input type="<?= $campo->tipo === 'email' ? 'email' : 'text' ?>" name="<?= esc($campo->clave) ?>" value="<?= esc($valor($campo->clave)) ?>"<?= $atributos ?>>
        <?php endif ?>
        <?= $error($campo->clave) ?>
    </label>
<?php endif ?>

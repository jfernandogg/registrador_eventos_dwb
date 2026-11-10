<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Valida la ficha contra el esquema del evento. No tiene claves fijas.
 */
final class ValidadorFicha
{
    /**
     * @param list<DefinicionCampo> $ficha
     * @param array<string, mixed>  $entrada
     *
     * @return array<string, string>
     */
    public function errores(array $ficha, array $entrada): array
    {
        $errores = [];
        foreach ($ficha as $campo) {
            $this->revisar($campo, $entrada, $errores);
        }

        return $errores;
    }

    /**
     * @param array<string, mixed>  $entrada
     * @param array<string, string> $errores
     */
    private function revisar(DefinicionCampo $campo, array $entrada, array &$errores): void
    {
        if (! $this->visible($campo, $entrada)) {
            return;
        }
        if ($campo->tipo === 'grupo') {
            foreach ($campo->hijos as $hijo) {
                $this->revisar($hijo, $entrada, $errores);
            }

            return;
        }

        $valor = trim((string) ($entrada[$campo->clave] ?? ''));
        if ($campo->tipo === 'lista' && $valor !== '' && ! isset($campo->opciones[$valor])) {
            $errores[$campo->clave] = $campo->mensaje !== '' ? $campo->mensaje : 'Elige ' . $campo->etiqueta . '.';

            return;
        }
        if ($campo->tipo === 'email' && $valor !== '' && ! filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            $errores[$campo->clave] = $campo->mensaje !== '' ? $campo->mensaje : 'Escribe un correo válido.';

            return;
        }
        if (! $campo->requerido) {
            return;
        }
        $vacio = $campo->tipo === 'si_no' ? $valor !== '1' : $valor === '';
        if ($campo->tipo === 'lista' && ! isset($campo->opciones[$valor])) {
            $vacio = true;
        }
        if ($vacio) {
            $errores[$campo->clave] = $campo->mensaje !== '' ? $campo->mensaje : 'Completa ' . $campo->etiqueta . '.';
        }
    }

    /**
     * @param array<string, mixed> $entrada
     */
    private function visible(DefinicionCampo $campo, array $entrada): bool
    {
        if ($campo->visibleSi === '' || ! str_contains($campo->visibleSi, '=')) {
            return true;
        }
        [$clave, $esperado] = explode('=', $campo->visibleSi, 2);
        $actual = trim((string) ($entrada[$clave] ?? ''));

        return in_array($actual, explode('|', $esperado), true);
    }
}

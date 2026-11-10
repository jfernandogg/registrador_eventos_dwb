<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Extrae de una inscripción lo que logística y tesorería necesitan ver.
 * No incluye seguro, alergias ni contacto de emergencia.
 */
final class ResumenInscripcion
{
    /**
     * @param array<string, mixed> $entrada
     * @param list<string>         $notas
     *
     * @return list<string>
     */
    public static function alojamiento(array $entrada, array $notas): array
    {
        $lineas = [];
        foreach ($notas as $nota) {
            if (! is_string($nota)) {
                continue;
            }
            if (preg_match('/duerme|carpa|cama|pernocta|montaje|alojamiento/i', $nota) === 1) {
                $lineas[] = $nota;
            }
        }
        foreach ($entrada as $clave => $valor) {
            if (! is_string($clave) || is_array($valor)) {
                continue;
            }
            $texto = trim((string) $valor);
            if ($texto === '') {
                continue;
            }
            if (preg_match('/^condicion_cama_(.+)$/', $clave, $coincidencia) === 1) {
                $lineas[] = 'Condición de cama en ' . $coincidencia[1] . ': ' . $texto;
            }
            if (preg_match('/^apoyo_montaje_(.+)$/', $clave, $coincidencia) === 1 && $texto !== 'no') {
                $lineas[] = 'Apoyo en ' . $coincidencia[1] . ': ' . $texto;
            }
        }

        return array_values(array_unique($lineas));
    }
}

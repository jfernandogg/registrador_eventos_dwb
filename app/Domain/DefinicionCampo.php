<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Un campo de la ficha. La vista y el validador no conocen su clave:
 * la pinta y la exige porque el esquema del evento la trae.
 */
final class DefinicionCampo
{
    /**
     * @param array<string, string> $opciones
     * @param list<self>            $hijos
     */
    public function __construct(
        public readonly string $clave,
        public readonly string $etiqueta,
        public readonly string $tipo,
        public readonly bool $requerido = false,
        public readonly array $opciones = [],
        public readonly string $visibleSi = '',
        public readonly string $rol = '',
        public readonly string $mensaje = '',
        public readonly array $hijos = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function exportar(): array
    {
        return [
            'clave' => $this->clave,
            'etiqueta' => $this->etiqueta,
            'tipo' => $this->tipo,
            'requerido' => $this->requerido,
            'opciones' => $this->opciones,
            'visible_si' => $this->visibleSi,
            'rol' => $this->rol,
            'mensaje' => $this->mensaje,
            'hijos' => array_map(static fn (self $hijo): array => $hijo->exportar(), $this->hijos),
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function importar(array $datos): self
    {
        $hijos = [];
        foreach ($datos['hijos'] ?? [] as $hijo) {
            if (is_array($hijo)) {
                $hijos[] = self::importar($hijo);
            }
        }
        $opciones = [];
        foreach ($datos['opciones'] ?? [] as $codigo => $etiqueta) {
            $opciones[(string) $codigo] = (string) $etiqueta;
        }

        return new self(
            (string) $datos['clave'],
            (string) $datos['etiqueta'],
            (string) $datos['tipo'],
            (bool) ($datos['requerido'] ?? false),
            $opciones,
            (string) ($datos['visible_si'] ?? ''),
            (string) ($datos['rol'] ?? ''),
            (string) ($datos['mensaje'] ?? ''),
            $hijos,
        );
    }
}

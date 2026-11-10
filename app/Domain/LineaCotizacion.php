<?php

declare(strict_types=1);

namespace App\Domain;

final class LineaCotizacion
{
    public function __construct(
        public readonly string $descripcion,
        public readonly int $precioUnitario,
        public readonly int $cantidad,
        public readonly TipoLinea $tipo,
    ) {
        if ($cantidad < 1) {
            throw new \InvalidArgumentException('La cantidad de una línea debe ser al menos 1.');
        }
    }

    public function subtotal(): int
    {
        return $this->precioUnitario * $this->cantidad;
    }
}

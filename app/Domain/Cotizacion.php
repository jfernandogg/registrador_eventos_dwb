<?php

declare(strict_types=1);

namespace App\Domain;

final class Cotizacion
{
    /**
     * @param list<LineaCotizacion> $lineas
     * @param list<LineaCotizacion> $ajustes
     */
    public function __construct(
        public readonly array $lineas,
        public readonly array $ajustes,
    ) {
    }

    public function total(): int
    {
        $total = 0;

        foreach ($this->lineas as $linea) {
            $total += $linea->subtotal();
        }

        foreach ($this->ajustes as $ajuste) {
            $total += $ajuste->subtotal();
        }

        return $total;
    }

    public function subtotalDe(TipoLinea $tipo): int
    {
        $total = 0;

        foreach ($this->lineas as $linea) {
            if ($linea->tipo === $tipo) {
                $total += $linea->subtotal();
            }
        }

        return $total;
    }
}

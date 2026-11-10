<?php

declare(strict_types=1);

namespace App\Domain;

final class SeleccionCurso
{
    public function __construct(
        public readonly string $codigoCurso,
        public readonly int $comidas = 0,
        public readonly int $franjasBus = 0,
    ) {
        if ($this->comidas < 0 || $this->franjasBus < 0) {
            throw new \InvalidArgumentException('Las cantidades no pueden ser negativas.');
        }
    }
}

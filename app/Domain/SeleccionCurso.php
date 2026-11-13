<?php

declare(strict_types=1);

namespace App\Domain;

final class SeleccionCurso
{
    /** @param list<int> $costosSesiones */
    public function __construct(
        public readonly string $codigoCurso,
        public readonly int $comidas = 0,
        public readonly int $franjasBus = 0,
        public readonly array $costosSesiones = [],
        public readonly int $alojamientoCosto = 0,
    ) {
        if ($this->comidas < 0 || $this->franjasBus < 0) {
            throw new \InvalidArgumentException('Las cantidades no pueden ser negativas.');
        }
    }

    public function enModoSesiones(): bool { return $this->costosSesiones !== []; }
    public function totalSesiones(): int { return (int) array_sum($this->costosSesiones); }
}
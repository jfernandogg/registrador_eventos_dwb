<?php

declare(strict_types=1);

namespace App\Domain;

final class Curso
{
    public function __construct(
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly int $tarifa,
    ) {
        if ($this->tarifa < 0) {
            throw new \InvalidArgumentException('La tarifa del curso no puede ser negativa.');
        }
    }
}

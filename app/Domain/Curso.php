<?php

declare(strict_types=1);

namespace App\Domain;

final class Curso
{
    /**
     * @param list<Sesion> $sesiones
     */
    public function __construct(
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly int $tarifa,
        public readonly string $inicio = '',
        public readonly string $fin = '',
        public readonly array $sesiones = [],
    ) {
        if ($this->tarifa < 0) {
            throw new \InvalidArgumentException('La tarifa del curso no puede ser negativa.');
        }
    }
}
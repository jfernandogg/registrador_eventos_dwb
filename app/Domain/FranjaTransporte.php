<?php

declare(strict_types=1);

namespace App\Domain;

final class FranjaTransporte
{
    public function __construct(
        public readonly string $id,
        public readonly string $codigoCurso,
        public readonly string $sentido,
        public readonly string $etiqueta,
    ) {
    }
}

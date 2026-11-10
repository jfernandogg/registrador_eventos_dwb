<?php

declare(strict_types=1);

namespace App\Domain;

final class OpcionLista
{
    public function __construct(
        public readonly string $codigo,
        public readonly string $etiqueta,
        public readonly ?string $soloCurso = null,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Domain;

final class FormaPago
{
    public function __construct(
        public readonly string $codigo,
        public readonly string $etiqueta,
        public readonly bool $cobraEnRegistro,
        public readonly bool $pideComprobante,
    ) {
    }
}

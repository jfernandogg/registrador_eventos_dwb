<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Pesos colombianos, sin decimales. El ajuste puede ser negativo.
 */
final class Dinero
{
    public function __construct(public readonly int $pesos)
    {
    }

    public function sumar(self $otro): self
    {
        return new self($this->pesos + $otro->pesos);
    }

    public function porcentaje(int $porcentaje): self
    {
        if ($porcentaje < 0 || $porcentaje > 100) {
            throw new \InvalidArgumentException('El porcentaje debe estar entre 0 y 100.');
        }

        $redondeado = intdiv($this->pesos * $porcentaje + 50, 100);

        return new self($redondeado);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain;

final class CatalogoEvento
{
    /**
     * @param array<string, Curso> $cursos
     */
    public function __construct(
        public readonly int $precioComida,
        public readonly int $precioFranjaBus,
        private readonly array $cursos,
    ) {
        if ($this->precioComida < 0 || $this->precioFranjaBus < 0) {
            throw new \InvalidArgumentException('Los precios del catálogo no pueden ser negativos.');
        }
    }

    public function curso(string $codigo): Curso
    {
        if (! isset($this->cursos[$codigo])) {
            throw new \InvalidArgumentException(sprintf('El curso "%s" no está en este evento.', $codigo));
        }

        return $this->cursos[$codigo];
    }
}

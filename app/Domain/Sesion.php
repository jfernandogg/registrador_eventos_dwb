<?php

declare(strict_types=1);

namespace App\Domain;

final class Sesion
{
    /**
     * @param string $id       Identificador único dentro del curso (ej. "kpl_s1")
     * @param string $titulo   Nombre visible (ej. "Enseñanza de Lama Ole")
     * @param string $tipo     Tipo: ensenanza, meditacion, trabajo, libre
     * @param string $inicio   Fecha y hora ISO (ej. "2026-11-28T09:00")
     * @param string $fin      Fecha y hora ISO (ej. "2026-11-28T11:30")
     * @param int    $costo    Costo individual en pesos (0 si es gratuita)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $titulo,
        public readonly string $tipo,
        public readonly string $inicio,
        public readonly string $fin,
        public readonly int $costo,
    ) {
        if ($this->costo < 0) {
            throw new \InvalidArgumentException('El costo de la sesión no puede ser negativo.');
        }
    }
}
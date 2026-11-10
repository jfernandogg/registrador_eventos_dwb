<?php

declare(strict_types=1);

namespace App\Domain;

enum TipoLinea: string
{
    case Curso = 'curso';
    case Comida = 'comida';
    case Transporte = 'transporte';
    case Ajuste = 'ajuste';
}

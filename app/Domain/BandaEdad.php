<?php

declare(strict_types=1);

namespace App\Domain;

enum BandaEdad: string
{
    case MenorDe7 = 'menor_7';
    case De7A14 = 'de_7_a_14';
    case MayorDe14 = 'mayor_14';
}

<?php

declare(strict_types=1);

namespace Tests\Domain;

use App\Domain\ResumenInscripcion;
use PHPUnit\Framework\TestCase;

final class ResumenInscripcionTest extends TestCase
{
    public function test_separa_el_alojamiento_del_resto(): void
    {
        $lineas = ResumenInscripcion::alojamiento(
            [
                'datos_seguro' => 'Sura',
                'condicion_cama_kpl' => 'lesión de rodilla',
                'apoyo_montaje_kpl' => 'montaje',
            ],
            [
                'Carpa en kpl. Se paga en sitio y no entra en este total.',
                'Esta forma se cobra en el registro, por el total de arriba.',
            ],
        );

        $this->assertSame([
            'Carpa en kpl. Se paga en sitio y no entra en este total.',
            'Condición de cama en kpl: lesión de rodilla',
            'Apoyo en kpl: montaje',
        ], $lineas);
        $this->assertStringNotContainsString('Sura', implode(' ', $lineas));
    }
}

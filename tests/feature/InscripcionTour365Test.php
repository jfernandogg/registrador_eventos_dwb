<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class InscripcionTour365Test extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testMuestraElFormulario(): void
    {
        $result = $this->get('/e/tour-365');

        $result->assertOK();
        $result->assertSee('Tour 365 Colombia');
        $result->assertSee('Curso KPL');
    }

    public function testVerTotalDeLuis(): void
    {
        $result = $this->post('/e/tour-365', [
            'accion' => 'actualizar',
            'edad' => 'mayor_14',
            'cursos' => ['kpl'],
            'comidas' => ['k27_cena', 'k28_desayuno', 'k28_almuerzo', 'k28_cena', 'k29_desayuno'],
            'dieta' => 'vegetariano',
            'transporte_ida' => ['kpl' => 'curso'],
            'transporte_regreso' => ['kpl' => 'curso'],
            'franja_ida' => 'kpl_ida_1400',
            'franja_regreso' => 'kpl_salida_0900',
            'alojamiento_kpl' => 'carpa',
            'pernocta_extra' => 'no',
            'forma_pago' => 'transferencia',
        ]);

        $result->assertOK();
        $result->assertSee('390.000');
    }

    public function testSegundoVerTotalSumaLasComidas(): void
    {
        $result = $this->post('/e/tour-365', [
            'accion' => 'actualizar',
            'edad' => 'mayor_14',
            'cursos' => ['kpl'],
            'comidas' => ['k27_cena', 'k28_desayuno', 'k28_almuerzo', 'k28_cena', 'k29_desayuno'],
            'dieta' => 'vegetariano',
            'alojamiento_kpl' => 'carpa',
            'pernocta_extra' => 'no',
        ]);

        $result->assertOK();
        $result->assertSee('190.000');
        $result->assertSee('Resumen de tu inscripción');
    }

    public function testCotizarRespondeJson(): void
    {
        $result = $this->post('/e/tour-365/cotizar', [
            'edad' => 'mayor_14',
            'cursos' => ['kpl'],
            'comidas' => ['k27_cena'],
        ]);

        $result->assertOK();
        $json = json_decode($result->getJSON(), true);
        $this->assertSame(158000, $json['total']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Domain;

use App\Domain\AsistenteInscripcion;
use App\Domain\Tour365;
use PHPUnit\Framework\TestCase;

final class AsistenteInscripcionTest extends TestCase
{
    private AsistenteInscripcion $asistente;

    protected function setUp(): void
    {
        $this->asistente = new AsistenteInscripcion();
    }

    public function test_preview_de_luis_cobra_390000_sin_sumar_la_carpa(): void
    {
        $resultado = $this->asistente->procesar(Tour365::programa(), $this->luis(), false, false);

        $this->assertFalse($resultado->tieneErrores());
        $this->assertSame(390_000, $resultado->cotizacion?->total());
        $this->assertNotNull($resultado->formaPago);
        $this->assertTrue($resultado->formaPago->cobraEnRegistro);
        $this->assertStringContainsString('en sitio', implode(' ', $resultado->notas));
    }

    public function test_el_segundo_calculo_suma_comidas_aunque_falte_la_franja_del_bus(): void
    {
        $entrada = $this->luis();
        $entrada['franja_ida'] = '';
        $entrada['franja_regreso'] = '';

        $resultado = $this->asistente->procesar(Tour365::programa(), $entrada, false, false);

        $this->assertNotNull($resultado->cotizacion);
        $this->assertSame(190_000, $resultado->cotizacion->total());
        $this->assertArrayHasKey('franja_ida', $resultado->errores);
    }

    public function test_ruta_budista_no_pide_comprobante_y_no_cobra(): void
    {
        $entrada = $this->luis();
        $entrada['forma_pago'] = 'transferencia_ruta';

        $resultado = $this->asistente->procesar(Tour365::programa(), $entrada, true, false);

        $this->assertFalse($resultado->tieneErrores());
        $this->assertSame(390_000, $resultado->cotizacion?->total());
        $this->assertFalse($resultado->formaPago?->cobraEnRegistro);
        $this->assertSame(390_000, $resultado->congelar('2026-11-10 13:00')['total']);
    }

    public function test_transferencia_sin_comprobante_no_cierra(): void
    {
        $resultado = $this->asistente->procesar(Tour365::programa(), $this->luis(), true, false);

        $this->assertArrayHasKey('comprobante', $resultado->errores);
        $this->assertSame(390_000, $resultado->cotizacion?->total());
    }

    public function test_menor_de_7_paga_comida_y_bus(): void
    {
        $entrada = $this->luis();
        $entrada['edad'] = 'menor_7';

        $resultado = $this->asistente->procesar(Tour365::programa(), $entrada, false, false);

        $this->assertSame(240_000, $resultado->cotizacion?->total());
    }

    public function test_la_charla_sola_queda_en_cero(): void
    {
        $resultado = $this->asistente->procesar(Tour365::programa(), [
            'edad' => 'mayor_14',
            'cursos' => ['charla_bogota'],
            'transporte_ida' => ['charla_bogota' => 'carro'],
            'transporte_regreso' => ['charla_bogota' => 'publico'],
        ], false, false);

        $this->assertSame(0, $resultado->cotizacion?->total());
    }

    /**
     * @return array<string, mixed>
     */
    private function luis(): array
    {
        return [
            'nombre' => 'Luis Ejemplo',
            'tipo_documento' => 'CC',
            'documento' => '123456',
            'edad' => 'mayor_14',
            'telefono' => '3000000000',
            'correo' => 'luis@example.com',
            'centro' => 'medellin',
            'seguro' => 'eps',
            'datos_seguro' => 'Sura',
            'emergencia_nombre' => 'Ana',
            'emergencia_parentesco' => 'amigo',
            'emergencia_telefono' => '3010000000',
            'consentimiento' => '1',
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
        ];
    }
}

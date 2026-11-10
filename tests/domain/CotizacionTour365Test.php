<?php

declare(strict_types=1);

namespace Tests\Domain;

use App\Domain\BandaEdad;
use App\Domain\Cotizador;
use App\Domain\SeleccionCurso;
use App\Domain\TipoLinea;
use App\Domain\Tour365;
use PHPUnit\Framework\TestCase;

final class CotizacionTour365Test extends TestCase
{
    private Cotizador $cotizador;

    protected function setUp(): void
    {
        $this->cotizador = new Cotizador();
    }

    public function test_charla_de_un_adulto_no_cobra(): void
    {
        $cotizacion = $this->cotizador->cotizar(
            Tour365::catalogo(),
            BandaEdad::MayorDe14,
            [new SeleccionCurso('charla_bogota')],
        );

        $this->assertSame(0, $cotizacion->total());
        $this->assertSame([], $cotizacion->ajustes);
    }

    public function test_adulto_en_kpl_con_dos_buses_y_cinco_comidas(): void
    {
        $cotizacion = $this->cotizador->cotizar(
            Tour365::catalogo(),
            BandaEdad::MayorDe14,
            [new SeleccionCurso('kpl', comidas: 5, franjasBus: 2)],
        );

        $this->assertSame(150_000, $cotizacion->subtotalDe(TipoLinea::Curso));
        $this->assertSame(40_000, $cotizacion->subtotalDe(TipoLinea::Comida));
        $this->assertSame(200_000, $cotizacion->subtotalDe(TipoLinea::Transporte));
        $this->assertSame(390_000, $cotizacion->total());
        $this->assertSame([], $cotizacion->ajustes);
    }

    public function test_menor_de_7_no_paga_el_curso_pero_paga_comida_y_bus(): void
    {
        $cotizacion = $this->cotizador->cotizar(
            Tour365::catalogo(),
            BandaEdad::MenorDe7,
            [new SeleccionCurso('kpl', comidas: 5, franjasBus: 2)],
        );

        $this->assertSame(40_000, $cotizacion->subtotalDe(TipoLinea::Comida));
        $this->assertSame(200_000, $cotizacion->subtotalDe(TipoLinea::Transporte));
        $this->assertSame(-150_000, $cotizacion->ajustes[0]->subtotal());
        $this->assertSame(240_000, $cotizacion->total());
    }

    public function test_de_7_a_14_tiene_quince_porciento_solo_sobre_el_curso(): void
    {
        $cotizacion = $this->cotizador->cotizar(
            Tour365::catalogo(),
            BandaEdad::De7A14,
            [new SeleccionCurso('kpl', comidas: 5, franjasBus: 2)],
        );

        $this->assertSame(40_000, $cotizacion->subtotalDe(TipoLinea::Comida));
        $this->assertSame(200_000, $cotizacion->subtotalDe(TipoLinea::Transporte));
        $this->assertSame(-22_500, $cotizacion->ajustes[0]->subtotal());
        $this->assertSame(367_500, $cotizacion->total());
    }

    public function test_dos_cursos_con_descuento_de_edad_no_rebajan_las_comidas(): void
    {
        $cotizacion = $this->cotizador->cotizar(
            Tour365::catalogo(),
            BandaEdad::De7A14,
            [
                new SeleccionCurso('bucaramanga', comidas: 6),
                new SeleccionCurso('kpl', comidas: 5),
            ],
        );

        $this->assertSame(88_000, $cotizacion->subtotalDe(TipoLinea::Comida));
        $this->assertSame(0, $cotizacion->subtotalDe(TipoLinea::Transporte));
        $this->assertSame(283_500, $cotizacion->total());
    }

    public function test_rechaza_una_inscripcion_sin_cursos(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->cotizador->cotizar(Tour365::catalogo(), BandaEdad::MayorDe14, []);
    }

    public function test_rechaza_un_curso_que_no_esta_en_el_evento(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->cotizador->cotizar(
            Tour365::catalogo(),
            BandaEdad::MayorDe14,
            [new SeleccionCurso('medellin')],
        );
    }
}

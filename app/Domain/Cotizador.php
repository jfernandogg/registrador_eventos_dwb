<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Calcula el total en línea. El descuento de edad solo toca la tarifa del curso.
 * Comida, transporte y lo que se paga en sitio no entran aquí.
 */
final class Cotizador
{
    /**
     * @param list<SeleccionCurso> $selecciones
     */
    public function cotizar(CatalogoEvento $evento, BandaEdad $edad, array $selecciones): Cotizacion
    {
        if ($selecciones === []) {
            throw new \InvalidArgumentException('Debe elegir al menos un curso.');
        }

        $lineas = [];
        $ajustes = [];

        foreach ($selecciones as $seleccion) {
            if (! $seleccion instanceof SeleccionCurso) {
                throw new \InvalidArgumentException('Cada selección debe ser un curso del evento.');
            }

            $curso = $evento->curso($seleccion->codigoCurso);
            $lineas[] = new LineaCotizacion($curso->nombre, $curso->tarifa, 1, TipoLinea::Curso);

            if ($seleccion->comidas > 0) {
                $lineas[] = new LineaCotizacion(
                    'Alimentación ' . $curso->nombre,
                    $evento->precioComida,
                    $seleccion->comidas,
                    TipoLinea::Comida,
                );
            }

            if ($seleccion->franjasBus > 0) {
                $lineas[] = new LineaCotizacion(
                    'Transporte ' . $curso->nombre,
                    $evento->precioFranjaBus,
                    $seleccion->franjasBus,
                    TipoLinea::Transporte,
                );
            }

            $descuento = $this->descuentoCurso($edad, $curso->tarifa);
            if ($descuento > 0) {
                $ajustes[] = new LineaCotizacion(
                    $this->motivo($edad),
                    -$descuento,
                    1,
                    TipoLinea::Ajuste,
                );
            }
        }

        return new Cotizacion($lineas, $ajustes);
    }

    private function descuentoCurso(BandaEdad $edad, int $tarifa): int
    {
        return match ($edad) {
            BandaEdad::MenorDe7 => $tarifa,
            BandaEdad::De7A14 => (new Dinero($tarifa))->porcentaje(15)->pesos,
            BandaEdad::MayorDe14 => 0,
        };
    }

    private function motivo(BandaEdad $edad): string
    {
        return match ($edad) {
            BandaEdad::MenorDe7 => 'Menor de 7 años: el curso no se cobra',
            BandaEdad::De7A14 => 'Edad de 7 a 14 años: 15 % sobre la tarifa del curso',
            BandaEdad::MayorDe14 => '',
        };
    }
}

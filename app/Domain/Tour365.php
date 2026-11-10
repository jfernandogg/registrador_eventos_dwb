<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Catálogo sembrado del primer evento. El alojamiento se pregunta, pero no se cobra aquí.
 */
final class Tour365
{
    public static function catalogo(): CatalogoEvento
    {
        return self::programa()->catalogo;
    }

    public static function programa(): ProgramaEvento
    {
        $cursos = [
            'charla_bogota' => new Curso('charla_bogota', 'Charla pública Bogotá', 0),
            'bucaramanga' => new Curso('bucaramanga', 'Curso Bucaramanga', 80_000),
            'kpl' => new Curso('kpl', 'Curso KPL', 150_000),
        ];

        return new ProgramaEvento(
            nombre: 'Tour 365 Colombia',
            catalogo: new CatalogoEvento(8_000, 100_000, $cursos),
            cursos: $cursos,
            comidas: [
                new ComidaProgramada('b24_cena', 'bucaramanga', '24 nov, cena'),
                new ComidaProgramada('b25_desayuno', 'bucaramanga', '25 nov, desayuno'),
                new ComidaProgramada('b25_almuerzo', 'bucaramanga', '25 nov, almuerzo'),
                new ComidaProgramada('b25_cena', 'bucaramanga', '25 nov, cena'),
                new ComidaProgramada('b26_desayuno', 'bucaramanga', '26 nov, desayuno'),
                new ComidaProgramada('b26_almuerzo', 'bucaramanga', '26 nov, almuerzo'),
                new ComidaProgramada('k27_cena', 'kpl', '27 nov, cena'),
                new ComidaProgramada('k28_desayuno', 'kpl', '28 nov, desayuno'),
                new ComidaProgramada('k28_almuerzo', 'kpl', '28 nov, almuerzo'),
                new ComidaProgramada('k28_cena', 'kpl', '28 nov, cena'),
                new ComidaProgramada('k29_desayuno', 'kpl', '29 nov, desayuno'),
            ],
            franjas: [
                new FranjaTransporte('kpl_ida_1400', 'kpl', 'ida', '27 nov, 14:00, Living Center Bogotá → KPL'),
                new FranjaTransporte('kpl_ida_1900', 'kpl', 'ida', '27 nov, 19:00, Living Center Bogotá → KPL'),
                new FranjaTransporte('kpl_salida_0900', 'kpl', 'regreso', 'Salida 9:00, KPL → Living Center Bogotá. No se completa el resto del curso'),
            ],
            formasPago: [
                new FormaPago('efectivo', 'Pago en sitio', false, false),
                new FormaPago('ruta', 'Ruta budista', false, false),
                new FormaPago('tarjeta', 'Tarjeta de crédito (Bold)', true, true),
                new FormaPago('tarjeta_ruta', 'Tarjeta + Ruta budista', false, false),
                new FormaPago('transferencia', 'Transferencia bancaria', true, true),
                new FormaPago('transferencia_ruta', 'Transferencia + Ruta budista', false, false),
            ],
            equipos: [
                new OpcionLista('aseo', 'Aseo general'),
                new OpcionLista('traveling', 'Atención traveling teachers'),
                new OpcionLista('cafeteria', 'Cafetería'),
                new OpcionLista('camping', 'Equipos de camping', 'kpl'),
                new OpcionLista('job_center', 'Job center'),
                new OpcionLista('alimentos', 'Preparación de alimentos'),
                new OpcionLista('gompa', 'Preparación de gompa'),
            ],
            tiposDocumento: [
                'CC' => 'CC',
                'TI' => 'TI',
                'CE' => 'CE',
                'pasaporte' => 'Pasaporte',
            ],
            centros: [
                'bogota' => 'Bogotá',
                'bucaramanga' => 'Bucaramanga',
                'medellin' => 'Medellín',
                'cali' => 'Cali',
                'kpl' => 'KPL',
                'otro' => 'Otro',
            ],
            seguros: [
                'assist_card' => 'Assist Card',
                'eps' => 'EPS',
                'prepagada' => 'Prepagada',
                'otro' => 'Otro',
            ],
            parentescos: [
                'amigo' => 'Amigo/a',
                'conyuge' => 'Cónyuge',
                'hermano' => 'Hermano/a',
                'hijo' => 'Hijo/a',
                'padre' => 'Padre/Madre',
                'otro' => 'Otro',
            ],
            bandasEdad: [
                BandaEdad::MenorDe7->value => 'Menor de 7 años',
                BandaEdad::De7A14->value => 'De 7 a 14 años',
                BandaEdad::MayorDe14->value => 'Mayor de 14 años',
            ],
            alojamientos: [
                'kpl' => [
                    'opciones' => [
                        'no' => 'No duermo en el curso',
                        'carpa' => 'Carpa',
                        'cama' => 'Cama por condición especial',
                    ],
                    'pernocta_extra' => true,
                ],
            ],
        );
    }
}

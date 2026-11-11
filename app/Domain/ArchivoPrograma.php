<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Convierte un programa en datos y al revés.
 * Tour 365 es la semilla del primer evento, no una clase especial del formulario.
 */
final class ArchivoPrograma
{
    /**
     * @return array<string, mixed>
     */
    public static function exportar(ProgramaEvento $programa): array
    {
        return [
            'nombre' => $programa->nombre,
            'descripcion' => $programa->descripcion,
            'precio_comida' => $programa->catalogo->precioComida,
            'precio_franja' => $programa->catalogo->precioFranjaBus,
            'cursos' => array_map(
                static fn (Curso $curso): array => [
                    'codigo' => $curso->codigo,
                    'nombre' => $curso->nombre,
                    'tarifa' => $curso->tarifa,
                ],
                array_values($programa->cursos),
            ),
            'comidas' => array_map(
                static fn (ComidaProgramada $comida): array => [
                    'id' => $comida->id,
                    'codigo_curso' => $comida->codigoCurso,
                    'etiqueta' => $comida->etiqueta,
                ],
                $programa->comidas,
            ),
            'franjas' => array_map(
                static fn (FranjaTransporte $franja): array => [
                    'id' => $franja->id,
                    'codigo_curso' => $franja->codigoCurso,
                    'sentido' => $franja->sentido,
                    'etiqueta' => $franja->etiqueta,
                ],
                $programa->franjas,
            ),
            'formas_pago' => array_map(
                static fn (FormaPago $forma): array => [
                    'codigo' => $forma->codigo,
                    'etiqueta' => $forma->etiqueta,
                    'cobra' => $forma->cobraEnRegistro,
                    'comprobante' => $forma->pideComprobante,
                ],
                $programa->formasPago,
            ),
            'equipos' => array_map(
                static fn (OpcionLista $opcion): array => [
                    'codigo' => $opcion->codigo,
                    'etiqueta' => $opcion->etiqueta,
                    'solo_curso' => $opcion->soloCurso,
                ],
                $programa->equipos,
            ),
            'tipos_documento' => $programa->tiposDocumento,
            'centros' => $programa->centros,
            'seguros' => $programa->seguros,
            'parentescos' => $programa->parentescos,
            'bandas_edad' => $programa->bandasEdad,
            'alojamientos' => $programa->alojamientos,
            'ficha' => array_map(
                static fn (DefinicionCampo $campo): array => $campo->exportar(),
                $programa->ficha,
            ),
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function importar(array $datos): ProgramaEvento
    {
        $cursos = [];
        foreach ($datos['cursos'] ?? [] as $curso) {
            $objeto = new Curso((string) $curso['codigo'], (string) $curso['nombre'], (int) $curso['tarifa']);
            $cursos[$objeto->codigo] = $objeto;
        }

        $comidas = [];
        foreach ($datos['comidas'] ?? [] as $comida) {
            $comidas[] = new ComidaProgramada(
                (string) $comida['id'],
                (string) $comida['codigo_curso'],
                (string) $comida['etiqueta'],
            );
        }

        $franjas = [];
        foreach ($datos['franjas'] ?? [] as $franja) {
            $franjas[] = new FranjaTransporte(
                (string) $franja['id'],
                (string) $franja['codigo_curso'],
                (string) $franja['sentido'],
                (string) $franja['etiqueta'],
            );
        }

        $formas = [];
        foreach ($datos['formas_pago'] ?? [] as $forma) {
            $formas[] = new FormaPago(
                (string) $forma['codigo'],
                (string) $forma['etiqueta'],
                (bool) $forma['cobra'],
                (bool) $forma['comprobante'],
            );
        }

        $equipos = [];
        foreach ($datos['equipos'] ?? [] as $equipo) {
            $equipos[] = new OpcionLista(
                (string) $equipo['codigo'],
                (string) $equipo['etiqueta'],
                isset($equipo['solo_curso']) ? (string) $equipo['solo_curso'] : null,
            );
        }

        return new ProgramaEvento(
            (string) $datos['nombre'],
            new CatalogoEvento((int) $datos['precio_comida'], (int) $datos['precio_franja'], $cursos),
            $cursos,
            $comidas,
            $franjas,
            $formas,
            $equipos,
            self::textos($datos['tipos_documento'] ?? []),
            self::textos($datos['centros'] ?? []),
            self::textos($datos['seguros'] ?? []),
            self::textos($datos['parentescos'] ?? []),
            self::textos($datos['bandas_edad'] ?? []),
            is_array($datos['alojamientos'] ?? null) ? $datos['alojamientos'] : [],
            self::ficha($datos['ficha'] ?? []),
            (string) ($datos['descripcion'] ?? ''),
        );
    }

    /**
     * @param mixed $campos
     *
     * @return list<DefinicionCampo>
     */
    private static function ficha(mixed $campos): array
    {
        if (! is_array($campos)) {
            return [];
        }
        $ficha = [];
        foreach ($campos as $campo) {
            if (is_array($campo)) {
                $ficha[] = DefinicionCampo::importar($campo);
            }
        }

        return $ficha;
    }

    /**
     * @param mixed $valores
     *
     * @return array<string, string>
     */
    private static function textos(mixed $valores): array
    {
        if (! is_array($valores)) {
            return [];
        }
        $salida = [];
        foreach ($valores as $codigo => $etiqueta) {
            $salida[(string) $codigo] = (string) $etiqueta;
        }

        return $salida;
    }
}

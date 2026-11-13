<?php

declare(strict_types=1);

namespace App\Domain;

final class ProgramaEvento
{
    /**
     * @param array<string, Curso>          $cursos
     * @param list<ComidaProgramada>        $comidas
     * @param list<FranjaTransporte>        $franjas
     * @param list<FormaPago>               $formasPago
     * @param list<OpcionLista>             $equipos
     * @param array<string, string>         $tiposDocumento
     * @param array<string, string>         $centros
     * @param array<string, string>         $seguros
     * @param array<string, string>         $parentescos
     * @param array<string, string>         $bandasEdad
     */
    public function __construct(
        public readonly string $nombre,
        public readonly CatalogoEvento $catalogo,
        public readonly array $cursos,
        public readonly array $comidas,
        public readonly array $franjas,
        public readonly array $formasPago,
        public readonly array $equipos,
        public readonly array $tiposDocumento,
        public readonly array $centros,
        public readonly array $seguros,
        public readonly array $parentescos,
        public readonly array $bandasEdad,
        /** @var array<string, array{opciones: array<string, string>, pernocta_extra: bool}> */
        public readonly array $alojamientos = [],
        /** @var list<DefinicionCampo> */
        public readonly array $ficha = [],
        public readonly string $descripcion = '',
        public readonly string $cuentaBancaria = '',
        public readonly string $breb = '',
        public readonly string $boldUrl = '',
    ) {
    }

    public function valorRol(array $entrada, string $rol): string
    {
        $clave = $this->claveConRol($rol);

        return $clave === null ? '' : trim((string) ($entrada[$clave] ?? ''));
    }

    public function claveConRol(string $rol): ?string
    {
        foreach ($this->ficha as $campo) {
            $clave = $this->buscarRol($campo, $rol);
            if ($clave !== null) {
                return $clave;
            }
        }

        return null;
    }

    private function buscarRol(DefinicionCampo $campo, string $rol): ?string
    {
        if ($campo->rol === $rol) {
            return $campo->clave;
        }
        foreach ($campo->hijos as $hijo) {
            $clave = $this->buscarRol($hijo, $rol);
            if ($clave !== null) {
                return $clave;
            }
        }

        return null;
    }

    public function curso(string $codigo): ?Curso
    {
        return $this->cursos[$codigo] ?? null;
    }

    public function formaPago(string $codigo): ?FormaPago
    {
        foreach ($this->formasPago as $forma) {
            if ($forma->codigo === $codigo) {
                return $forma;
            }
        }

        return null;
    }

    /**
     * @return list<ComidaProgramada>
     */
    public function comidasDe(string $codigoCurso): array
    {
        return array_values(array_filter(
            $this->comidas,
            static fn (ComidaProgramada $comida): bool => $comida->codigoCurso === $codigoCurso,
        ));
    }

    /**
     * @return list<FranjaTransporte>
     */
    public function franjasDe(string $codigoCurso, string $sentido): array
    {
        return array_values(array_filter(
            $this->franjas,
            static fn (FranjaTransporte $franja): bool => $franja->codigoCurso === $codigoCurso && $franja->sentido === $sentido,
        ));
    }

    public function franja(string $id): ?FranjaTransporte
    {
        foreach ($this->franjas as $franja) {
            if ($franja->id === $id) {
                return $franja;
            }
        }

        return null;
    }

    public function comida(string $id): ?ComidaProgramada
    {
        foreach ($this->comidas as $comida) {
            if ($comida->id === $id) {
                return $comida;
            }
        }

        return null;
    }

    /**
     * @return list<OpcionLista>
     */
    public function equiposDe(string $codigoCurso): array
    {
        return array_values(array_filter(
            $this->equipos,
            static fn (OpcionLista $equipo): bool => $equipo->soloCurso === null || $equipo->soloCurso === $codigoCurso,
        ));
    }

    /**
     * @return list<Sesion>
     */
    public function sesionesDe(string $codigoCurso): array
    {
        $curso = $this->cursos[$codigoCurso] ?? null;

        return $curso !== null ? $curso->sesiones : [];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Convierte el formulario del participante en una cotización.
 * No conoce HTTP ni la base de datos.
 */
final class AsistenteInscripcion
{
    private const TRANSPORTE_PROPIO = ['carro', 'publico'];

    public function __construct(private readonly Cotizador $cotizador = new Cotizador())
    {
    }

    /**
     * @param array<string, mixed> $entrada
     */
    public function procesar(
        ProgramaEvento $programa,
        array $entrada,
        bool $confirmar,
        bool $tieneComprobante,
    ): ResultadoInscripcion {
        $entrada = $this->normalizar($programa, $entrada);
        $errores = $confirmar ? (new ValidadorFicha())->errores($programa->ficha, $entrada) : [];
        $cursos = $this->cursosElegidos($programa, $entrada);
        $errores = array_merge($errores, $this->erroresCursos($programa, $entrada, $cursos, $confirmar));

        $notas = [];
        $selecciones = [];
        if ($cursos !== [] && ! $this->hayErrorDeCursos($errores)) {
            [$selecciones, $notasCurso, $erroresCurso] = $this->armarSelecciones($programa, $entrada, $cursos, $confirmar);
            $errores = array_merge($errores, $erroresCurso);
            $notas = array_merge($notas, $notasCurso);
        }

        $forma = $programa->formaPago($entrada['forma_pago']);
        if ($confirmar && $forma === null) {
            $errores['forma_pago'] = 'Elige una forma de pago.';
        }
        if ($confirmar && $forma !== null && $forma->pideComprobante && ! $tieneComprobante) {
            $errores['comprobante'] = 'Sube el comprobante para terminar.';
        }
        if ($forma !== null) {
            $notas[] = $forma->cobraEnRegistro
                ? 'Esta forma se cobra en el registro, por el total de arriba.'
                : 'Esta forma no se cobra en el registro. El total es informativo.';
        }

        $cotizacion = null;
        $lineas = [];
        if ($selecciones !== [] && $programa->valorRol($entrada, 'edad') !== '') {
            $cotizacion = $this->cotizador->cotizar(
                $programa->catalogo,
                BandaEdad::from($programa->valorRol($entrada, 'edad')),
                $selecciones,
            );
            $lineas = $this->congelarLineas($cotizacion);
        } elseif ($confirmar && $cursos === []) {
            $errores['cursos'] = 'Elige al menos un curso.';
        }

        if ($confirmar && $cotizacion === null && $programa->claveConRol('edad') !== null && $programa->valorRol($entrada, 'edad') === '') {
            $errores[$programa->claveConRol('edad')] = 'Elige la banda de edad.';
        }

        return new ResultadoInscripcion($errores, $entrada, $cotizacion, $notas, $forma, $lineas);
    }

    /**
     * @param array<string, mixed> $entrada
     *
     * @return array<string, mixed>
     */
    private function normalizar(ProgramaEvento $programa, array $entrada): array
    {
        $texto = static fn (mixed $valor): string => trim((string) $valor);
        $lista = static function (mixed $valor): array {
            if (! is_array($valor)) {
                return [];
            }

            return array_values(array_map(static fn (mixed $item): string => trim((string) $item), $valor));
        };

        $transporte = [];
        foreach (['transporte_ida', 'transporte_regreso'] as $clave) {
            $transporte[$clave] = [];
            if (isset($entrada[$clave]) && is_array($entrada[$clave])) {
                foreach ($entrada[$clave] as $curso => $modo) {
                    $transporte[$clave][(string) $curso] = trim((string) $modo);
                }
            }
        }

        $equipos = [];
        if (isset($entrada['equipos']) && is_array($entrada['equipos'])) {
            foreach ($entrada['equipos'] as $curso => $elegidos) {
                $equipos[(string) $curso] = $lista($elegidos);
            }
        }

        return [
            'nombre' => $texto($entrada['nombre'] ?? ''),
            'tipo_documento' => $texto($entrada['tipo_documento'] ?? ''),
            'documento' => $texto($entrada['documento'] ?? ''),
            'edad' => $texto($entrada['edad'] ?? ''),
            'telefono' => $texto($entrada['telefono'] ?? ''),
            'correo' => $texto($entrada['correo'] ?? ''),
            'centro' => $texto($entrada['centro'] ?? ''),
            'centro_otro' => $texto($entrada['centro_otro'] ?? ''),
            'seguro' => $texto($entrada['seguro'] ?? ''),
            'datos_seguro' => $texto($entrada['datos_seguro'] ?? ''),
            'emergencia_nombre' => $texto($entrada['emergencia_nombre'] ?? ''),
            'emergencia_parentesco' => $texto($entrada['emergencia_parentesco'] ?? ''),
            'emergencia_parentesco_otro' => $texto($entrada['emergencia_parentesco_otro'] ?? ''),
            'emergencia_telefono' => $texto($entrada['emergencia_telefono'] ?? ''),
            'consentimiento' => $texto($entrada['consentimiento'] ?? ''),
            'cursos' => $lista($entrada['cursos'] ?? []),
            'comidas' => $lista($entrada['comidas'] ?? []),
            'dieta' => $texto($entrada['dieta'] ?? ''),
            'transporte_ida' => $transporte['transporte_ida'],
            'transporte_regreso' => $transporte['transporte_regreso'],
            'franja_ida' => $texto($entrada['franja_ida'] ?? ''),
            'franja_regreso' => $texto($entrada['franja_regreso'] ?? ''),
            'alojamiento_kpl' => $texto($entrada['alojamiento_kpl'] ?? ''),
            'condicion_cama' => $texto($entrada['condicion_cama'] ?? ''),
            'pernocta_extra' => $texto($entrada['pernocta_extra'] ?? ''),
            'apoyo_montaje' => $texto($entrada['apoyo_montaje'] ?? ''),
            'equipos' => $equipos,
            'forma_pago' => $texto($entrada['forma_pago'] ?? ''),
        ] + $this->camposDelCurso($entrada, $texto) + $this->valoresFicha($programa, $entrada, $texto);
    }

    /**
     * @param array<string, mixed>     $entrada
     * @param callable(mixed): string  $texto
     *
     * @return array<string, string>
     */
    private function valoresFicha(ProgramaEvento $programa, array $entrada, callable $texto): array
    {
        $valores = [];
        foreach ($programa->ficha as $campo) {
            $this->copiarCampo($campo, $entrada, $valores, $texto);
        }

        return $valores;
    }

    /**
     * @param array<string, mixed>     $entrada
     * @param array<string, string>    $valores
     * @param callable(mixed): string  $texto
     */
    private function copiarCampo(DefinicionCampo $campo, array $entrada, array &$valores, callable $texto): void
    {
        if ($campo->tipo === 'grupo') {
            foreach ($campo->hijos as $hijo) {
                $this->copiarCampo($hijo, $entrada, $valores, $texto);
            }

            return;
        }
        $valores[$campo->clave] = $texto($entrada[$campo->clave] ?? '');
    }

    /**
     * Conserva alojamiento y pernocta de cualquier curso, no solo de KPL.
     *
     * @param array<string, mixed> $entrada
     * @param callable(mixed): string $texto
     *
     * @return array<string, string>
     */
    private function camposDelCurso(array $entrada, callable $texto): array
    {
        $extra = [];
        foreach ($entrada as $clave => $valor) {
            if (! is_string($clave) || is_array($valor)) {
                continue;
            }
            if (preg_match('/^(alojamiento|condicion_cama|pernocta_extra|apoyo_montaje)_.+/', $clave) === 1) {
                $extra[$clave] = $texto($valor);
            }
        }

        return $extra;
    }

    /**
     * @param array<string, mixed> $entrada
     *
     * @return array<string, string>
     */
    private function erroresFicha(ProgramaEvento $programa, array $entrada): array
    {
        $errores = [];

        foreach ([
            'nombre' => 'Escribe el nombre completo.',
            'documento' => 'Escribe el número de documento.',
            'telefono' => 'Escribe el teléfono.',
            'datos_seguro' => 'Escribe el nombre de la EPS o los datos del seguro.',
            'emergencia_nombre' => 'Escribe el nombre del contacto de emergencia.',
            'emergencia_telefono' => 'Escribe el teléfono del contacto de emergencia.',
        ] as $campo => $mensaje) {
            if ($entrada[$campo] === '') {
                $errores[$campo] = $mensaje;
            }
        }

        if (! isset($programa->tiposDocumento[$entrada['tipo_documento']])) {
            $errores['tipo_documento'] = 'Elige el tipo de documento.';
        }
        if (! isset($programa->bandasEdad[$entrada['edad']])) {
            $errores['edad'] = 'Elige la banda de edad.';
        }
        if (! filter_var($entrada['correo'], FILTER_VALIDATE_EMAIL)) {
            $errores['correo'] = 'Escribe un correo válido.';
        }
        if (! isset($programa->centros[$entrada['centro']])) {
            $errores['centro'] = 'Elige el centro.';
        } elseif ($entrada['centro'] === 'otro' && $entrada['centro_otro'] === '') {
            $errores['centro_otro'] = 'Escribe cuál centro.';
        }
        if (! isset($programa->seguros[$entrada['seguro']])) {
            $errores['seguro'] = 'Elige el seguro médico.';
        }
        if (! isset($programa->parentescos[$entrada['emergencia_parentesco']])) {
            $errores['emergencia_parentesco'] = 'Elige el parentesco.';
        } elseif ($entrada['emergencia_parentesco'] === 'otro' && $entrada['emergencia_parentesco_otro'] === '') {
            $errores['emergencia_parentesco_otro'] = 'Escribe cuál parentesco.';
        }
        if ($entrada['consentimiento'] !== '1') {
            $errores['consentimiento'] = 'Hace falta autorizar el tratamiento de datos.';
        }

        return $errores;
    }

    /**
     * @param array<string, mixed> $entrada
     *
     * @return list<string>
     */
    private function cursosElegidos(ProgramaEvento $programa, array $entrada): array
    {
        $cursos = [];
        foreach ($entrada['cursos'] as $codigo) {
            if ($programa->curso($codigo) !== null) {
                $cursos[] = $codigo;
            }
        }

        return array_values(array_unique($cursos));
    }

    /**
     * @param array<string, mixed> $entrada
     * @param list<string>         $cursos
     *
     * @return array<string, string>
     */
    private function erroresCursos(ProgramaEvento $programa, array $entrada, array $cursos, bool $confirmar): array
    {
        $errores = [];
        if ($confirmar && $cursos === []) {
            $errores['cursos'] = 'Elige al menos un curso.';
        }
        if ($entrada['edad'] !== '' && ! isset($programa->bandasEdad[$programa->valorRol($entrada, 'edad') !== '' ? $programa->valorRol($entrada, 'edad') : $entrada['edad']])) {
            $errores['edad'] = 'Elige la banda de edad.';
        }
        foreach ($entrada['cursos'] as $codigo) {
            if ($programa->curso($codigo) === null) {
                $errores['cursos'] = 'Hay un curso que no pertenece a este evento.';
            }
        }

        return $errores;
    }

    /**
     * @param array<string, string> $errores
     */
    private function hayErrorDeCursos(array $errores): bool
    {
        foreach (array_keys($errores) as $campo) {
            if (in_array($campo, ['cursos', 'comidas', 'dieta', 'franja_ida', 'franja_regreso', 'transporte_ida', 'transporte_regreso', 'alojamiento_kpl', 'condicion_cama', 'pernocta_extra', 'apoyo_montaje', 'equipos'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $entrada
     * @param list<string>         $cursos
     * @return array{0: list<SeleccionCurso>, 1: list<string>, 2: array<string, string>}
     */
    private function armarSelecciones(ProgramaEvento $programa, array $entrada, array $cursos, bool $confirmar): array
    {
        $errores = [];
        $notas = [];
        $selecciones = [];
        $comidasPorCurso = array_fill_keys($cursos, 0);

        foreach ($entrada['comidas'] as $comidaId) {
            $comida = $programa->comida($comidaId);
            if ($comida === null || ! in_array($comida->codigoCurso, $cursos, true)) {
                $errores['comidas'] = 'Hay una comida que no corresponde a los cursos elegidos.';
                continue;
            }
            $comidasPorCurso[$comida->codigoCurso]++;
        }

        if (array_sum($comidasPorCurso) > 0 && ! in_array($entrada['dieta'], ['vegetariano', 'carne'], true)) {
            if ($confirmar) {
                $errores['dieta'] = 'Elige vegetariano o carne.';
            }
        } elseif (array_sum($comidasPorCurso) > 0) {
            $notas[] = 'Dieta: ' . $entrada['dieta'] . '. No cambia el precio.';
        }

        $franjasPorCurso = array_fill_keys($cursos, 0);
        foreach (['ida' => 'franja_ida', 'regreso' => 'franja_regreso'] as $sentido => $campo) {
            $claveTransporte = $sentido === 'ida' ? 'transporte_ida' : 'transporte_regreso';
            foreach ($cursos as $codigo) {
                $modo = $entrada[$claveTransporte][$codigo] ?? '';
                $curso = $programa->curso($codigo);
                $permiteCurso = $programa->franjasDe($codigo, $sentido) !== [];
                $modos = $permiteCurso ? ['carro', 'publico', 'curso'] : self::TRANSPORTE_PROPIO;
                if ($modo === '') {
                    if ($confirmar) {
                        $errores[$claveTransporte] = 'Elige cómo llegas y cómo sales de cada curso.';
                    }
                    continue;
                }
                if (! in_array($modo, $modos, true)) {
                    $errores[$claveTransporte] = 'Ese transporte no está disponible para ' . $curso?->nombre . '.';
                    continue;
                }
                if ($modo !== 'curso') {
                    $etiqueta = $modo === 'carro' ? 'carro particular' : 'transporte público';
                    $notas[] = $curso?->nombre . ', ' . $sentido . ': ' . $etiqueta . '.';
                    continue;
                }

                $franja = $programa->franja($entrada[$campo]);
                if ($franja === null || $franja->codigoCurso !== $codigo || $franja->sentido !== $sentido) {
                    $errores[$campo] = 'Elige la franja para sumar ese bus. Mientras no la elijas, ese trayecto no entra en el total.';
                    continue;
                }
                $franjasPorCurso[$codigo]++;
                $notas[] = $franja->etiqueta . '.';
            }
        }

        foreach ($cursos as $codigo) {
            if (isset($programa->alojamientos[$codigo])) {
                $notas = array_merge($notas, $this->notasAlojamiento($entrada, $errores, $confirmar, $codigo, $programa->alojamientos[$codigo]));
            }
            $equipos = $entrada['equipos'][$codigo] ?? [];
            $permitidos = array_map(
                static fn (OpcionLista $opcion): string => $opcion->codigo,
                $programa->equiposDe($codigo),
            );
            if (count($equipos) > 2) {
                $errores['equipos'] = 'Puedes marcar hasta dos equipos por curso.';
            }
            foreach ($equipos as $equipo) {
                if (! in_array($equipo, $permitidos, true)) {
                    $errores['equipos'] = 'Hay un equipo que no corresponde al curso.';
                }
            }
            if ($equipos !== [] && ! isset($errores['equipos'])) {
                $notas[] = $programa->curso($codigo)?->nombre . ', equipos: ' . implode(', ', $equipos) . '. No cambian el precio.';
            }

            $selecciones[] = new SeleccionCurso($codigo, $comidasPorCurso[$codigo], $franjasPorCurso[$codigo]);
        }

        return [$selecciones, $notas, $errores];
    }

    /**
     * @param array<string, mixed>  $entrada
     * @param array<string, string> $errores
     *
     * @return list<string>
     */
    private function notasAlojamiento(array $entrada, array &$errores, bool $confirmar, string $codigo, array $config): array
    {
        $notas = [];
        $opciones = array_keys($config['opciones'] ?? []);
        $alojamiento = (string) ($entrada['alojamiento_' . $codigo] ?? '');
        $condicion = (string) ($entrada['condicion_cama_' . $codigo] ?? $entrada['condicion_cama'] ?? '');
        $pernocta = (string) ($entrada['pernocta_extra_' . $codigo] ?? $entrada['pernocta_extra'] ?? '');
        $apoyo = (string) ($entrada['apoyo_montaje_' . $codigo] ?? $entrada['apoyo_montaje'] ?? '');
        $nombre = $codigo;

        if (! in_array($alojamiento, $opciones, true)) {
            if ($confirmar) {
                $errores['alojamiento_' . $codigo] = 'Elige el alojamiento.';
            }

            return $notas;
        }
        if ($alojamiento === 'no') {
            $notas[] = 'No duerme en ' . $nombre . '.';
        } elseif ($alojamiento === 'carpa') {
            $notas[] = 'Carpa en ' . $nombre . '. Se paga en sitio y no entra en este total.';
        } elseif ($condicion === '') {
            $errores['condicion_cama_' . $codigo] = 'Describe la condición por la que necesitas cama.';
        } else {
            $notas[] = 'Cama en ' . $nombre . ' por condición especial. Se paga en sitio y no entra en este total.';
        }

        if (($config['pernocta_extra'] ?? false) !== true) {
            return $notas;
        }
        if (! in_array($pernocta, ['si', 'no'], true)) {
            if ($confirmar) {
                $errores['pernocta_extra_' . $codigo] = 'Indica si pernoctas fuera de las fechas.';
            }
        } elseif ($pernocta === 'si') {
            if (! in_array($apoyo, ['no', 'montaje', 'desmontaje', 'ambos'], true)) {
                $errores['apoyo_montaje_' . $codigo] = 'Indica si apoyas el montaje o el desmontaje.';
            }
            $notas[] = 'Pernocta fuera de las fechas. Se paga en sitio y no entra en este total.';
        }

        return $notas;
    }

    /**
     * @return list<array{descripcion: string, subtotal: int, tipo: string}>
     */
    private function congelarLineas(Cotizacion $cotizacion): array
    {
        $lineas = [];
        foreach ([...$cotizacion->lineas, ...$cotizacion->ajustes] as $linea) {
            $lineas[] = [
                'descripcion' => $linea->descripcion,
                'subtotal' => $linea->subtotal(),
                'tipo' => $linea->tipo->value,
            ];
        }

        return $lineas;
    }
}

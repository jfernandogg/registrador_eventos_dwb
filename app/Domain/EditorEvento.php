<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Arma el programa de un evento desde el formulario del backoffice.
 * No conoce HTTP. Si el arreglo no se puede importar, no se publica.
 */
final class EditorEvento
{
    /**
     * @return array<string, mixed>
     */
    public static function vacio(string $nombre, string $slug): array
    {
        return [
            'nombre' => $nombre,
            'slug' => $slug,
            'precio_comida' => 0,
            'precio_franja' => 0,
            'cursos' => [
                ['codigo' => 'curso', 'nombre' => 'Curso', 'tarifa' => 0],
            ],
            'comidas' => [],
            'franjas' => [],
            'formas_pago' => [
                ['codigo' => 'efectivo', 'etiqueta' => 'Pago en sitio', 'cobra' => false, 'comprobante' => false],
            ],
            'cuenta_bancaria' => '',
            'breb' => '',
            'bold_url' => '',
            'equipos' => [],
            'tipos_documento' => [
                'CC' => 'CC',
                'TI' => 'TI',
                'CE' => 'CE',
                'pasaporte' => 'Pasaporte',
            ],
            'centros' => [],
            'seguros' => [],
            'parentescos' => [],
            'bandas_edad' => [
                BandaEdad::MenorDe7->value => 'Menor de 7 años',
                BandaEdad::De7A14->value => 'De 7 a 14 años',
                BandaEdad::MayorDe14->value => 'Mayor de 14 años',
            ],
            'alojamientos' => [],
            'ficha' => [
                ['clave' => 'nombre', 'etiqueta' => 'Nombre completo', 'tipo' => 'texto', 'requerido' => true, 'opciones' => [], 'visible_si' => '', 'rol' => 'nombre', 'mensaje' => 'Escribe el nombre completo.', 'hijos' => []],
                ['clave' => 'documento', 'etiqueta' => 'Documento', 'tipo' => 'texto', 'requerido' => true, 'opciones' => [], 'visible_si' => '', 'rol' => 'documento', 'mensaje' => 'Escribe el número de documento.', 'hijos' => []],
                ['clave' => 'correo', 'etiqueta' => 'Correo', 'tipo' => 'email', 'requerido' => true, 'opciones' => [], 'visible_si' => '', 'rol' => 'correo', 'mensaje' => 'Escribe un correo válido.', 'hijos' => []],
                ['clave' => 'edad', 'etiqueta' => 'Edad', 'tipo' => 'lista', 'requerido' => true, 'opciones' => [
                    BandaEdad::MenorDe7->value => 'Menor de 7 años',
                    BandaEdad::De7A14->value => 'De 7 a 14 años',
                    BandaEdad::MayorDe14->value => 'Mayor de 14 años',
                ], 'visible_si' => '', 'rol' => 'edad', 'mensaje' => 'Elige la banda de edad.', 'hijos' => []],
                ['clave' => 'consentimiento', 'etiqueta' => 'Autorizo el tratamiento de mis datos para esta inscripción.', 'tipo' => 'si_no', 'requerido' => true, 'opciones' => [], 'visible_si' => '', 'rol' => 'consentimiento', 'mensaje' => 'Hace falta autorizar el tratamiento de datos.', 'hijos' => []],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $base
     *
     * @return array{datos: array<string, mixed>, errores: list<string>}
     */
    public static function desdeFormulario(array $post, array $base): array
    {
        $errores = [];
        $cursos = self::filas($post, ['curso_codigo', 'curso_nombre', 'curso_tarifa', 'curso_inicio', 'curso_fin'], static function (array $fila) use (&$errores): ?array {
            $codigo = self::codigo($fila['curso_codigo']);
            $nombre = trim($fila['curso_nombre']);
            if ($codigo === '' && $nombre === '' && trim($fila['curso_tarifa']) === '') {
                return null;
            }
            if ($codigo === '' || $nombre === '') {
                $errores[] = 'Cada curso necesita código y nombre.';

                return null;
            }
            $tarifa = self::entero($fila['curso_tarifa']);
            if ($tarifa < 0) {
                $errores[] = 'La tarifa de ' . $nombre . ' no puede ser negativa.';
            }

            return ['codigo' => $codigo, 'nombre' => $nombre, 'tarifa' => max(0, $tarifa), 'inicio' => trim($fila['curso_inicio']), 'fin' => trim($fila['curso_fin'])];
        });
        if ($cursos === []) {
            $errores[] = 'El evento necesita al menos un curso.';
        }
        $codigos = array_column($cursos, 'codigo');
        if (count($codigos) !== count(array_unique($codigos))) {
            $errores[] = 'Hay dos cursos con el mismo código.';
        }

        $comidas = self::filas($post, ['comida_id', 'comida_curso', 'comida_etiqueta'], static function (array $fila) use ($codigos, &$errores): ?array {
            if (trim($fila['comida_id'] . $fila['comida_curso'] . $fila['comida_etiqueta']) === '') {
                return null;
            }
            $id = self::codigo($fila['comida_id']);
            $curso = self::codigo($fila['comida_curso']);
            $etiqueta = trim($fila['comida_etiqueta']);
            if ($id === '' || $etiqueta === '' || ! in_array($curso, $codigos, true)) {
                $errores[] = 'Cada comida necesita id, etiqueta y un curso que exista.';

                return null;
            }

            return ['id' => $id, 'codigo_curso' => $curso, 'etiqueta' => $etiqueta];
        });

        $franjas = self::filas($post, ['franja_id', 'franja_curso', 'franja_sentido', 'franja_etiqueta'], static function (array $fila) use ($codigos, &$errores): ?array {
            if (trim($fila['franja_id'] . $fila['franja_curso'] . $fila['franja_etiqueta']) === '') {
                return null;
            }
            $id = self::codigo($fila['franja_id']);
            $curso = self::codigo($fila['franja_curso']);
            $sentido = trim($fila['franja_sentido']);
            $etiqueta = trim($fila['franja_etiqueta']);
            if ($id === '' || $etiqueta === '' || ! in_array($curso, $codigos, true) || ! in_array($sentido, ['ida', 'regreso'], true)) {
                $errores[] = 'Cada franja necesita id, etiqueta, sentido ida o regreso, y un curso que exista.';

                return null;
            }

            return ['id' => $id, 'codigo_curso' => $curso, 'sentido' => $sentido, 'etiqueta' => $etiqueta];
        });

        $pagos = self::filas($post, ['pago_codigo', 'pago_etiqueta', 'pago_cobra', 'pago_comprobante'], static function (array $fila) use (&$errores): ?array {
            if (trim($fila['pago_codigo'] . $fila['pago_etiqueta']) === '') {
                return null;
            }
            $codigo = self::codigo($fila['pago_codigo']);
            $etiqueta = trim($fila['pago_etiqueta']);
            if ($codigo === '' || $etiqueta === '') {
                $errores[] = 'Cada forma de pago necesita código y etiqueta.';

                return null;
            }

            return [
                'codigo' => $codigo,
                'etiqueta' => $etiqueta,
                'cobra' => $fila['pago_cobra'] === '1',
                'comprobante' => $fila['pago_comprobante'] === '1',
            ];
        });
        if ($pagos === []) {
            $errores[] = 'El evento necesita al menos una forma de pago.';
        }

        $equipos = self::filas($post, ['equipo_codigo', 'equipo_etiqueta', 'equipo_curso'], static function (array $fila) use ($codigos, &$errores): ?array {
            if (trim($fila['equipo_codigo'] . $fila['equipo_etiqueta']) === '') {
                return null;
            }
            $codigo = self::codigo($fila['equipo_codigo']);
            $etiqueta = trim($fila['equipo_etiqueta']);
            $curso = self::codigo($fila['equipo_curso']);
            if ($codigo === '' || $etiqueta === '') {
                $errores[] = 'Cada equipo necesita código y etiqueta.';

                return null;
            }
            if ($curso !== '' && ! in_array($curso, $codigos, true)) {
                $errores[] = 'El equipo ' . $etiqueta . ' apunta a un curso que no existe.';
            }

            return ['codigo' => $codigo, 'etiqueta' => $etiqueta, 'solo_curso' => $curso === '' ? null : $curso];
        });

        $sesiones = self::filas($post, ['sesion_curso', 'sesion_id', 'sesion_titulo', 'sesion_tipo', 'sesion_inicio', 'sesion_fin', 'sesion_costo'], static function (array $fila) use ($codigos, &$errores): ?array {
            if (trim($fila['sesion_id'] . $fila['sesion_titulo']) === '') {
                return null;
            }
            $id = trim($fila['sesion_id']);
            $titulo = trim($fila['sesion_titulo']);
            $curso = self::codigo($fila['sesion_curso']);
            if ($id === '' || $titulo === '') {
                $errores[] = 'Cada sesión necesita id y título.';

                return null;
            }
            if ($curso === '' || ! in_array($curso, $codigos, true)) {
                $errores[] = 'La sesión ' . $titulo . ' necesita un curso válido.';

                return null;
            }

            return [
                'curso' => $curso,
                'id' => $id,
                'titulo' => $titulo,
                'tipo' => $fila['sesion_tipo'],
                'inicio' => trim($fila['sesion_inicio']),
                'fin' => trim($fila['sesion_fin']),
                'costo' => max(0, self::entero($fila['sesion_costo'] ?? 0)),
            ];
        });

        // Agregar sesiones a los cursos
        foreach ($cursos as &$obj) {
            $obj['sesiones'] = [];
        }
        unset($obj);
        foreach ($sesiones as $s) {
            if (isset($cursos[$s['curso']])) {
                $cursos[$s['curso']]['sesiones'][] = $s;
            }
        }

        $alojamientos = [];
        $publicado = $post['alojamiento'] ?? [];
        if (is_array($publicado)) {
            foreach ($codigos as $codigo) {
                $bloque = $publicado[$codigo] ?? null;
                if (! is_array($bloque)) {
                    continue;
                }
                $opcionesRaw = $bloque['opciones'] ?? null;
                if (is_array($opcionesRaw) && isset($opcionesRaw['codigo']) && is_array($opcionesRaw['codigo'])) {
                    $opciones = self::pares($opcionesRaw['codigo'], $opcionesRaw['etiqueta'] ?? []);
                } else {
                    $opciones = self::mapa((string) $opcionesRaw);
                }
                if ($opciones === []) {
                    continue;
                }
                $alojamientos[$codigo] = [
                    'opciones' => $opciones,
                    'costos' => self::costosAlojamiento($bloque['opciones']['costo'] ?? [], $opciones),
                    'pernocta_extra' => ($bloque['pernocta'] ?? '') === '1',
                ];
            }
        }

        $campos = self::filas($post, ['campo_clave', 'campo_etiqueta', 'campo_tipo', 'campo_requerido', 'campo_opciones', 'campo_visible', 'campo_rol', 'campo_mensaje', 'campo_padre'], static function (array $fila) use (&$errores): ?array {
            if (trim($fila['campo_clave'] . $fila['campo_etiqueta']) === '') {
                return null;
            }
            $clave = self::codigo($fila['campo_clave']);
            $tipo = trim($fila['campo_tipo']);
            if ($clave === '' || trim($fila['campo_etiqueta']) === '' || ! in_array($tipo, ['texto', 'email', 'telefono', 'lista', 'si_no', 'texto_largo', 'grupo'], true)) {
                $errores[] = 'Cada campo necesita clave, etiqueta y un tipo válido.';

                return null;
            }

            return [
                'clave' => $clave,
                'etiqueta' => trim($fila['campo_etiqueta']),
                'tipo' => $tipo,
                'requerido' => $fila['campo_requerido'] === '1',
                'opciones' => self::mapa($fila['campo_opciones']),
                'visible_si' => trim($fila['campo_visible']),
                'rol' => self::codigo($fila['campo_rol']),
                'mensaje' => trim($fila['campo_mensaje']),
                'padre' => self::codigo($fila['campo_padre']),
                'hijos' => [],
            ];
        });
        $ficha = self::armarFicha($campos, $errores);
        if ($ficha === []) {
            $errores[] = 'La ficha necesita al menos un campo.';
        }

        $datos = [
            'nombre' => trim((string) ($post['nombre'] ?? '')),
            'descripcion' => trim((string) ($post['descripcion'] ?? '')),
            'precio_comida' => max(0, self::entero($post['precio_comida'] ?? 0)),
            'precio_franja' => max(0, self::entero($post['precio_franja'] ?? 0)),
            'cursos' => $cursos,
            'comidas' => $comidas,
            'franjas' => $franjas,
            'formas_pago' => $pagos,
            'equipos' => $equipos,
            'tipos_documento' => self::mapa((string) ($post['tipos_documento'] ?? '')) ?: ($base['tipos_documento'] ?? []),
            'centros' => self::mapa((string) ($post['centros'] ?? '')),
            'seguros' => self::mapa((string) ($post['seguros'] ?? '')),
            'parentescos' => self::mapa((string) ($post['parentescos'] ?? '')),
            'bandas_edad' => self::mapa((string) ($post['bandas_edad'] ?? '')) ?: ($base['bandas_edad'] ?? []),
            'alojamientos' => $alojamientos,
            'ficha' => $ficha,
            'cuenta_bancaria' => trim((string) ($post['cuenta_bancaria'] ?? '')),
            'breb' => trim((string) ($post['breb'] ?? '')),
            'bold_url' => trim((string) ($post['bold_url'] ?? '')),
        ];
        if ($datos['nombre'] === '') {
            $errores[] = 'Escribe el nombre del evento.';
        }
        if ($errores !== []) {
            return ['datos' => $datos, 'errores' => $errores];
        }

        try {
            ArchivoPrograma::importar($datos);
        } catch (\Throwable $error) {
            $errores[] = 'El esquema no se puede publicar: ' . $error->getMessage();
        }

        return ['datos' => $datos, 'errores' => $errores];
    }

    /**
     * @param list<string> $costosRaw
     * @param array<string, string> $opciones
     * @return array<string, int>
     */
    private static function costosAlojamiento(array $costosRaw, array $opciones): array
    {
        $costos = [];
        $i = 0;
        foreach (array_keys($opciones) as $codigo) {
            $costos[$codigo] = max(0, self::entero($costosRaw[$i] ?? 0));
            $i++;
        }

        return $costos;
    }

    /**
     * @param array<string, mixed> $datos
     *
     * @return array<string, mixed>
     */
    public static function comoFormulario(array $datos): array
    {
        $post = [
            'nombre' => (string) ($datos['nombre'] ?? ''),
            'descripcion' => (string) ($datos['descripcion'] ?? ''),
            'precio_comida' => (string) ($datos['precio_comida'] ?? 0),
            'precio_franja' => (string) ($datos['precio_franja'] ?? 0),
            'tipos_documento' => self::texto($datos['tipos_documento'] ?? []),
            'centros' => self::texto($datos['centros'] ?? []),
            'seguros' => self::texto($datos['seguros'] ?? []),
            'parentescos' => self::texto($datos['parentescos'] ?? []),
            'bandas_edad' => self::texto($datos['bandas_edad'] ?? []),
            'alojamiento' => [],
            'cuenta_bancaria' => (string) ($datos['cuenta_bancaria'] ?? ''),
            'breb' => (string) ($datos['breb'] ?? ''),
            'bold_url' => (string) ($datos['bold_url'] ?? ''),
        ];
        foreach (['curso_codigo', 'curso_nombre', 'curso_tarifa', 'curso_inicio', 'curso_fin', 'comida_id', 'comida_curso', 'comida_etiqueta', 'franja_id', 'franja_curso', 'franja_sentido', 'franja_etiqueta', 'sesion_curso', 'sesion_id', 'sesion_titulo', 'sesion_tipo', 'sesion_inicio', 'sesion_fin', 'sesion_costo', 'pago_codigo', 'pago_etiqueta', 'pago_cobra', 'pago_comprobante', 'equipo_codigo', 'equipo_etiqueta', 'equipo_curso', 'campo_clave', 'campo_etiqueta', 'campo_tipo', 'campo_requerido', 'campo_opciones', 'campo_visible', 'campo_rol', 'campo_mensaje', 'campo_padre'] as $clave) {
            $post[$clave] = [];
        }
        foreach ($datos['cursos'] ?? [] as $curso) {
            $post['curso_codigo'][] = (string) $curso['codigo'];
            $post['curso_nombre'][] = (string) $curso['nombre'];
            $post['curso_tarifa'][] = (string) $curso['tarifa'];
            $post['curso_inicio'][] = (string) ($curso['inicio'] ?? '');
            $post['curso_fin'][] = (string) ($curso['fin'] ?? '');
            foreach ($curso['sesiones'] ?? [] as $s) {
                $post['sesion_curso'][] = (string) $curso['codigo'];
                $post['sesion_id'][] = (string) $s['id'];
                $post['sesion_titulo'][] = (string) $s['titulo'];
                $post['sesion_tipo'][] = (string) $s['tipo'];
                $post['sesion_inicio'][] = (string) $s['inicio'];
                $post['sesion_fin'][] = (string) $s['fin'];
                $post['sesion_costo'][] = (string) $s['costo'];
            }
        }
        foreach ($datos['comidas'] ?? [] as $comida) {
            $post['comida_id'][] = (string) $comida['id'];
            $post['comida_curso'][] = (string) $comida['codigo_curso'];
            $post['comida_etiqueta'][] = (string) $comida['etiqueta'];
        }
        foreach ($datos['franjas'] ?? [] as $franja) {
            $post['franja_id'][] = (string) $franja['id'];
            $post['franja_curso'][] = (string) $franja['codigo_curso'];
            $post['franja_sentido'][] = (string) $franja['sentido'];
            $post['franja_etiqueta'][] = (string) $franja['etiqueta'];
        }
        foreach ($datos['formas_pago'] ?? [] as $pago) {
            $post['pago_codigo'][] = (string) $pago['codigo'];
            $post['pago_etiqueta'][] = (string) $pago['etiqueta'];
            $post['pago_cobra'][] = ! empty($pago['cobra']) ? '1' : '0';
            $post['pago_comprobante'][] = ! empty($pago['comprobante']) ? '1' : '0';
        }
        foreach ($datos['equipos'] ?? [] as $equipo) {
            $post['equipo_codigo'][] = (string) $equipo['codigo'];
            $post['equipo_etiqueta'][] = (string) $equipo['etiqueta'];
            $post['equipo_curso'][] = (string) ($equipo['solo_curso'] ?? '');
        }
        foreach ($datos['alojamientos'] ?? [] as $codigo => $alojamiento) {
            $opciones = $alojamiento['opciones'] ?? [];
            $post['alojamiento'][$codigo] = [
                'opciones' => [
                    'codigo' => array_keys($opciones),
                    'etiqueta' => array_values($opciones),
                    'costo' => array_values($alojamiento['costos'] ?? array_fill(0, count($opciones), 0)),
                ],
                'pernocta' => ! empty($alojamiento['pernocta_extra']) ? '1' : '0',
            ];
        }
        foreach (self::filasFicha($datos['ficha'] ?? [], '') as $campo) {
            $post['campo_clave'][] = $campo['clave'];
            $post['campo_etiqueta'][] = $campo['etiqueta'];
            $post['campo_tipo'][] = $campo['tipo'];
            $post['campo_requerido'][] = $campo['requerido'] ? '1' : '0';
            $post['campo_opciones'][] = self::texto($campo['opciones']);
            $post['campo_visible'][] = $campo['visible_si'];
            $post['campo_rol'][] = $campo['rol'];
            $post['campo_mensaje'][] = $campo['mensaje'];
            $post['campo_padre'][] = $campo['padre'];
        }

        return $post;
    }

    /**
     * @param array<string, mixed> $post
     * @param list<string>         $claves
     * @param callable(array<string, string>): ?array $armar
     *
     * @return list<array<string, mixed>>
     */
    private static function filas(array $post, array $claves, callable $armar): array
    {
        $cantidad = 0;
        foreach ($claves as $clave) {
            $cantidad = max($cantidad, is_array($post[$clave] ?? null) ? count($post[$clave]) : 0);
        }
        $filas = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $fila = [];
            foreach ($claves as $clave) {
                $valores = $post[$clave] ?? [];
                $fila[$clave] = is_array($valores) ? trim((string) ($valores[$i] ?? '')) : '';
            }
            $armada = $armar($fila);
            if ($armada !== null) {
                $filas[] = $armada;
            }
        }

        return $filas;
    }

    /**
     * @param list<array<string, mixed>> $campos
     * @param list<string>               $errores
     *
     * @return list<array<string, mixed>>
     */
    private static function armarFicha(array $campos, array &$errores): array
    {
        $ficha = [];
        $hijos = [];
        foreach ($campos as $campo) {
            $padre = (string) $campo['padre'];
            unset($campo['padre']);
            if ($padre === '') {
                $ficha[] = $campo;
                continue;
            }
            $hijos[$padre][] = $campo;
        }
        foreach ($ficha as &$campo) {
            if ($campo['tipo'] === 'grupo') {
                $campo['hijos'] = $hijos[$campo['clave']] ?? [];
                unset($hijos[$campo['clave']]);
            }
        }
        unset($campo);
        if ($hijos !== []) {
            $errores[] = 'Hay campos hijos cuyo grupo no existe.';
        }

        return $ficha;
    }

    /**
     * @param list<array<string, mixed>> $ficha
     *
     * @return list<array{clave: string, etiqueta: string, tipo: string, requerido: bool, opciones: array<string, string>, visible_si: string, rol: string, mensaje: string, padre: string}>
     */
    private static function filasFicha(array $ficha, string $padre): array
    {
        $filas = [];
        foreach ($ficha as $campo) {
            $filas[] = [
                'clave' => (string) $campo['clave'],
                'etiqueta' => (string) $campo['etiqueta'],
                'tipo' => (string) $campo['tipo'],
                'requerido' => (bool) ($campo['requerido'] ?? false),
                'opciones' => is_array($campo['opciones'] ?? null) ? $campo['opciones'] : [],
                'visible_si' => (string) ($campo['visible_si'] ?? ''),
                'rol' => (string) ($campo['rol'] ?? ''),
                'mensaje' => (string) ($campo['mensaje'] ?? ''),
                'padre' => $padre,
            ];
            foreach (self::filasFicha($campo['hijos'] ?? [], (string) $campo['clave']) as $hijo) {
                $filas[] = $hijo;
            }
        }

        return $filas;
    }

    /**
     * @return array<string, string>
     */
    private static function mapa(string $texto): array
    {
        $mapa = [];
        foreach (preg_split('/\R/', $texto) ?: [] as $linea) {
            if (! str_contains($linea, '=')) {
                continue;
            }
            [$codigo, $etiqueta] = explode('=', $linea, 2);
            $codigo = trim($codigo);
            $etiqueta = trim($etiqueta);
            if ($codigo !== '' && $etiqueta !== '') {
                $mapa[$codigo] = $etiqueta;
            }
        }

        return $mapa;
    }

    /**
     * @param list<string> $codigos
     * @param list<string> $etiquetas
     * @return array<string, string>
     */
    private static function pares(array $codigos, array $etiquetas): array
    {
        $mapa = [];
        $n = max(count($codigos), count($etiquetas));
        for ($i = 0; $i < $n; $i++) {
            $codigo = trim((string) ($codigos[$i] ?? ''));
            $etiqueta = trim((string) ($etiquetas[$i] ?? ''));
            if ($codigo !== '' && $etiqueta !== '') {
                $mapa[$codigo] = $etiqueta;
            }
        }

        return $mapa;
    }

    /**
     * @param array<string, string> $mapa
     */
    private static function texto(array $mapa): string
    {
        $lineas = [];
        foreach ($mapa as $codigo => $etiqueta) {
            $lineas[] = $codigo . '=' . $etiqueta;
        }

        return implode("\n", $lineas);
    }

    private static function codigo(string $valor): string
    {
        $codigo = strtolower(trim($valor));
        $codigo = preg_replace('/[^a-z0-9_]+/', '_', $codigo) ?? '';

        return trim($codigo, '_');
    }

    private static function entero(mixed $valor): int
    {
        $texto = preg_replace('/[^\d-]/', '', (string) $valor) ?? '0';

        return (int) $texto;
    }
}

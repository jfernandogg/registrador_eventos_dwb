<?php

declare(strict_types=1);

namespace App\Persistencia;

use App\Domain\ArchivoPrograma;
use App\Domain\ProgramaEvento;

/**
 * El evento vive en MariaDB. La semilla es un JSON por slug, no una clase del formulario.
 * En las pruebas de PHPUnit no hay base: se lee el mismo JSON.
 */
final class RepositorioEventos
{
    public function programa(string $slug): ?ProgramaEvento
    {
        if (! $this->slugValido($slug)) {
            return null;
        }
        if ($this->enPruebas()) {
            return $this->desdeArchivo($slug);
        }

        try {
            $this->sembrarSiFalta($slug);
            $fila = db_connect()->table('eventos')->where('slug', $slug)->get()->getRowArray();
        } catch (\Throwable) {
            return $this->desdeArchivo($slug);
        }

        if (! is_array($fila)) {
            return null;
        }

        $datos = json_decode((string) $fila['programa_json'], true);

        return is_array($datos) ? ArchivoPrograma::importar($datos) : null;
    }

    public function id(string $slug): ?int
    {
        if ($this->enPruebas() || ! $this->slugValido($slug)) {
            return null;
        }

        try {
            $this->sembrarSiFalta($slug);
            $fila = db_connect()->table('eventos')->select('id')->where('slug', $slug)->get()->getRowArray();
        } catch (\Throwable) {
            return null;
        }

        return is_array($fila) ? (int) $fila['id'] : null;
    }

    /**
     * @return list<array{slug: string, nombre: string, descripcion: string}>
     */
    public function publicados(): array
    {
        if (! $this->enPruebas()) {
            try {
                $filas = db_connect()->table('eventos')->select('slug, nombre, programa_json')->orderBy('nombre')->get()->getResultArray();
                if ($filas !== []) {
                    return array_map(
                        static function (array $fila): array {
                            $programa = json_decode((string) $fila['programa_json'], true);

                            return [
                                'slug' => (string) $fila['slug'],
                                'nombre' => (string) $fila['nombre'],
                                'descripcion' => is_array($programa) ? (string) ($programa['descripcion'] ?? '') : '',
                                'imagen' => is_array($programa) ? (string) ($programa['imagen'] ?? '') : '',
                            ];
                        },
                        $filas,
                    );
                }
            } catch (\Throwable) {
            }
        }

        $eventos = [];
        foreach ($this->slugsSemilla() as $slug) {
            $programa = $this->desdeArchivo($slug);
            if ($programa !== null) {
                $eventos[] = ['slug' => $slug, 'nombre' => $programa->nombre, 'descripcion' => $programa->descripcion];
            }
        }

        return $eventos;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function datos(string $slug): ?array
    {
        if (! $this->slugValido($slug) || $this->enPruebas()) {
            return $this->leerSemilla($slug);
        }

        try {
            $fila = db_connect()->table('eventos')->where('slug', $slug)->get()->getRowArray();
        } catch (\Throwable) {
            return $this->leerSemilla($slug);
        }
        if (! is_array($fila)) {
            return null;
        }
        $datos = json_decode((string) $fila['programa_json'], true);

        return is_array($datos) ? $datos : null;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public function guardarPrograma(string $slug, array $datos, bool $nuevo): string
    {
        if (! $this->slugValido($slug)) {
            return 'slug';
        }
        $db = db_connect();
        $existe = $db->table('eventos')->where('slug', $slug)->countAllResults() > 0;
        if ($nuevo && $existe) {
            return 'existe';
        }
        if (! $nuevo && ! $existe) {
            return 'no_existe';
        }
        $fila = [
            'nombre' => (string) $datos['nombre'],
            'programa_json' => json_encode($datos, JSON_UNESCAPED_UNICODE),
        ];
        if ($nuevo) {
            $db->table('eventos')->insert($fila + [
                'slug' => $slug,
                'creado_en' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $db->table('eventos')->where('slug', $slug)->update($fila);
        }

        return 'ok';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function inscritos(string $slug): array
    {
        $eventoId = $this->id($slug);
        if ($eventoId === null) {
            return [];
        }

        $filas = db_connect()->table('inscripciones')
            ->where('evento_id', $eventoId)
            ->orderBy('creado_en', 'DESC')
            ->get()
            ->getResultArray();
        $salida = [];
        foreach ($filas as $fila) {
            $notas = json_decode((string) $fila['notas_json'], true);
            $entrada = json_decode((string) $fila['entrada_json'], true);
            $recibo = json_decode((string) $fila['cotizacion_json'], true);
            $salida[] = [
                'id' => (int) $fila['id'],
                'nombre' => (string) $fila['nombre'],
                'documento' => (string) $fila['documento'],
                'correo' => (string) $fila['correo'],
                'total' => (int) $fila['total'],
                'estado' => (string) $fila['estado'],
                'creado_en' => (string) $fila['creado_en'],
                'forma' => is_array($recibo) ? (string) ($recibo['forma'] ?? '') : '',
                'comprobante' => self::nombreSeguro((string) ($fila['comprobante'] ?? '')),
                'alojamiento' => \App\Domain\ResumenInscripcion::alojamiento(
                    is_array($entrada) ? $entrada : [],
                    is_array($notas) ? $notas : [],
                ),
            ];
        }

        return $salida;
    }

    public function archivoComprobante(string $slug, int $id): ?string
    {
        $eventoId = $this->id($slug);
        if ($eventoId === null || $id < 1) {
            return null;
        }
        $fila = db_connect()->table('inscripciones')
            ->select('comprobante')
            ->where('id', $id)
            ->where('evento_id', $eventoId)
            ->get()
            ->getRowArray();
        $nombre = self::nombreSeguro(is_array($fila) ? (string) ($fila['comprobante'] ?? '') : '');
        if ($nombre === null) {
            return null;
        }
        $ruta = WRITEPATH . 'uploads/comprobantes/' . $nombre;

        return is_file($ruta) ? $ruta : null;
    }

    private static function nombreSeguro(string $nombre): ?string
    {
        return preg_match('/^[A-Za-z0-9_-]+\.(pdf|jpg|jpeg|png)$/', $nombre) === 1 ? $nombre : null;
    }

    /**
     * @param array<string, mixed> $recibo
     * @param array<string, mixed> $entrada
     *
     * @return array{estado: string, id: ?int}
     */
    public function guardar(string $slug, array $recibo, array $entrada, ?string $comprobante): array
    {
        $eventoId = $this->id($slug);
        if ($eventoId === null) {
            return ['estado' => 'sin_evento', 'id' => null];
        }

        $documento = trim((string) ($recibo['documento'] ?? ''));
        if ($documento === '') {
            return ['estado' => 'sin_documento', 'id' => null];
        }

        $db = db_connect();
        $activa = $db->table('inscripciones')
            ->where('evento_id', $eventoId)
            ->where('documento_activo', $documento)
            ->countAllResults();
        if ($activa > 0) {
            return ['estado' => 'duplicada', 'id' => null];
        }

        try {
            $db->table('inscripciones')->insert([
                'evento_id' => $eventoId,
                'documento' => $documento,
                'documento_activo' => $documento,
                'nombre' => (string) ($recibo['nombre'] ?? ''),
                'correo' => (string) ($entrada['correo'] ?? ''),
                'total' => (int) ($recibo['total'] ?? 0),
                'estado' => 'activa',
                'cotizacion_json' => json_encode($recibo, JSON_UNESCAPED_UNICODE),
                'entrada_json' => json_encode($entrada, JSON_UNESCAPED_UNICODE),
                'notas_json' => json_encode($recibo['notas'] ?? [], JSON_UNESCAPED_UNICODE),
                'comprobante' => $comprobante,
                'creado_en' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $error) {
            if (str_contains($error->getMessage(), 'Duplicate')) {
                return ['estado' => 'duplicada', 'id' => null];
            }

            return ['estado' => 'error', 'id' => null];
        }

        return ['estado' => 'guardada', 'id' => (int) $db->insertID()];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detalleInscrito(string $slug, int $id): ?array
    {
        $eventoId = $this->id($slug);
        if ($eventoId === null || $id < 1) {
            return null;
        }
        $fila = db_connect()->table('inscripciones')
            ->where('id', $id)
            ->where('evento_id', $eventoId)
            ->get()
            ->getRowArray();
        if (! is_array($fila)) {
            return null;
        }
        $nombre = $this->datos($slug)['nombre'] ?? $slug;

        return [
            'id' => (int) $fila['id'],
            'nombre' => (string) $fila['nombre'],
            'documento' => (string) $fila['documento'],
            'correo' => (string) $fila['correo'],
            'total' => (int) $fila['total'],
            'estado' => (string) $fila['estado'],
            'creado_en' => (string) $fila['creado_en'],
            'comprobante' => (string) ($fila['comprobante'] ?? ''),
                            'evento' => (string) $nombre,
                            'payment_link' => (string) ($fila['payment_link'] ?? ''),
                            'payment_status' => (string) ($fila['payment_status'] ?? ''),
                            'transaction_id' => (string) ($fila['transaction_id'] ?? ''),
                            'pagado_en' => (string) ($fila['pagado_en'] ?? ''),
                            'entrada' => json_decode((string) $fila['entrada_json'], true) ?: [],
                            'cotizacion' => json_decode((string) $fila['cotizacion_json'], true) ?: [],
                            'notas' => json_decode((string) $fila['notas_json'], true) ?: [],
                        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function recibo(string $slug, int $id): ?array
    {
        if ($this->enPruebas()) {
            return null;
        }

        $eventoId = $this->id($slug);
        if ($eventoId === null) {
            return null;
        }

        $fila = db_connect()->table('inscripciones')
            ->select('inscripciones.*, eventos.nombre AS evento_nombre')
            ->join('eventos', 'eventos.id = inscripciones.evento_id')
            ->where('inscripciones.id', $id)
            ->where('inscripciones.evento_id', $eventoId)
            ->get()
            ->getRowArray();
        if (! is_array($fila)) {
            return null;
        }

        $recibo = json_decode((string) $fila['cotizacion_json'], true);

        return is_array($recibo) ? $recibo + [
            'id' => (int) $fila['id'],
            'evento' => (string) $fila['evento_nombre'],
        ] : null;
    }

    private function sembrarSiFalta(string $slug): void
    {
        $datos = $this->leerSemilla($slug);
        if ($datos === null) {
            return;
        }

        $db = db_connect();
        if ($db->table('eventos')->where('slug', $slug)->countAllResults() > 0) {
            return;
        }

        $db->table('eventos')->insert([
            'slug' => $slug,
            'nombre' => (string) $datos['nombre'],
            'programa_json' => json_encode($datos, JSON_UNESCAPED_UNICODE),
            'creado_en' => date('Y-m-d H:i:s'),
        ]);
    }

    private function desdeArchivo(string $slug): ?ProgramaEvento
    {
        $datos = $this->leerSemilla($slug);

        return is_array($datos) ? ArchivoPrograma::importar($datos) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function leerSemilla(string $slug): ?array
    {
        if (! $this->slugValido($slug)) {
            return null;
        }
        $ruta = APPPATH . 'Database/Seeds/eventos/' . $slug . '.json';
        if (! is_file($ruta)) {
            return null;
        }
        $datos = json_decode((string) file_get_contents($ruta), true);

        return is_array($datos) ? $datos : null;
    }

    /**
     * @return list<string>
     */
    private function slugsSemilla(): array
    {
        $slugs = [];
        foreach (glob(APPPATH . 'Database/Seeds/eventos/*.json') ?: [] as $archivo) {
            $slug = basename($archivo, '.json');
            if ($this->slugValido($slug)) {
                $slugs[] = $slug;
            }
        }
        sort($slugs);

        return $slugs;
    }

    private function slugValido(string $slug): bool
    {
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public function guardarPago(int $idInscripcion, array $datos): bool
    {
        $this->asegurarColumnasPago();
        if ($idInscripcion < 1) {
            return false;
        }
        $db = db_connect();
        $valores = [];
        foreach (['payment_link', 'payment_status', 'transaction_id'] as $columna) {
            if (array_key_exists($columna, $datos)) {
                $valores[$columna] = (string) $datos[$columna];
            }
        }
        if (array_key_exists('pagado_en', $datos)) {
            $valores['pagado_en'] = $datos['pagado_en'];
        }
        if ($valores === []) {
            return false;
        }

        return $db->table('inscripciones')->where('id', $idInscripcion)->update($valores);
    }

    /**
     * Busca una inscripción por la referencia externa de Bold.
     *
     * @return array<string, mixed>|null
     */
    public function buscarPorReferencia(string $referencia): ?array
    {
        $this->asegurarColumnasPago();
        $fila = db_connect()->table('inscripciones')
            ->where('payment_link', $referencia)
            ->get()
            ->getRowArray();

        return is_array($fila) ? $fila : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function inscripcionPorId(int $id): ?array
    {
        $fila = db_connect()->table('inscripciones')
            ->where('id', $id)
            ->get()
            ->getRowArray();

        return is_array($fila) ? $fila : null;
    }

    /**
     * Agrega las columnas de pago si faltan (auto-migración).
     */
    public function asegurarColumnasPago(): void
    {
        $db = db_connect();
        try {
            $columnas = $db->query('SHOW COLUMNS FROM inscripciones')->getResultArray();
        } catch (\Throwable) {
            return;
        }
        $nombres = array_map(static fn ($c) => (string) $c['Field'], $columnas);
        $faltantes = [
            'payment_link' => "VARCHAR(60) NULL",
            'payment_status' => "VARCHAR(30) NULL",
            'transaction_id' => "VARCHAR(60) NULL",
            'pagado_en' => "DATETIME NULL",
        ];
        foreach ($faltantes as $columna => $definicion) {
            if (! in_array($columna, $nombres, true)) {
                $db->query("ALTER TABLE inscripciones ADD COLUMN {$columna} {$definicion}");
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportarDatos(string $slug): array
    {
        $eventoId = $this->id($slug);
        if ($eventoId === null) {
            return [];
        }

        $filas = db_connect()->table('inscripciones')
            ->where('evento_id', $eventoId)
            ->orderBy('creado_en', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(static function (array $fila): array {
            $entrada = json_decode((string) $fila['entrada_json'], true) ?: [];
            $notas = json_decode((string) $fila['notas_json'], true) ?: [];
            $recibo = json_decode((string) $fila['cotizacion_json'], true) ?: [];

            return [
                'ID' => (int) $fila['id'],
                'Nombre' => (string) $fila['nombre'],
                'Documento' => (string) $fila['documento'],
                'Correo' => (string) $fila['correo'],
                'Teléfono' => (string) ($entrada['telefono'] ?? ''),
                'Edad' => (string) ($entrada['edad'] ?? ''),
                'Centro' => (string) ($entrada['centro'] ?? ''),
                'Seguro' => (string) ($entrada['seguro'] ?? ''),
                'Cursos' => implode(', ', (array) ($entrada['cursos'] ?? [])),
                'Forma de pago' => is_array($recibo) ? (string) ($recibo['forma'] ?? '') : '',
                'Total' => (int) $fila['total'],
                'Estado' => (string) $fila['estado'],
                'Comprobante' => self::nombreSeguro((string) ($fila['comprobante'] ?? '')),
                'Notas' => implode(' | ', is_array($notas) ? $notas : []),
                'Creado' => (string) $fila['creado_en'],
            ];
        }, $filas);
    }

    private function enPruebas(): bool
    {
        return defined('ENVIRONMENT') && ENVIRONMENT === 'testing';
    }
}

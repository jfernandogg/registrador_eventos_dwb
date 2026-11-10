<?php

declare(strict_types=1);

namespace App\Persistencia;

use App\Domain\ArchivoPrograma;
use App\Domain\ProgramaEvento;
use App\Domain\Tour365;

/**
 * El evento vive en MariaDB. Tour 365 solo siembra la primera fila.
 * En las pruebas de PHPUnit no hay base: se usa la semilla en memoria.
 */
final class RepositorioEventos
{
    public function programa(string $slug): ?ProgramaEvento
    {
        if ($this->enPruebas()) {
            return $slug === 'tour-365' ? Tour365::programa() : null;
        }

        try {
            $this->sembrarSiFalta();
            $fila = db_connect()->table('eventos')->where('slug', $slug)->get()->getRowArray();
        } catch (\Throwable) {
            return $slug === 'tour-365' ? Tour365::programa() : null;
        }

        if (! is_array($fila)) {
            return null;
        }

        $datos = json_decode((string) $fila['programa_json'], true);

        return is_array($datos) ? ArchivoPrograma::importar($datos) : null;
    }

    public function id(string $slug): ?int
    {
        if ($this->enPruebas()) {
            return null;
        }

        try {
            $this->sembrarSiFalta();
            $fila = db_connect()->table('eventos')->select('id')->where('slug', $slug)->get()->getRowArray();
        } catch (\Throwable) {
            return null;
        }

        return is_array($fila) ? (int) $fila['id'] : null;
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

    private function sembrarSiFalta(): void
    {
        $db = db_connect();
        if ($db->table('eventos')->where('slug', 'tour-365')->countAllResults() > 0) {
            return;
        }

        $db->table('eventos')->insert([
            'slug' => 'tour-365',
            'nombre' => Tour365::programa()->nombre,
            'programa_json' => json_encode(
                ArchivoPrograma::exportar(Tour365::programa()),
                JSON_UNESCAPED_UNICODE,
            ),
            'creado_en' => date('Y-m-d H:i:s'),
        ]);
    }

    private function enPruebas(): bool
    {
        return defined('ENVIRONMENT') && ENVIRONMENT === 'testing';
    }
}

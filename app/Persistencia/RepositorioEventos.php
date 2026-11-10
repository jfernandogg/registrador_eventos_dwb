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
     * @return list<array{slug: string, nombre: string}>
     */
    public function publicados(): array
    {
        $eventos = [];
        foreach ($this->slugsSemilla() as $slug) {
            $programa = $this->desdeArchivo($slug);
            if ($programa !== null) {
                $eventos[] = ['slug' => $slug, 'nombre' => $programa->nombre];
            }
        }

        return $eventos;
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

    private function enPruebas(): bool
    {
        return defined('ENVIRONMENT') && ENVIRONMENT === 'testing';
    }
}

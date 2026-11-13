<?php

namespace App\Controllers;

use App\Domain\EditorEvento;
use App\Persistencia\RepositorioEventos;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class Admin extends BaseController
{
    private RepositorioEventos $eventos;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        $this->helpers = ['form', 'url'];
        parent::initController($request, $response, $logger);
        $this->eventos = new RepositorioEventos();
    }

    public function entrar()
    {
        $this->asegurarAdmin();
        $error = '';
        if ($this->request->is('post')) {
            $correo = trim((string) $this->request->getPost('correo'));
            $clave = (string) $this->request->getPost('clave');
            $fila = db_connect()->table('usuarios')->where('correo', $correo)->get()->getRowArray();
            if (! is_array($fila) || ! password_verify($clave, (string) $fila['clave_hash'])) {
                $error = 'Correo o clave incorrectos.';
            } else {
                session()->set('admin_id', (int) $fila['id']);

                return redirect()->to($this->destino((string) $this->request->getPost('vuelve')));
            }
        }

        return view('admin/entrar', [
            'error' => $error,
            'vuelve' => $this->destino((string) $this->request->getGet('vuelve')),
        ]);
    }

    public function salir()
    {
        session()->remove('admin_id');

        return redirect()->to('/admin/entrar');
    }

    public function eventos()
    {
        return view('admin/eventos', ['eventos' => $this->eventos->publicados()]);
    }

    public function inscritos(string $slug)
    {
        if ($this->eventos->datos($slug) === null) {
            return redirect()->to('/admin');
        }
        $ver = (string) $this->request->getGet('ver');
        $filas = $this->eventos->inscritos($slug);
        if ($ver === 'comprobante') {
            $filas = array_values(array_filter($filas, static fn (array $fila): bool => $fila['comprobante'] !== null));
        } elseif ($ver === 'alojamiento') {
            $filas = array_values(array_filter($filas, static fn (array $fila): bool => $fila['alojamiento'] !== []));
        }

        return view('admin/inscritos', [
            'slug' => $slug,
            'nombre' => (string) ($this->eventos->datos($slug)['nombre'] ?? $slug),
            'filas' => $filas,
            'ver' => $ver,
        ]);
    }

    /**
     * @return string|ResponseInterface
     */
    public function detalleInscrito(string $slug, $id = null)
    {
        if ($this->eventos->datos($slug) === null) {
            return redirect()->to('/admin');
        }
        $detalle = $this->eventos->detalleInscrito($slug, (int) $id);
        if ($detalle === null) {
            return redirect()->to('/admin/eventos/' . $slug . '/inscritos');
        }
        $ver = (string) $this->request->getGet('ver');

        return view('admin/detalle-inscrito', [
            'slug' => $slug,
            'nombreEvento' => (string) ($this->eventos->datos($slug)['nombre'] ?? $slug),
            'detalle' => $detalle,
            'ver' => $ver,
        ]);
    }

    public function comprobante(string $slug, $id = null)
    {
        $ruta = $this->eventos->archivoComprobante($slug, (int) $id);
        if ($ruta === null) {
            return $this->response->setStatusCode(404)->setBody('Comprobante no encontrado.');
        }
        $nombre = basename($ruta);
        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $tipo = match ($extension) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };

        return $this->response
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Disposition', 'inline; filename="' . $nombre . '"')
            ->setContentType($tipo)
            ->setBody((string) file_get_contents($ruta));
    }

    public function nuevo()
    {
        return view('admin/parametrizar', [
            'slug' => '',
            'nuevo' => true,
            'post' => EditorEvento::comoFormulario(EditorEvento::vacio('', '')),
            'errores' => [],
        ]);
    }

    public function crear()
    {
        $slug = $this->slug((string) $this->request->getPost('slug'));
        $base = EditorEvento::vacio((string) $this->request->getPost('nombre'), $slug);
        $resultado = EditorEvento::desdeFormulario($this->request->getPost() ?? [], $base);
        if ($slug === '') {
            $resultado['errores'][] = 'El enlace del evento solo puede usar letras minúsculas, números y guiones.';
        }
        if ($resultado['errores'] === []) {
            $estado = $this->eventos->guardarPrograma($slug, $resultado['datos'], true);
            if ($estado === 'ok') {
                return redirect()->to('/admin/eventos/' . $slug);
            }
            $resultado['errores'][] = $estado === 'existe'
                ? 'Ya hay un evento con ese enlace.'
                : 'No se pudo crear el evento.';
        }

        return view('admin/parametrizar', [
            'slug' => $slug,
            'nuevo' => true,
            'post' => $this->request->getPost() ?? [],
            'errores' => $resultado['errores'],
        ]);
    }

    public function editar(string $slug)
    {
        $datos = $this->eventos->datos($slug);
        if ($datos === null) {
            return redirect()->to('/admin');
        }

        return view('admin/parametrizar', [
            'slug' => $slug,
            'nuevo' => false,
            'post' => EditorEvento::comoFormulario($datos),
            'errores' => [],
        ]);
    }

    public function guardar(string $slug)
    {
        $datos = $this->eventos->datos($slug) ?? [];
        $resultado = EditorEvento::desdeFormulario($this->request->getPost() ?? [], $datos);
        if ($resultado['errores'] === []) {
            // Procesar imagen subida
            $imagenAnterior = (string) ($datos['imagen'] ?? '');
            $nuevaImagen = $this->guardarImagenEvento($slug, $imagenAnterior);
            if ($nuevaImagen !== null) {
                $resultado['datos']['imagen'] = $nuevaImagen;
            } elseif ($imagenAnterior !== '') {
                $resultado['datos']['imagen'] = $imagenAnterior;
            }

            $estado = $this->eventos->guardarPrograma($slug, $resultado['datos'], false);
            if ($estado === 'ok') {
                return redirect()->to('/admin/eventos/' . $slug . '?guardado=1');
            }
            $resultado['errores'][] = 'No se pudo guardar el evento.';
        }

        return view('admin/parametrizar', [
            'slug' => $slug,
            'nuevo' => false,
            'post' => $this->request->getPost() ?? [],
            'errores' => $resultado['errores'],
        ]);
    }

    /**
     * @return string|null Ruta pública de la imagen guardada, o null si no se subió archivo
     */
    private function guardarImagenEvento(string $slug, string $anterior): ?string
    {
        $archivo = $this->request->getFile('imagen');
        if ($archivo === null || $archivo->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (! $archivo->isValid()) {
            return null;
        }

        $extension = strtolower($archivo->getExtension() ?: $archivo->guessExtension() ?: 'jpg');
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return null;
        }

        $directorio = FCPATH . 'uploads/eventos';
        if (! is_dir($directorio) && ! mkdir($directorio, 0777, true) && ! is_dir($directorio)) {
            return null;
        }

        // Borrar imagen anterior si existe
        if ($anterior !== '' && str_starts_with($anterior, 'uploads/eventos/')) {
            $rutaAnterior = FCPATH . $anterior;
            if (is_file($rutaAnterior)) {
                unlink($rutaAnterior);
            }
        }

        $nombre = $slug . '.' . $extension;
        try {
            $archivo->move($directorio, $nombre, true);
        } catch (\Throwable) {
            return null;
        }

        return 'uploads/eventos/' . $nombre;
    }

    private function asegurarAdmin(): void
    {
        $db = db_connect();
        $db->query(
            'CREATE TABLE IF NOT EXISTS usuarios (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                correo VARCHAR(200) NOT NULL,
                nombre VARCHAR(120) NOT NULL,
                clave_hash VARCHAR(255) NOT NULL,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY correo (correo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        if ($db->table('usuarios')->countAllResults() > 0) {
            return;
        }
        $db->table('usuarios')->insert([
            'correo' => 'admin@localhost',
            'nombre' => 'Administrador',
            'clave_hash' => password_hash('cambiala', PASSWORD_DEFAULT),
            'creado_en' => date('Y-m-d H:i:s'),
        ]);
    }

    private function destino(string $vuelve): string
    {
        $ruta = ltrim(rawurldecode($vuelve), '/');
        if (preg_match('#^admin(?:/[A-Za-z0-9_./-]*)?$#', $ruta) !== 1 || str_contains($ruta, '..')) {
            return '/admin';
        }

        return '/' . $ruta;
    }

    private function slug(string $valor): string
    {
        $slug = strtolower(trim($valor));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    public function exportar(string $slug)
    {
        if ($this->eventos->datos($slug) === null) {
            return redirect()->to('/admin');
        }

        $separador = (string) $this->request->getGet('sep');
        $encerrador = (string) $this->request->getGet('enc');
        $conComprobantes = $this->request->getGet('comprobantes') === '1';

        if (! in_array($separador, [',', ';', "\t"], true)) {
            $separador = ',';
        }
        if (! in_array($encerrador, ['"', "'", ''], true)) {
            $encerrador = '"';
        }

        $filas = $this->eventos->exportarDatos($slug);
        $nombreEvento = (string) ($this->eventos->datos($slug)['nombre'] ?? $slug);
        $base = preg_replace('/[^a-z0-9]+/', '-', strtolower($nombreEvento));
        $csv = $this->aCsv($filas, $separador, $encerrador);

        if ($conComprobantes && class_exists('ZipArchive')) {
            $zipPath = $this->construirZip($slug, $base . '.csv', $csv);
            if ($zipPath === null) {
                return redirect()->to('/admin/eventos/' . $slug . '/inscritos');
            }
            $this->servirDescarga($zipPath, $base . '.zip', 'application/zip');
        } else {
            $this->servirDescarga(null, $base . '.csv', 'text/csv; charset=utf-8', $csv);
        }

        exit;
    }

    /**
     * @return string|null Path del ZIP temporal, o null si falló
     */
    private function construirZip(string $slug, string $csvNombre, string $csvContenido): ?string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'export-') . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $zip->addFromString($csvNombre, $csvContenido);

        $inscritos = $this->eventos->inscritos($slug);
        $comprobantesDir = WRITEPATH . 'uploads/comprobantes/';
        foreach ($inscritos as $fila) {
            $nombre = (string) ($fila['comprobante'] ?? '');
            if ($nombre === '') {
                continue;
            }
            $ruta = $comprobantesDir . $nombre;
            if (is_file($ruta)) {
                $zip->addFile($ruta, 'comprobantes/' . $nombre);
            }
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * @param string|null $rutaArchivo Ruta del ZIP temporal (null si es contenido en memoria)
     */
    private function servirDescarga(?string $rutaArchivo, string $filename, string $contentType, ?string $contenido = null): void
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . ($rutaArchivo !== null ? (string) filesize($rutaArchivo) : (string) strlen((string) $contenido)));

        if ($rutaArchivo !== null) {
            readfile($rutaArchivo);
            unlink($rutaArchivo);
        } else {
            echo $contenido;
        }
    }

    /**
     * @param list<array<string, mixed>> $filas
     */
    private function aCsv(array $filas, string $sep, string $enc): string
    {
        if ($filas === []) {
            return '';
        }

        $salida = fopen('php://temp', 'r+');
        if ($salida === false) {
            return '';
        }

        // BOM for Excel UTF-8 compatibility
        fwrite($salida, "\xEF\xBB\xBF");

        // Header
        fputcsv($salida, array_keys($filas[0]), $sep, $enc);

        // Rows
        foreach ($filas as $fila) {
            fputcsv($salida, array_map(static fn ($v) => is_scalar($v) ? (string) $v : '', $fila), $sep, $enc);
        }

        rewind($salida);
        $csv = stream_get_contents($salida);
        fclose($salida);

        return $csv !== false ? $csv : '';
    }
}

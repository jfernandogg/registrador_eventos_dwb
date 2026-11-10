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

                return redirect()->to('/admin');
            }
        }

        return view('admin/entrar', ['error' => $error]);
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

    public function nuevo()
    {
        return view('admin/editor', [
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

        return view('admin/editor', [
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

        return view('admin/editor', [
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
            $estado = $this->eventos->guardarPrograma($slug, $resultado['datos'], false);
            if ($estado === 'ok') {
                return redirect()->to('/admin/eventos/' . $slug . '?guardado=1');
            }
            $resultado['errores'][] = 'No se pudo guardar el evento.';
        }

        return view('admin/editor', [
            'slug' => $slug,
            'nuevo' => false,
            'post' => $this->request->getPost() ?? [],
            'errores' => $resultado['errores'],
        ]);
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

    private function slug(string $valor): string
    {
        $slug = strtolower(trim($valor));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }
}

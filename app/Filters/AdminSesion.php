<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminSesion implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $adminId = session()->get('admin_id');
        if (! $adminId) {
            $vuelve = rawurlencode(uri_string());

            return redirect()->to('/admin/entrar?vuelve=' . $vuelve);
        }

        $usuario = db_connect()->table('usuarios')->where('id', (int) $adminId)->get()->getRowArray();
        if (! is_array($usuario) || empty($usuario['activo'])) {
            session()->remove('admin_id');

            return redirect()->to('/admin/entrar');
        }

        // Guardar rol en sesión para chequeos en controlador
        session()->set('admin_rol', (string) ($usuario['rol'] ?? 'invitado'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}

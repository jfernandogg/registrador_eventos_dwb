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
        $this->asegurarAdmin();
    }

    public function entrar()
    {
        $error = '';
        $aviso = '';
        if ($this->request->is('post')) {
            $correo = trim((string) $this->request->getPost('correo'));
            $clave = (string) $this->request->getPost('clave');
            $fila = db_connect()->table('usuarios')->where('correo', $correo)->get()->getRowArray();
            if (! is_array($fila) || (string) $fila['clave_hash'] === '' || ! password_verify($clave, (string) $fila['clave_hash'])) {
                $error = 'Correo o clave incorrectos.';
            } elseif (empty($fila['activo'])) {
                $error = 'Tu cuenta está desactivada. Contactá a un administrador.';
            } else {
                session()->set('admin_id', (int) $fila['id']);

                return redirect()->to($this->destino((string) $this->request->getPost('vuelve')));
            }
        }

        return view('admin/entrar', [
            'error' => $error,
            'vuelve' => $this->destino((string) $this->request->getGet('vuelve')),
            'aviso' => $aviso,
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

    public function webhooks()
    {
        return view('admin/webhooks', ['webhooks' => $this->eventos->webhooks()]);
    }

    public function detalleWebhook($id = null)
    {
        $detalle = $this->eventos->webhookPorId((int) $id);
        if ($detalle === null) {
            return redirect()->to('/admin/webhooks');
        }

        return view('admin/webhook-detalle', ['webhook' => $detalle]);
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
                rol VARCHAR(20) NOT NULL DEFAULT \'invitado\',
                activo TINYINT(1) NOT NULL DEFAULT 1,
                token VARCHAR(64) DEFAULT NULL,
                token_expira DATETIME DEFAULT NULL,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY correo (correo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $cols = array_column($db->query('SHOW COLUMNS FROM usuarios')->getResultArray(), 'Field');
        if (! in_array('rol', $cols, true)) {
            $db->query('ALTER TABLE usuarios ADD COLUMN rol VARCHAR(20) NOT NULL DEFAULT \'invitado\' AFTER clave_hash');
        }
        if (! in_array('activo', $cols, true)) {
            $db->query('ALTER TABLE usuarios ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER rol');
        }
        if (! in_array('token', $cols, true)) {
            $db->query('ALTER TABLE usuarios ADD COLUMN token VARCHAR(64) DEFAULT NULL AFTER activo');
        }
        if (! in_array('token_expira', $cols, true)) {
            $db->query('ALTER TABLE usuarios ADD COLUMN token_expira DATETIME DEFAULT NULL AFTER token');
        }

        // El seed admin siempre debe ser admin, incluso si se migró antes
        $db->query('UPDATE usuarios SET rol = \'admin\', activo = 1 WHERE correo = \'admin@localhost\' AND rol = \'invitado\'');

        if ($db->table('usuarios')->countAllResults() > 0) {
            return;
        }
        $db->table('usuarios')->insert([
            'correo' => 'admin@localhost',
            'nombre' => 'Administrador',
            'clave_hash' => password_hash('cambiala', PASSWORD_DEFAULT),
            'rol' => 'admin',
            'activo' => 1,
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

    // ─── Gestión de usuarios ───────────────────────────────────────────

    public function usuarios()
    {
        $this->requiereRol('admin');
        $usuarios = db_connect()->table('usuarios')->orderBy('creado_en', 'ASC')->get()->getResultArray();

        return view('admin/usuarios', [
            'usuarios' => $usuarios,
            'error' => (string) $this->request->getGet('error'),
            'ok' => (string) $this->request->getGet('ok'),
        ]);
    }

    public function crearUsuario()
    {
        $this->requiereRol('admin');
        if (! $this->request->is('post')) {
            return redirect()->to('/admin/usuarios');
        }

        $correo = trim((string) $this->request->getPost('correo'));
        $nombre = trim((string) $this->request->getPost('nombre'));

        if ($correo === '' || $nombre === '') {
            return redirect()->to('/admin/usuarios?error=' . urlencode('Correo y nombre son obligatorios.'));
        }

        $db = db_connect();
        if ($db->table('usuarios')->where('correo', $correo)->countAllResults() > 0) {
            return redirect()->to('/admin/usuarios?error=' . urlencode('Ya existe un usuario con ese correo.'));
        }

        $token = bin2hex(random_bytes(32));
        $db->table('usuarios')->insert([
            'correo' => $correo,
            'nombre' => $nombre,
            'clave_hash' => '',
            'rol' => (string) ($this->request->getPost('rol') ?: 'invitado'),
            'activo' => 1,
            'token' => $token,
            'token_expira' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'creado_en' => date('Y-m-d H:i:s'),
        ]);

        $enlace = site_url('admin/aceptar-invitacion?token=' . $token);

        // Enviar invitación por correo
        $this->enviarInvitacion($correo, $nombre, $enlace);

        return redirect()->to('/admin/usuarios?ok=' . urlencode('Usuario creado. Enlace de invitación: ' . $enlace));
    }

    private function enviarInvitacion(string $correo, string $nombre, string $enlace): bool
    {
        try {
            $email = \App\Persistencia\CorreoConfigurado::instancia();
            $email->setTo($correo);
            $email->setSubject('Invitación para el backoffice de DWB Colombia');
            $email->setMessage(
                "Hola {$nombre},\n\n" .
                "Te invitaron al backoffice de Budismo Camino del Diamante Colombia.\n\n" .
                "Para activar tu cuenta y elegir tu clave, hacé click en este enlace:\n\n" .
                "{$enlace}\n\n" .
                "El enlace vence en 7 días.\n\n" .
                "Si no esperabas esta invitación, ignorá este correo.\n\n" .
                "— DWB Colombia"
            );
            if (! $email->send(false)) {
                log_message('error', 'No se pudo enviar la invitación a ' . $correo . ': ' . $email->printDebugger());
            }

            return true;
        } catch (\Throwable $e) {
            log_message('error', 'Error enviando invitación: ' . $e->getMessage());

            return false;
        }
    }

    public function cambiarRol($id = null)
    {
        $this->requiereRol('admin');
        if (! $this->request->is('post')) {
            return redirect()->to('/admin/usuarios');
        }

        $db = db_connect();
        $nuevoRol = (string) $this->request->getPost('rol');
        if (! in_array($nuevoRol, ['admin', 'editor', 'tesorero', 'invitado'], true)) {
            return redirect()->to('/admin/usuarios?error=Rol+no+válido');
        }

        // No permitir quitarle admin al último admin
        if ($nuevoRol !== 'admin') {
            $adminActual = $db->table('usuarios')->where('id', (int) $id)->get()->getRowArray();
            if (is_array($adminActual) && $adminActual['rol'] === 'admin') {
                $totalAdmins = $db->table('usuarios')->where('rol', 'admin')->where('activo', 1)->countAllResults();
                if ($totalAdmins <= 1) {
                    return redirect()->to('/admin/usuarios?error=No+podés+quitarle+el+rol+admin+al+único+administrador');
                }
            }
        }

        $db->table('usuarios')->where('id', (int) $id)->update(['rol' => $nuevoRol]);

        return redirect()->to('/admin/usuarios?ok=Rol+actualizado');
    }

    public function toggleActivo($id = null)
    {
        $this->requiereRol('admin');
        if (! $this->request->is('post')) {
            return redirect()->to('/admin/usuarios');
        }

        $db = db_connect();
        $usuario = $db->table('usuarios')->where('id', (int) $id)->get()->getRowArray();
        if (! is_array($usuario)) {
            return redirect()->to('/admin/usuarios?error=Usuario+no+encontrado');
        }

        $nuevoEstado = empty($usuario['activo']) ? 1 : 0;

        // No permitir desactivar al último admin
        if ($nuevoEstado === 0 && $usuario['rol'] === 'admin') {
            $totalAdminsActivos = $db->table('usuarios')->where('rol', 'admin')->where('activo', 1)->countAllResults();
            if ($totalAdminsActivos <= 1) {
                return redirect()->to('/admin/usuarios?error=No+podés+desactivar+al+único+administrador+activo');
            }
        }

        $db->table('usuarios')->where('id', (int) $id)->update(['activo' => $nuevoEstado]);

        return redirect()->to('/admin/usuarios?ok=Usuario+' . ($nuevoEstado ? 'activado' : 'desactivado'));
    }

    public function aceptarInvitacion()
    {
        $db = db_connect();
        $token = (string) ($this->request->getGet('token') ?: $this->request->getPost('token'));
        $usuario = $token !== '' ? $db->table('usuarios')->where('token', $token)->get()->getRowArray() : null;
        $tokenValido = is_array($usuario) && $usuario['token_expira'] > date('Y-m-d H:i:s');

        $error = '';
        if ($this->request->is('post')) {
            if (! $tokenValido) {
                $error = 'El enlace de invitación no es válido o ya expiró.';
            } else {
                $clave = (string) $this->request->getPost('clave');
                if (strlen($clave) < 6) {
                    $error = 'La clave debe tener al menos 6 caracteres.';
                } else {
                    $db->table('usuarios')->where('id', (int) $usuario['id'])->update([
                        'clave_hash' => password_hash($clave, PASSWORD_DEFAULT),
                        'token' => null,
                        'token_expira' => null,
                        'activo' => 1,
                    ]);

                    return view('admin/entrar', [
                        'error' => '',
                        'vuelve' => '/admin',
                        'aviso' => 'Clave creada. Ya podés entrar con tu correo y la clave que elegiste.',
                    ]);
                }
            }
        }

        return view('admin/aceptar-invitacion', [
            'token' => $token,
            'tokenValido' => $tokenValido,
            'nombre' => $tokenValido ? (string) $usuario['nombre'] : '',
            'error' => $error,
        ]);
    }

    public function cambiarClave()
    {
        $error = '';
        if ($this->request->is('post')) {
            $adminId = (int) session()->get('admin_id');
            $actual = (string) $this->request->getPost('clave_actual');
            $nueva = (string) $this->request->getPost('clave_nueva');
            $db = db_connect();
            $usuario = $db->table('usuarios')->where('id', $adminId)->get()->getRowArray();

            if (! is_array($usuario) || ! password_verify($actual, (string) $usuario['clave_hash'])) {
                $error = 'La clave actual no coincide.';
            } elseif (strlen($nueva) < 6) {
                $error = 'La nueva clave debe tener al menos 6 caracteres.';
            } else {
                $db->table('usuarios')->where('id', $adminId)->update([
                    'clave_hash' => password_hash($nueva, PASSWORD_DEFAULT),
                ]);

                return redirect()->to('/admin?ok=' . urlencode('Clave actualizada.'));
            }
        }

        return view('admin/cambiar-clave', ['error' => $error]);
    }

    // ─── Integraciones ────────────────────────────────────────────────────

    public function integraciones()
    {
        $this->requiereRol('admin');
        $repositorio = new \App\Persistencia\RepositorioConfiguracion();
        $config = $repositorio->todos();

        $emailConfig = new \Config\Email();
        $valores = [
            'correo_protocolo' => (string) ($config['correo_protocolo'] ?? $emailConfig->protocol),
            'correo_smtp_host' => (string) ($config['correo_smtp_host'] ?? $emailConfig->SMTPHost),
            'correo_smtp_puerto' => (string) ($config['correo_smtp_puerto'] ?? (string) $emailConfig->SMTPPort),
            'correo_smtp_usuario' => (string) ($config['correo_smtp_usuario'] ?? $emailConfig->SMTPUser),
            'correo_smtp_clave' => (string) ($config['correo_smtp_clave'] ?? $emailConfig->SMTPPass),
            'correo_smtp_cripto' => (string) ($config['correo_smtp_cripto'] ?? $emailConfig->SMTPCrypto),
            'correo_from_email' => (string) ($config['correo_from_email'] ?? $emailConfig->fromEmail),
            'correo_from_nombre' => (string) ($config['correo_from_nombre'] ?? $emailConfig->fromName),
            'bold_api_key' => (string) ($config['bold_api_key'] ?? ''),
            'bold_secret_key' => (string) ($config['bold_secret_key'] ?? ''),
            'bold_ambiente' => (string) ($config['bold_ambiente'] ?? 'sandbox'),
        ];

        return view('admin/integraciones', [
            'valores' => $valores,
            'error' => (string) $this->request->getGet('error'),
            'ok' => (string) $this->request->getGet('ok'),
            'bold_configurado' => (new \App\Persistencia\BoldClient())->configurado(),
            'bold_sandbox' => (new \App\Persistencia\BoldClient())->esSandbox(),
        ]);
    }

    public function guardarCorreo()
    {
        $this->requiereRol('admin');
        if (! $this->request->is('post')) {
            return redirect()->to('/admin/integraciones');
        }

        $post = $this->request->getPost();
        $valores = [];
        foreach (\App\Persistencia\RepositorioConfiguracion::clavesCorreo() as $clave) {
            $valores[$clave] = trim((string) ($post[$clave] ?? ''));
        }

        $puerto = (int) ($valores['correo_smtp_puerto'] ?? 0);
        if ($valores['correo_smtp_puerto'] !== '' && ($puerto < 1 || $puerto > 65535)) {
            return redirect()->to('/admin/integraciones?error=' . urlencode('El puerto SMTP debe estar entre 1 y 65535.'));
        }

        (new \App\Persistencia\RepositorioConfiguracion())->guardar($valores);

        return redirect()->to('/admin/integraciones?ok=' . urlencode('Configuración de correo guardada.'));
    }

    public function probarCorreo()
    {
        $this->requiereRol('admin');
        if (! $this->request->is('post')) {
            return redirect()->to('/admin/integraciones');
        }
        $destino = trim((string) $this->request->getPost('destino'));
        if ($destino === '' || ! filter_var($destino, FILTER_VALIDATE_EMAIL)) {
            return redirect()->to('/admin/integraciones?error=' . urlencode('Escribe un correo de destino válido.'));
        }

        try {
            $email = \App\Persistencia\CorreoConfigurado::instancia();
            $email->setTo($destino);
            $email->setSubject('Prueba de correo — DWB Colombia');
            $email->setMessage("Si ves este mensaje, la configuración de correo funciona.\n\n— DWB Colombia");
            if (! $email->send(false)) {
                return redirect()->to('/admin/integraciones?error=' . urlencode('No se pudo enviar: ' . $email->printDebugger()));
            }
        } catch (\Throwable $e) {
            return redirect()->to('/admin/integraciones?error=' . urlencode('Error: ' . $e->getMessage()));
        }

        return redirect()->to('/admin/integraciones?ok=' . urlencode('Correo de prueba enviado a ' . $destino . '.'));
    }

    public function guardarBold()
    {
        $this->requiereRol('admin');
        if (! $this->request->is('post')) {
            return redirect()->to('/admin/integraciones');
        }

        $post = $this->request->getPost();
        $valores = [];
        foreach (\App\Persistencia\RepositorioConfiguracion::clavesBold() as $clave) {
            $valores[$clave] = trim((string) ($post[$clave] ?? ''));
        }
        $valores['bold_ambiente'] = $valores['bold_ambiente'] === 'produccion' ? 'produccion' : 'sandbox';

        (new \App\Persistencia\RepositorioConfiguracion())->guardar($valores);

        return redirect()->to('/admin/integraciones?ok=' . urlencode('Configuración de Bold guardada.'));
    }

    public function probarBold()
    {
        $this->requiereRol('admin');
        if (! $this->request->is('post')) {
            return redirect()->to('/admin/integraciones');
        }

        $bold = new \App\Persistencia\BoldClient();
        if (! $bold->configurado()) {
            return redirect()->to('/admin/integraciones?error=' . urlencode('Cargá la llave de identidad de Bold primero.'));
        }

        try {
            $link = $bold->crearLink([
                'reference' => 'PRUEBA-' . time(),
                'amount' => 1000,
                'description' => 'Prueba de integración DWB Colombia',
                'callback_url' => site_url('admin/integraciones?ok=prueba'),
                'expiration_date' => (int) (time() + 900) * 1000000000,
            ]);
        } catch (\Throwable $e) {
            return redirect()->to('/admin/integraciones?error=' . urlencode('Error: ' . $e->getMessage()));
        }

        return redirect()->to('/admin/integraciones?ok=' . urlencode('Link de prueba creado: ' . $link['url']));
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function requiereRol(string $rol): void
    {
        $adminId = (int) session()->get('admin_id');
        $fila = db_connect()->table('usuarios')->where('id', $adminId)->get()->getRowArray();
        $actual = is_array($fila) ? (string) ($fila['rol'] ?? 'invitado') : 'invitado';

        // Refrescar sesión con el valor real de BD
        session()->set('admin_rol', $actual);

        if ($rol === 'admin' && $actual !== 'admin') {
            echo view('admin/entrar', [
                'error' => 'No tenés permiso para acceder a esta sección.',
                'vuelve' => '/admin',
                'aviso' => '',
            ]);
            exit;
        }
    }

    private function generarToken(): string
    {
        return bin2hex(random_bytes(32));
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

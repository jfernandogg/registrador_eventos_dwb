<?php

namespace App\Controllers;

use App\Domain\AsistenteInscripcion;
use App\Domain\ProgramaEvento;
use App\Persistencia\RepositorioEventos;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class Inscripcion extends BaseController
{
    private AsistenteInscripcion $asistente;

    private RepositorioEventos $eventos;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        $this->helpers = ['form', 'url'];
        parent::initController($request, $response, $logger);
        $this->asistente = new AsistenteInscripcion();
        $this->eventos = new RepositorioEventos();
    }

    public function portada()
    {
        return view('inscripcion/portada', ['eventos' => $this->eventos->publicados()]);
    }

    public function index(string $slug)
    {
        return view('inscripcion/formulario', $this->datos($this->programa($slug), $slug, null, []));
    }

    public function cotizar(string $slug)
    {
        $resultado = $this->asistente->procesar(
            $this->programa($slug),
            $this->request->getPost() ?? [],
            false,
            false,
        );
        $lineas = [];
        if ($resultado->cotizacion !== null) {
            foreach ([...$resultado->cotizacion->lineas, ...$resultado->cotizacion->ajustes] as $linea) {
                $lineas[] = [
                    'descripcion' => $linea->descripcion,
                    'subtotal' => number_format($linea->subtotal(), 0, ',', '.'),
                ];
            }
        }

        return $this->response->setJSON([
            'total' => $resultado->cotizacion?->total(),
            'total_formato' => $resultado->cotizacion !== null
                ? number_format($resultado->cotizacion->total(), 0, ',', '.')
                : null,
            'lineas' => $lineas,
            'notas' => $resultado->notas,
            'errores' => $resultado->errores,
        ]);
    }

    /**
     * AJAX: crea el link de pago (Bold) con el total calculado en el servidor,
     * sin guardar aún la inscripción. Devuelve la URL de la pasarela para que
     * el participante abra la pasarela (target=_blank), pague, baje el
     * comprobante y lo suba al confirmar.
     */
    public function linkPago(string $slug): \CodeIgniter\HTTP\ResponseInterface
    {
        $programa = $this->programa($slug);
        $bold = new \App\Persistencia\BoldClient();
        if (! $bold->configurado()) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'El pago con tarjeta aún no está disponible.']);
        }

        $codigoForma = (string) ($this->request->getPost('forma_pago') ?? '');
        $forma = $programa->formaPago($codigoForma);
        if ($forma === null) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Elige primero la forma de pago.']);
        }
        if (! $forma->cobraEnRegistro) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Esta forma de pago no genera un enlace de pago.']);
        }

        $resultado = $this->asistente->procesar($programa, $this->request->getPost() ?? [], false, false);
        $total = $resultado->cotizacion?->total();
        if ($resultado->errores !== [] || $total === null || $total <= 0) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Faltan datos para calcular el total. Completá el formulario (incluido el curso y tus datos) antes de generar el enlace.',
            ]);
        }

        try {
            $link = $bold->crearLink([
                'reference' => 'REG-' . strtoupper(bin2hex(random_bytes(5))),
                'amount' => $total,
                'description' => mb_substr($programa->nombre, 0, 100),
                'payer_email' => trim((string) ($this->request->getPost('correo') ?? '')),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['error' => 'No se pudo crear el enlace de pago: ' . $e->getMessage()]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            'url' => $link['url'] ?? $bold->urlDelLink((string) $link['payment_link']),
            'payment_link' => $link['payment_link'],
        ]);
    }

    public function enviar(string $slug)
    {
        $programa = $this->programa($slug);
        // Enlace de pago que se haya generado antes de confirmar.
        $linkGenerado = trim((string) ($this->request->getPost('payment_link_generado') ?? ''));
        $confirmar = $this->request->getPost('accion') === 'confirmar';
        $archivo = $this->request->getFile('comprobante');
        $guardado = $this->nombreGuardado((string) ($this->request->getPost('comprobante_guardado') ?? ''));
        $claveDocumento = $programa->claveConRol('documento') ?? 'documento';
        $carga = $this->guardarComprobante($archivo, (string) ($this->request->getPost($claveDocumento) ?? 'comprobante'));
        if ($carga['nombre'] !== null) {
            $guardado = $carga['nombre'];
        }

        $resultado = $this->asistente->procesar(
            $programa,
            $this->request->getPost() ?? [],
            $confirmar,
            $guardado !== null,
        );
        $entrada = $resultado->entrada;
        $entrada['comprobante_guardado'] = $guardado ?? '';
        $errores = $resultado->errores;
        if ($carga['error'] !== null) {
            $errores['comprobante'] = $carga['error'];
        }

        if ($confirmar && $errores === []) {
            $recibo = $resultado->congelar(date('Y-m-d H:i'));
            $recibo['comprobante'] = $guardado ?? '';
            $recibo['documento'] = $programa->valorRol($entrada, 'documento');
            $recibo['nombre'] = $programa->valorRol($entrada, 'nombre') ?: (string) $recibo['nombre'];
            $guardadoDb = $this->eventos->guardar($slug, $recibo, $entrada, $guardado);
            if ($guardadoDb['estado'] === 'duplicada') {
                $errores[$claveDocumento] = 'Ya hay una inscripción activa con este documento en este evento.';
            } elseif ($guardadoDb['estado'] !== 'guardada') {
                $errores[$claveDocumento] = 'No se pudo guardar la inscripción. Intenta de nuevo.';
            } else {
                // Si se generó un link de pago antes de confirmar, asociarlo.
                if ($linkGenerado !== '' && preg_match('/^LNK_[A-Za-z0-9]+$/', $linkGenerado) === 1) {
                    $this->eventos->guardarPago((int) $guardadoDb['id'], [
                        'payment_link' => $linkGenerado,
                        'payment_status' => 'ACTIVE',
                    ]);
                }

                $this->notificarInscripcion($slug, $recibo, $entrada, $programa, (int) $guardadoDb['id']);

                return redirect()->to('/e/' . $slug . '/recibo/' . $guardadoDb['id']);
            }
        }

        return view('inscripcion/formulario', $this->datos($programa, $slug, $resultado, $entrada, $errores));
    }

    public function recibo(string $slug, $id = null)
    {
        $recibo = $this->eventos->recibo($slug, (int) $id);
        if (! is_array($recibo)) {
            return redirect()->to('/e/' . $slug);
        }

        // Fallback: si hay un link de Bold pendiente, consultar el estado actual.
        if (($recibo['payment_link'] ?? '') !== '' && ($recibo['payment_status'] ?? '') !== 'PAID') {
            try {
                $bold = new \App\Persistencia\BoldClient();
                if ($bold->configurado()) {
                    $datos = $bold->consultarLink((string) $recibo['payment_link']);
                    $estado = (string) ($datos['status'] ?? '');
                    if ($estado !== '' && $estado !== (string) $recibo['payment_status']) {
                        $this->eventos->guardarPago((int) $recibo['id'], [
                            'payment_status' => $estado,
                            'transaction_id' => (string) ($datos['transaction_id'] ?? ''),
                            'pagado_en' => $estado === 'PAID' ? date('Y-m-d H:i:s') : null,
                        ]);
                        $recibo['payment_status'] = $estado;
                        $recibo['transaction_id'] = (string) ($datos['transaction_id'] ?? '');
                    }
                }
            } catch (\Throwable) {
                // Si falla la consulta, dejar el estado guardado.
            }
        }

        return view('inscripcion/recibo', [
            'recibo' => $recibo,
            'evento' => $recibo['evento'],
            'slug' => $slug,
            'bold_configurado' => (new \App\Persistencia\BoldClient())->configurado(),
        ]);
    }

    public function pagar(string $slug, $id = null)
    {
        $programa = $this->programa($slug);
        if ($programa === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        if ($id === null || (int) $id < 1) {
            return redirect()->to('/e/' . $slug);
        }

        $inscripcion = $this->eventos->inscripcionPorId((int) $id);
        if (! is_array($inscripcion) || (int) $inscripcion['evento_id'] !== (int) $this->eventos->id($slug)) {
            return redirect()->to('/e/' . $slug);
        }

        $bold = new \App\Persistencia\BoldClient();
        if (! $bold->configurado()) {
            return view('inscripcion/pagar-placeholder', [
                'slug' => $slug,
                'programa' => $programa,
                'id' => $id,
            ]);
        }

        // Si ya hay un link activo que no está pagado, reutilizarlo
        $linkExistente = (string) ($inscripcion['payment_link'] ?? '');
        $estado = (string) ($inscripcion['payment_status'] ?? '');
        if ($linkExistente !== '' && in_array($estado, ['', 'ACTIVE', 'PROCESSING'], true)) {
            try {
                $datos = $bold->consultarLink($linkExistente);
                if (($datos['status'] ?? '') === 'PAID') {
                    $this->eventos->guardarPago((int) $id, [
                        'payment_status' => 'PAID',
                        'transaction_id' => (string) ($datos['transaction_id'] ?? ''),
                        'pagado_en' => date('Y-m-d H:i:s'),
                    ]);

                    return redirect()->to('/e/' . $slug . '/recibo/' . $id . '?pagado=1');
                }
                if (! empty($datos['url'])) {
                    return redirect()->to((string) $datos['url']);
                }
                return redirect()->to($bold->urlDelLink($linkExistente));
            } catch (\Throwable) {
                // Si falla la consulta, crear un link nuevo
            }
        }

        try {
            $link = $bold->crearLink([
                'reference' => 'INS-' . $id,
                'amount' => (int) $inscripcion['total'],
                'description' => mb_substr($programa->nombre, 0, 100),
                'callback_url' => site_url('e/' . $slug . '/recibo/' . $id . '?pagado=1'),
                'payer_email' => (string) ($inscripcion['correo'] ?? ''),
                'expiration_date' => (int) (time() + 3600) * 1000000000,
            ]);
        } catch (\Throwable $e) {
            return view('inscripcion/pagar-placeholder', [
                'slug' => $slug,
                'programa' => $programa,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->eventos->guardarPago((int) $id, [
            'payment_link' => $link['payment_link'],
            'payment_status' => 'ACTIVE',
        ]);

        return redirect()->to($link['url']);
    }

    /**
     * @return array{nombre: ?string, error: ?string}
     */
    private function notificarInscripcion(string $slug, array $recibo, array $entrada, \App\Domain\ProgramaEvento $programa, int $idInscripcion): void
    {
        try {
            $correoCampo = $programa->claveConRol('correo') ?? 'correo';
            $correo = trim((string) ($entrada[$correoCampo] ?? ''));
            if ($correo === '' || ! filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                return;
            }

            $nombre = trim((string) ($programa->valorRol($entrada, 'nombre') ?: $recibo['nombre'] ?? ''));
            $total = (int) ($recibo['total'] ?? 0);
            $enlaceRecibo = site_url('e/' . $slug . '/recibo/' . $idInscripcion);

            $email = \App\Persistencia\CorreoConfigurado::instancia();
            $email->setTo($correo);
            $email->setSubject('Inscripción confirmada — ' . $programa->nombre);
            $email->setMessage(
                "Hola {$nombre},\n\n" .
                "Tu inscripción a \"{$programa->nombre}\" está confirmada.\n\n" .
                "Total: $ " . number_format($total, 0, ',', '.') . "\n" .
                "Ver tu recibo: {$enlaceRecibo}\n\n" .
                "— DWB Colombia"
            );
            if (! $email->send(false)) {
                log_message('error', 'No se pudo enviar la notificación de inscripción a ' . $correo);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Error enviando notificación de inscripción: ' . $e->getMessage());
        }
    }

    private function guardarComprobante(?\CodeIgniter\HTTP\Files\UploadedFile $archivo, string $documento): array
    {
        if ($archivo === null || $archivo->getError() === UPLOAD_ERR_NO_FILE) {
            return ['nombre' => null, 'error' => null];
        }
        if (! $archivo->isValid()) {
            return ['nombre' => null, 'error' => $this->mensajeCarga($archivo->getError())];
        }

        $extension = strtolower($archivo->getExtension() ?: $archivo->guessExtension() ?: '');
        if (! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            return ['nombre' => null, 'error' => 'El comprobante debe ser PDF, JPG o PNG.'];
        }

        $directorio = WRITEPATH . 'uploads/comprobantes';
        if (! is_dir($directorio) && ! mkdir($directorio, 0777, true) && ! is_dir($directorio)) {
            return ['nombre' => null, 'error' => 'No se pudo crear la carpeta de comprobantes.'];
        }

        $base = preg_replace('/[^A-Za-z0-9_-]/', '', $documento) ?: 'comprobante';
        $nombre = $base . '-' . date('YmdHis') . '.' . $extension;
        $destino = $directorio . DIRECTORY_SEPARATOR . $nombre;

        try {
            $archivo->move($directorio, $nombre, true);
        } catch (\Throwable) {
            if (! is_file($destino)) {
                return ['nombre' => null, 'error' => 'No se pudo guardar el comprobante.'];
            }
        }

        return ['nombre' => $nombre, 'error' => null];
    }

    private function nombreGuardado(string $nombre): ?string
    {
        if (preg_match('/^[A-Za-z0-9_-]+\.(pdf|jpg|jpeg|png)$/', $nombre) !== 1) {
            return null;
        }
        $ruta = WRITEPATH . 'uploads/comprobantes/' . $nombre;

        return is_file($ruta) ? $nombre : null;
    }

    private function mensajeCarga(int $codigo): string
    {
        return match ($codigo) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El comprobante pesa más de lo permitido. El máximo es 8 MB.',
            UPLOAD_ERR_PARTIAL => 'El comprobante llegó incompleto. Intenta subirlo otra vez.',
            default => 'No se pudo recibir el comprobante.',
        };
    }

    /**
     * @param array<string, mixed>  $entrada
     * @param array<string, string> $errores
     *
     * @return array<string, mixed>
     */
    private function datos(ProgramaEvento $programa, string $slug, ?\App\Domain\ResultadoInscripcion $resultado, array $entrada, array $errores = []): array
    {
        return [
            'programa' => $programa,
            'slug' => $slug,
            'entrada' => $entrada,
            'errores' => $errores !== [] ? $errores : ($resultado?->errores ?? []),
            'cotizacion' => $resultado?->cotizacion,
            'notas' => $resultado?->notas ?? [],
            'formaPago' => $resultado?->formaPago,
        ];
    }

    private function programa(string $slug): ProgramaEvento
    {
        $programa = $this->eventos->programa($slug);
        if ($programa === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $programa;
    }
}

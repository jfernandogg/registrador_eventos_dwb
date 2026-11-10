<?php

namespace App\Controllers;

use App\Domain\AsistenteInscripcion;
use App\Domain\ProgramaEvento;
use App\Domain\Tour365;
use App\Persistencia\RepositorioEventos;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class Inscripcion extends BaseController
{
    private AsistenteInscripcion $asistente;

    private RepositorioEventos $eventos;

    private string $slug = 'tour-365';

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        $this->helpers = ['form', 'url'];
        parent::initController($request, $response, $logger);
        $this->asistente = new AsistenteInscripcion();
        $this->eventos = new RepositorioEventos();
    }

    public function index()
    {
        return view('inscripcion/formulario', $this->datos(null, []));
    }

    public function cotizar()
    {
        $resultado = $this->asistente->procesar(
            Tour365::programa(),
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

    public function enviar()
    {
        $confirmar = $this->request->getPost('accion') === 'confirmar';
        $archivo = $this->request->getFile('comprobante');
        $guardado = $this->nombreGuardado((string) ($this->request->getPost('comprobante_guardado') ?? ''));
        $carga = $this->guardarComprobante($archivo, (string) ($this->request->getPost('documento') ?? 'comprobante'));
        if ($carga['nombre'] !== null) {
            $guardado = $carga['nombre'];
        }

        $resultado = $this->asistente->procesar(
            Tour365::programa(),
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
            $guardadoDb = $this->eventos->guardar($this->slug, $recibo, $entrada, $guardado);
            if ($guardadoDb['estado'] === 'duplicada') {
                $errores['documento'] = 'Ya hay una inscripción activa con este documento en este evento.';
            } elseif ($guardadoDb['estado'] !== 'guardada') {
                $errores['documento'] = 'No se pudo guardar la inscripción. Intenta de nuevo.';
            } else {
                return redirect()->to('/tour-365/recibo/' . $guardadoDb['id']);
            }
        }

        return view('inscripcion/formulario', [
            'programa' => Tour365::programa(),
            'entrada' => $entrada,
            'errores' => $errores,
            'cotizacion' => $resultado->cotizacion,
            'notas' => $resultado->notas,
            'formaPago' => $resultado->formaPago,
        ]);
    }

    public function recibo($id = null)
    {
        $recibo = $this->eventos->recibo($this->slug, (int) $id);
        if (! is_array($recibo)) {
            return redirect()->to('/tour-365');
        }

        return view('inscripcion/recibo', ['recibo' => $recibo, 'evento' => $recibo['evento']]);
    }

    /**
     * @return array{nombre: ?string, error: ?string}
     */
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
     * @param array<string, mixed> $entrada
     *
     * @return array<string, mixed>
     */
    private function datos(?\App\Domain\ResultadoInscripcion $resultado, array $entrada): array
    {
        return [
            'programa' => Tour365::programa(),
            'entrada' => $entrada,
            'errores' => $resultado?->errores ?? [],
            'cotizacion' => $resultado?->cotizacion,
            'notas' => $resultado?->notas ?? [],
            'formaPago' => $resultado?->formaPago,
        ];
    }
}

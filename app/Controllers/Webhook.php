<?php

declare(strict_types=1);

namespace App\Controllers;

use App\BaseController;
use App\Persistencia\BoldClient;
use App\Persistencia\RepositorioEventos;

/**
 * Recibe los webhooks de Bold (SALE_APPROVED, SALE_REJECTED, VOID_APPROVED,
 * VOID_REJECTED) y actualiza el estado de pago de la inscripción.
 *
 * Ruta pública: POST /webhook/bold
 * Seguridad: valida la firma x-bold-signature (HMAC-SHA256 del body en base64).
 */
final class Webhook extends BaseController
{
    public function bold(): \CodeIgniter\HTTP\ResponseInterface
    {
        $cuerpo = (string) $this->request->getBody();
        $firma = (string) $this->request->getHeaderLine('x-bold-signature');

        $bold = new BoldClient();
        if (! $bold->configurado()) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Bold no configurado']);
        }
        if (! $bold->validarFirma($cuerpo, $firma)) {
            log_message('warning', 'Webhook Bold: firma inválida');
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Firma inválida']);
        }

        $evento = json_decode($cuerpo, true);
        if (! is_array($evento)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'JSON inválido']);
        }

        $tipo = (string) ($evento['type'] ?? '');
        $datos = (array) ($evento['data'] ?? []);
        $referencia = (string) ($datos['metadata']['reference'] ?? '');
        $paymentId = (string) ($datos['payment_id'] ?? '');
        $link = (string) ($datos['payment_link'] ?? '');

        if ($referencia === '' && $link === '') {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Sin referencia']);
        }

        $repositorio = new RepositorioEventos();
        $inscripcion = $repositorio->buscarPorReferencia($referencia !== '' ? $referencia : $link);
        if (! is_array($inscripcion)) {
            // Bold puede enviar la notificación antes de que guardemos el link.
            // Responder 200 para que no reintente, y quedará como no asociada.
            log_message('info', 'Webhook Bold: inscripción no encontrada para ' . $referencia);
            return $this->response->setStatusCode(200)->setJSON(['ok' => true, 'asociada' => false]);
        }

        $estado = match ($tipo) {
            'SALE_APPROVED' => 'PAID',
            'SALE_REJECTED' => 'REJECTED',
            'VOID_APPROVED' => 'CANCELLED',
            'VOID_REJECTED' => 'CANCELLED',
            default => null,
        };
        if ($estado === null) {
            return $this->response->setStatusCode(200)->setJSON(['ok' => true]);
        }

        $repositorio->guardarPago((int) $inscripcion['id'], [
            'payment_status' => $estado,
            'transaction_id' => $paymentId,
            'payment_link' => $link !== '' ? $link : (string) ($inscripcion['payment_link'] ?? ''),
            'pagado_en' => $estado === 'PAID' ? date('Y-m-d H:i:s') : null,
        ]);

        log_message('info', 'Webhook Bold: inscripción ' . $inscripcion['id'] . ' → ' . $estado);

        return $this->response->setStatusCode(200)->setJSON(['ok' => true, 'asociada' => true]);
    }
}
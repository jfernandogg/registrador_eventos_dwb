<?php

declare(strict_types=1);

namespace App\Controllers;

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
        $repositorio = new RepositorioEventos();
        $repositorio->asegurarTablaWebhooks();

        $evento = json_decode($cuerpo, true);
        $datos = is_array($evento) ? (array) ($evento['data'] ?? []) : [];
        $tipo = is_array($evento) ? (string) ($evento['type'] ?? '') : '';
        $paymentId = (string) ($datos['payment_id'] ?? '');
        $link = (string) ($datos['payment_link'] ?? '');
        $referencia = (string) ($datos['metadata']['reference'] ?? '');
        $uuid = is_array($evento) ? (string) ($evento['uuid'] ?? '') : '';

        $firmaValida = $bold->configurado() && $bold->validarFirma($cuerpo, $firma);

        // Registra la notificación (no se pierde aunque falle el procesamiento).
        $idEntrada = $repositorio->guardarWebhook([
            'proveedor' => 'bold',
            'uuid' => $uuid,
            'tipo' => $tipo,
            'payment_id' => $paymentId,
            'payment_link' => $link,
            'referencia' => $referencia,
            'firma_valida' => (int) $firmaValida,
            'firma_recibida' => $firma,
            'payload_json' => $cuerpo,
        ]);

        $asociada = false;
        $nota = '';
        $estadoPago = null;
        $inscripcionId = null;

        if (! $firmaValida) {
            $nota = 'Firma inválida, notificación ignorada';
        } elseif (! is_array($evento)) {
            $nota = 'JSON inválido';
        } elseif ($referencia === '' && $link === '') {
            $nota = 'Sin referencia';
        } else {
            $inscripcion = null;

            // 1) Lo más fiable: el LNK (payment_link) coincide exactamente con la columna.
            if ($link !== '') {
                $inscripcion = $repositorio->buscarPorReferencia($link);
            }
            // 2) Fallback: referencia interna INS-{id}.
            if (! is_array($inscripcion) && $referencia !== '') {
                if (str_starts_with($referencia, 'INS-')) {
                    $idRef = (int) substr($referencia, 4);
                    if ($idRef > 0) {
                        $inscripcion = $repositorio->inscripcionPorId($idRef);
                    }
                } else {
                    $inscripcion = $repositorio->buscarPorReferencia($referencia);
                }
            }

            if (! is_array($inscripcion)) {
                $nota = 'No asociada: aún no existe una inscripción con ese link/referencia';
            } else {
                $inscripcionId = (int) $inscripcion['id'];
                $estado = match ($tipo) {
                    'SALE_APPROVED' => 'PAID',
                    'SALE_REJECTED' => 'REJECTED',
                    'VOID_APPROVED' => 'CANCELLED',
                    'VOID_REJECTED' => 'CANCELLED',
                    default => null,
                };

                if ($estado === null) {
                    $nota = 'Tipo de evento sin mapeo: ' . $tipo;
                } else {
                    $repositorio->guardarPago($inscripcionId, [
                        'payment_status' => $estado,
                        'transaction_id' => $paymentId,
                        'payment_link' => $link !== '' ? $link : (string) ($inscripcion['payment_link'] ?? ''),
                        'pagado_en' => $estado === 'PAID' ? date('Y-m-d H:i:s') : null,
                    ]);
                    $estadoPago = $estado;
                    $asociada = true;
                    $nota = 'Asociada y actualizada a ' . $estado;
                }
            }
        }

        $repositorio->completarWebhook($idEntrada, [
            'asociada' => (int) $asociada,
            'inscripcion_id' => $inscripcionId,
            'payment_status' => (string) ($estadoPago ?? ''),
            'nota' => $nota,
        ]);

        return $this->response->setStatusCode(200)->setJSON(['ok' => true, 'asociada' => $asociada]);
    }
}
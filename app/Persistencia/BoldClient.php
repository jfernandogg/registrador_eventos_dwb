<?php

declare(strict_types=1);

namespace App\Persistencia;

/**
 * Cliente de la API Link de pagos de Bold.
 *
 * Docs:
 *   POST https://integrations.api.bold.co/online/link/v1        (crear link)
 *   GET  https://integrations.api.bold.co/online/link/v1/{LNK}  (consultar)
 *   Webhook: header x-bold-signature = HMAC-SHA256(base64(body), llave_secreta)
 *
 * Las llaves se guardan en la tabla `configuracion` (página Integraciones).
 */
final class BoldClient
{
    private const URL_BASE = 'https://integrations.api.bold.co';

    private string $apiKey;

    private string $secretKey;

    private bool $sandbox;

    public function __construct()
    {
        $config = (new RepositorioConfiguracion())->todos();
        $this->apiKey = trim((string) ($config['bold_api_key'] ?? ''));
        $this->secretKey = trim((string) ($config['bold_secret_key'] ?? ''));
        $this->sandbox = trim((string) ($config['bold_ambiente'] ?? 'sandbox')) !== 'produccion';
    }

    public function configurado(): bool
    {
        return $this->apiKey !== '';
    }

    public function esSandbox(): bool
    {
        return $this->sandbox;
    }

    /**
     * Crea un link de pago con monto cerrado.
     *
     * @param array{
     *   reference: string,
     *   amount: int,
     *   description: string,
     *   callback_url: string,
     *   payer_email?: string,
     *   expiration_date?: int
     * } $datos
     * @return array{payment_link: string, url: string}
     */
    public function crearLink(array $datos): array
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('Falta la llave de identidad de Bold.');
        }
        if ($datos['amount'] < 1000) {
            throw new \RuntimeException('El monto mínimo de Bold es $1.000 COP.');
        }
        if (mb_strlen($datos['description']) < 2 || mb_strlen($datos['description']) > 100) {
            throw new \RuntimeException('La descripción debe tener entre 2 y 100 caracteres.');
        }

        $payload = [
            'amount_type' => 'CLOSE',
            'currency' => 'COP',
            'amount' => [
                'currency' => 'COP',
                'total_amount' => (int) $datos['amount'],
            ],
            'reference' => (string) $datos['reference'],
            'description' => (string) $datos['description'],
            'payment_methods' => ['CREDIT_CARD', 'PSE', 'BOTON_BANCOLOMBIA', 'NEQUI'],
        ];
        if (! empty($datos['payer_email'])) {
            $payload['payer_email'] = (string) $datos['payer_email'];
        }
        // Bold exige https:// en callback_url. En desarrollo (http) se omite.
        $callback = (string) ($datos['callback_url'] ?? '');
        if (str_starts_with($callback, 'https://')) {
            $payload['callback_url'] = $callback;
        }
        if (! empty($datos['expiration_date'])) {
            $payload['expiration_date'] = (int) $datos['expiration_date'];
        }

        $respuesta = $this->peticion('POST', '/online/link/v1', $payload);

        return [
            'payment_link' => (string) ($respuesta['payload']['payment_link'] ?? ''),
            'url' => (string) ($respuesta['payload']['url'] ?? ''),
        ];
    }

    /**
     * Consulta el estado de un link de pago.
     *
     * @return array<string, mixed>
     */
    public function consultarLink(string $paymentLink): array
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('Falta la llave de identidad de Bold.');
        }

        $respuesta = $this->peticion('GET', '/online/link/v1/' . rawurlencode($paymentLink));

        return (array) ($respuesta['payload'] ?? $respuesta);
    }

    /**
     * URL pública del link de pago para redirigir al usuario.
     */
    public function urlDelLink(string $paymentLink): string
    {
        return 'https://checkout.bold.co/payment/' . rawurlencode($paymentLink);
    }

    /**
     * Valida la firma del webhook: HMAC-SHA256 del body en base64.
     */
    public function validarFirma(string $body, string $firma): bool
    {
        if ($firma === '') {
            return false;
        }
        $encoded = base64_encode($body);

        // Bold firma con la llave secreta configurada en producción, PERO en
        // ambiente de pruebas la firma usa una clave vacía (documentación de
        // webhook: "$secretKey = '';"). En sandbox se acepta la llave guardada
        // o la vacía; en producción solo la llave guardada.
        $claves = [$this->secretKey];
        if ($this->sandbox) {
            $claves[] = '';
        }
        foreach ($claves as $clave) {
            if (hash_equals(hash_hmac('sha256', $encoded, $clave), $firma)) {
                return true;
            }
        }

        return false;
    }

    public function llaveSecreta(): string
    {
        return $this->secretKey;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    private function peticion(string $metodo, string $ruta, ?array $payload = null): array
    {
        $ch = curl_init(self::URL_BASE . $ruta);
        $cabeceras = [
            'Authorization: x-api-key ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => $cabeceras,
        ]);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        }

        $cuerpo = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($cuerpo === false || $cuerpo === '') {
            throw new \RuntimeException('Bold no respondió' . ($error !== '' ? ' (' . $error . ')' : '.'));
        }

        $decodificado = json_decode((string) $cuerpo, true);
        if (! is_array($decodificado)) {
            throw new \RuntimeException('Bold devolvió una respuesta inválida.');
        }
        if ($codigo >= 400) {
            $mensaje = $decodificado['errors'][0]['message'] ?? ('Error HTTP ' . $codigo);
            throw new \RuntimeException('Bold: ' . $mensaje);
        }

        return $decodificado;
    }
}
<?php

declare(strict_types=1);

namespace App\Persistencia;

use Config\Email as ConfigEmail;

/**
 * Devuelve un servicio de correo ya configurado con los parámetros
 * guardados en la página de integraciones. Si faltan, usa los defaults
 * de app/Config/Email.php (que a su vez viven en .env).
 *
 * Los envíos de invitaciones y notificaciones usan este constructor
 * en lugar de \Config\Services::email() directo.
 */
final class CorreoConfigurado
{
    /**
     * @return \CodeIgniter\Email\Email
     */
    public static function instancia(): \CodeIgniter\Email\Email
    {
        $config = new ConfigEmail();
        $guardado = (new RepositorioConfiguracion())->todos();

        $protocolo = trim((string) ($guardado['correo_protocolo'] ?? $config->protocol));
        if ($protocolo === '') {
            $protocolo = 'mail';
        }
        $config->protocol = $protocolo;

        // SMTP
        $config->SMTPHost = trim((string) ($guardado['correo_smtp_host'] ?? $config->SMTPHost));
        $config->SMTPUser = trim((string) ($guardado['correo_smtp_usuario'] ?? $config->SMTPUser));
        $config->SMTPPass = (string) ($guardado['correo_smtp_clave'] ?? $config->SMTPPass);
        $config->SMTPPort = (int) ($guardado['correo_smtp_puerto'] !== '' ? ($guardado['correo_smtp_puerto'] ?? $config->SMTPPort) : $config->SMTPPort);
        $config->SMTPCrypto = trim((string) ($guardado['correo_smtp_cripto'] ?? $config->SMTPCrypto));

        // Remitente
        $config->fromEmail = trim((string) ($guardado['correo_from_email'] ?? $config->fromEmail));
        $config->fromName = trim((string) ($guardado['correo_from_nombre'] ?? $config->fromName));

        // Autenticación: única para SMTP
        $config->SMTPAuth = $config->SMTPHost !== '' && $config->SMTPUser !== '';

        $email = \Config\Services::email($config);
        $email->setFrom(
            $config->fromEmail !== '' ? $config->fromEmail : 'no-reply@dwb.local',
            $config->fromName !== '' ? $config->fromName : 'DWB Colombia'
        );

        return $email;
    }
}
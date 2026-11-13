<?php

declare(strict_types=1);

namespace App\Persistencia;

/**
 * Guarda parámetros de configuración de integraciones (correo, etc.)
 * en una tabla clave-valor de MariaDB. No depende del marco (CodeIgniter).
 */
final class RepositorioConfiguracion
{
    private const CLAVES_PERMITIDAS = [
        'correo_protocolo',
        'correo_smtp_host',
        'correo_smtp_puerto',
        'correo_smtp_usuario',
        'correo_smtp_clave',
        'correo_smtp_cripto',
        'correo_from_email',
        'correo_from_nombre',
    ];

    /** @return array<string, string> */
    public function todos(): array
    {
        try {
            $filas = db_connect()->table('configuracion')->get()->getResultArray();
        } catch (\Throwable) {
            $this->crearTabla();

            return [];
        }
        $datos = [];
        foreach ($filas as $fila) {
            $datos[(string) $fila['clave']] = (string) $fila['valor'];
        }

        return $datos;
    }

    /**
     * @param array<string, string> $valores
     */
    public function guardar(array $valores): void
    {
        $db = db_connect();
        $this->crearTabla();
        foreach ($valores as $clave => $valor) {
            if (! in_array($clave, self::CLAVES_PERMITIDAS, true) || ! is_string($valor)) {
                continue;
            }
            if ($db->table('configuracion')->where('clave', $clave)->countAllResults() > 0) {
                $db->table('configuracion')->where('clave', $clave)->update(['valor' => $valor]);
            } else {
                $db->table('configuracion')->insert([
                    'clave' => $clave,
                    'valor' => $valor,
                ]);
            }
        }
    }

    private function crearTabla(): void
    {
        db_connect()->query(
            'CREATE TABLE IF NOT EXISTS configuracion (
                clave VARCHAR(60) NOT NULL,
                valor TEXT,
                PRIMARY KEY (clave)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /**
     * Devuelve las claves de correo permitidas por el formulario.
     *
     * @return list<string>
     */
    public static function clavesCorreo(): array
    {
        return [
            'correo_protocolo',
            'correo_smtp_host',
            'correo_smtp_puerto',
            'correo_smtp_usuario',
            'correo_smtp_clave',
            'correo_smtp_cripto',
            'correo_from_email',
            'correo_from_nombre',
        ];
    }
}
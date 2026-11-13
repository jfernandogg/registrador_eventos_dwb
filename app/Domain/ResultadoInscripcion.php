<?php

declare(strict_types=1);

namespace App\Domain;

final class ResultadoInscripcion
{
    /**
     * @param array<string, string> $errores
     * @param array<string, mixed>  $entrada
     * @param list<string>          $notas
     * @param list<array{descripcion: string, subtotal: int, tipo: string}> $lineasCongeladas
     */
    public function __construct(
        public readonly array $errores,
        public readonly array $entrada,
        public readonly ?Cotizacion $cotizacion,
        public readonly array $notas,
        public readonly ?FormaPago $formaPago,
        public readonly array $lineasCongeladas,
    ) {
    }

    public function tieneErrores(): bool
    {
        return $this->errores !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function congelar(string $momento): array
    {
        return [
            'nombre' => (string) ($this->entrada['nombre'] ?? ''),
            'documento' => (string) ($this->entrada['documento'] ?? ''),
            'forma' => $this->formaPago?->etiqueta ?? '',
            'forma_codigo' => $this->formaPago?->codigo ?? '',
            'cobra' => $this->formaPago?->cobraEnRegistro ?? false,
            'total' => $this->cotizacion?->total() ?? 0,
            'lineas' => $this->lineasCongeladas,
            'notas' => $this->notas,
            'momento' => $momento,
        ];
    }
}

<?php
namespace App\Core;

/**
 * Response - Padroniza toda saída JSON da API
 * 
 * Garante que todas as respostas sigam o mesmo formato,
 * com status HTTP correto e headers adequados.
 * 
 * FORMATO DE SUCESSO:
 * {
 *   "success": true,
 *   "data": { ... },
 *   "message": "opcional"
 * }
 * 
 * FORMATO DE ERRO:
 * {
 *   "success": false,
 *   "error": {
 *     "type": "AppointmentConflictException",
 *     "message": "Conflito de horário...",
 *     "errors": { ... }   // apenas em ValidationException
 *   }
 * }
 * 
 * USO:
 *   Response::json($data, 200)
 *   Response::created($data)
 *   Response::noContent()
 *   Response::error('Não encontrado', 404)
 *   Response::unauthorized()
 *   Response::fromException($e)
 */
Class Response {
    private int $statusCode;
    private array $headers = [];
    private mixed $payload;

    private function __construct(mixed $payload, int $statusCode = 200) {
        $this->payload = $payload;
        $this->statusCode = $statusCode;
    }

    // =========================================================
    // FACTORIES DE SUCESSO
    // =========================================================

    /**
     * Response genérica com dados
     */
    public static function json(mixed $data, int $statusCode = 200, ?string $message = null): static {
        $payload = ['success' => true, 'data' => $data];
 
        if ($message !== null) {
            $payload['message'] = $message;
        }
 
        return new static($payload, $statusCode);
    }


}
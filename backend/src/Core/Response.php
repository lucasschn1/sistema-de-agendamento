<?php
namespace App\Core;

use App\Exceptions\BusinessException;
use App\Exceptions\ValidationException;

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

    /**
     * 201 Created - recurso criado com sucesso
     * Retorna o ID ou objeto criado
     */
    public static function created(mixed $data, ?string $message = 'Criado com sucesso'): static {
        return static::json($data, 201, $message);
    }

    /**
     * 204 No Content -operação realizada, sem corpo de resposta
     * Usado em DELETE e algumas atualizações
     */
    public static function noContent(): static {
       return new static(null, 204);
    }

    // =========================================================
    // FACTORIES DE ERRO
    // =========================================================
    
    /**
     * Resposta de erro genérica
     */
    public static function error(string $message, int $statusCode = 400, ?string $type = null): static {
        $payload = [
            'sucess' => false,
            'error' => [
                'type' => $type ?? 'Error',
                'message' => $message,
            ]
        ];

        return new static($payload, $statusCode);
    }

    /**
     * 401 Unauthorized - token ausente ou inválido
     */
    public static function unauthorized(string $message = 'Token ausente ou inválido'): static {
        return static::error($message, 401, 'UnauthorizedException');
    }

    /**
     * 403 Forbidden - autenticado mas sem permissão
     */
    public static function forbidden(string $message = 'Acesso negado'): static {
        return static::error($message, 403, 'ForbiddenException');
    }

    /**
     * 404 Not Found
     */
    public static function notFound(string $message = 'Recurso não encontrado'): static {
        return static::error($message, 404, 'NotFoundException');
    }

    /**
     * 409 Conflict - duplicidade ou conflito de estado
     */
    public static function conflict(string $message): static  {
        return static::error($message, 409, 'ConflictException');
    }

    /**
     * 422 Unprocessable Entity - erros de validação
     * Retorna o mapa de erros por campo
     */
    public static function validationError(array $errors): static {
        $payload = [
            'sucess' => false,
            'error' => [
                'type' => 'ValidationException',
                'message' => 'Erro de validação',
                'errors' => $errors,
            ]
        ];

        return new static($payload, 422);
    }

    /**
     * 500 Internal Server error
     * Em produção não expoe detalhes do erro
     */
    public static function serverError(string $message = 'Erro interno do servidor'): static {
        return static::error($message, 500, 'ServerError');
    }

    /**
     * Controí a resposta correta a partir de uma exceção de negócio
     * Centraliza o mapeamento Exception -> HTTP status code
     * 
     * Usando no index.php como fallback global de erros
     */
    public static function fromException(\Throwable $e): static {
        // Exceções de validação (422)
        if ($e instanceof ValidationException){
            return static::validationError($e->getErrors());
        }

        // Exceções de negócios com httpStatusCode definido
        if ($e instanceof BusinessException) {
            $payload = [
                'sucess' => false,
                'error' => $e->toArray(),
            ];

            return new static($payload, $e->getHttpStatusCode());
        }

        // em desenvolvimento, expoe a stack trace
        $isDebug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

        if ($isDebug) {
            return static::serverError($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }

        // Em produção, reposta genérica
        return static::serverError();
    }

    // =========================================================
    // ENVIO DA RESPOSTA
    // =========================================================
    
    /**
     * Envia a reposta HTTP com header e body
     * Deve ser chamado uma unica vez, ao final do ciclo de requisição
     */
    public function send(): never {
        // Status HTTP
        http_response_code($this->statusCode);

        // Headers padrão
        header('Content-Type: applicatuion/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');

        // Headers CORS — ajuste o domínio em produção
        $allowedOrigin = $_ENV['FRONTEND_URL'] ?? '*';
        header("Access-Control-Allow-Origin: {$allowedOrigin}");
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
 
        // Headers customizados adicionados via withHeader()
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
 
        // Sem body em 204
        if ($this->statusCode === 204 || $this->payload === null) {
            exit;
        }
 
        echo json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
 
    }

    /**
     * Adiciona um header customizado à resposta
     * 
     * Uso encadeado: Response::created($data)->withHeader('X-Resource-Id', $id)->send()
     */
    public function withHeader(string $name, string $value): static {
        $this->headers[$name] = $value;
        return $this;
    }
 
    /**
     * Responde a preflight OPTIONS do CORS e encerra
     * Chamado no index.php antes do roteamento
     */
    public static function handlePreflight(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        $allowedOrigin = $_ENV['FRONTEND_URL'] ?? '*';
        header("Access-Control-Allow-Origin: {$allowedOrigin}");
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400');
        exit;
    }
}
}
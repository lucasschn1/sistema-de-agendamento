<?php
namespace App\Core;

/**
 * Request - Encapsula a requisição HTTP recebida
 * 
 * Centraliza o acesso a $_GET, $_POST, php://input, headers e
 * parâmetros de rota, evitando que os Controllers acessem superglobais diretamente.
 * 
 * USO:
 *   $request = Request::capture();
 *   $request->body()                   // JSON do corpo da requisição
 *   $request->param('id')              // parâmetro de rota (/users/:id)
 *   $request->query('status')          // $_GET['status']
 *   $request->header('Authorization')  // header HTTP
 *   $request->method()                 // GET, POST, PUT, DELETE, PATCH
 *   $request->path()                   // /api/appointments
 *   $request->bearerToken()            // extrai o JWT do header Authorization
 */
Class Request {
    private string $method;
    private string $path;
    private array $body;
    private array $query;
    private array $headers;
    private array $routeParams = []; // preenchido pelo Router após o match

    private function __construct(
        string $method,
        string $path,
        array $body,
        array $query,
        array $headers
    ) {
        $this->method  = $method;
        $this->path    = $path;
        $this->body    = $body;
        $this->query   = $query;
        $this->headers = $headers;
    }

    /**
     * Constroí uma Request a partir da requisição HTTP atual
     */
    public static function capture(): static {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = self::parsePath();
        $body = self::parseBody($method);
        $query = $_GET ?? [];
        $headers = self::parseHeaders();

        return new static($method, $path, $body, $query, $headers);
    }

    // =========================================================
    // GETTERS PRINCIPAIS
    // =========================================================
    
    /**
     * Retorna o método HTTP (GET, POST, PUT, DELETE, PATCH)
     */
    public function method(): string {
        return $this->method;
    }

    /**
     * Retorna o path da requisição sem query string 
     * EX: /api/appoitments
     */
    public function path(): string {
        return $this->path;
    }

    /**
     * Retorna o body da requisição decodificado como array
     * 
     * Funciona para JSON (Content-Type: application/json) e form-data
     */
    public function body(): array {
        return $this->body;
    }

    /**
     * Retorna um campo especifico de body
     * @param mixed $default Valor padrão se campo não existir
     */
    public function input(string $key, mixed $default = null): mixed {
        return $this->body[$key] ?? $default;
    }

    /**
     * Retorna um parâmetro de rota (definido pelo Router)
     * Ex: /appointment/{id} -> $request->param('id')
     */
    public function param(string $key, mixed $default = null): mixed {
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * Retorna um parâmetro da query string ($_GET)
     * Ex: /appointments?status=confirmed -> $request->query('status')
     */
    public function query(string $key, mixed $default= null): mixed {
        return $this->query[$key] ?? $default;
    }

    /**
     * Retorna todos os parâmetros da rota
     */
    public function params(): array {
        return $this->routeParams;
    }

    /**
     * Retorna um header HTTP
     * Ex: $request->header('Content-Type')
     */
    public function header(string $key, mixed $default = null): mixed {
        // normaliza para uppercase com underscore para compatibilidade
        $normalized = strtoupper(str_replace('-', '_', $key));

        return $this->header[$normalized] ?? $default;
    }

    /**
     * Extrai o token JWT do header Authorization
     * Formato esperado: "Bearer eyJ..."
     * 
     * @return string|null Token sem prefixo "Bearer "
     */
    public function bearerToken(): ?string {
        $authorization = $this->header('Authorization');

        if (!$authorization || !str_starts_with($authorization, 'Bearer ')) {
            return null;
        }

        return substr($authorization, 7); // Remove "Bearer "
    }

    /**
     * Verifica se a requisição espera a resposta JSON
     */
    public function expectsJson(): bool {
        $accept = $this->header('Accept') ?? '';
        $contentType = $this->header('Content_Type') ?? '';

        return str_contains($accept, 'application/json')
            || str_contains($contentType, 'application/json');
    }

    /**
     * Verifica se é uma requisição AJAX / XHR
     */
    public function isAjax(): bool {
        return $this->header('X_Requested_With') === 'XMLHttpRequest';
    }

    /**
     * Verifica o método HTTP
     */
    public function isGet(): bool    { return $this->method === 'GET'; }
    public function isPost(): bool   { return $this->method === 'POST'; }
    public function isPut(): bool    { return $this->method === 'PUT'; }
    public function isPatch(): bool  { return $this->method === 'PATCH'; }
    public function isDelete(): bool { return $this->method === 'DELETE'; }

    // =========================================================
    // SETTER — usado pelo Router após o match da rota
    // =========================================================
    
    /**
     * Injeta os parâmetros de rota extraídos pelo Router
     * Ex: /appoitmets/42 -> ['id' => 42] 
     */
    public function setRouterParams(array $params): void {
        $this->routeParams = $params;
    }

    // =========================================================
    // HELPERS PRIVADOS
    // =========================================================
    
    private static function parsePath(): string {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
 
        // Remove a query string do path
        $path = parse_url($uri, PHP_URL_PATH);
 
        // Remove barra final (exceto para o root /)
        return $path !== '/' ? rtrim($path, '/') : '/';
    }
 
    private static function parseBody(string $method): array {
        // GET e DELETE normalmente não têm body
        if (in_array($method, ['GET', 'DELETE'])) {
            return [];
        }
 
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
 
        // JSON (Content-Type: application/json)
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
 
            // json_decode retorna null se o JSON for inválido
            return is_array($decoded) ? $decoded : [];
        }
 
        // Form data (Content-Type: application/x-www-form-urlencoded)
        return $_POST ?? [];
    }
 
    private static function parseHeaders(): array {
        $headers = [];
 
        // getallheaders() disponível no Apache e PHP-FPM
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                $key           = strtoupper(str_replace('-', '_', $name));
                $headers[$key] = $value;
            }
            return $headers;
        }
 
        // Fallback para servidores que não suportam getallheaders()
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name           = substr($key, 5); // Remove 'HTTP_'
                $headers[$name] = $value;
            }
        }
 
        // Content-Type e Content-Length não vêm com prefixo HTTP_
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['CONTENT_TYPE'] = $_SERVER['CONTENT_TYPE'];
        }
 
        return $headers;
    }
}
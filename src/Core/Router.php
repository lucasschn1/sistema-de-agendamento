<?php

namespace App\Core;

/**
 * Router - Mapeia URI + método HTTP para Controllers
 * 
 * Suporta:
 * - Parâmetros de rota: /appointments/{id}
 * - Middleware por rota ou grupo
 * - Prefixo de grupo: group('/api', fn() => ...)
 * - Métodos HTTP: GET, POST, PUT, PATCH, DELETE
 * 
 * USO em routes.php:
 *   $router->get('/appointments',         [AppointmentController::class, 'index']);
 *   $router->post('/appointments',        [AppointmentController::class, 'store']);
 *   $router->get('/appointments/{id}',    [AppointmentController::class, 'show']);
 *   $router->put('/appointments/{id}',    [AppointmentController::class, 'update']);
 *   $router->delete('/appointments/{id}', [AppointmentController::class, 'destroy']);
 * 
 *   // Com middleware:
 *   $router->post('/auth/login', [AuthController::class, 'login']);
 *   $router->group('/api', ['AuthMiddleware'], function($router) {
 *       $router->get('/users', [UserController::class, 'index']);
 *   });
 */
class Router {
    private array  $routes      = [];
    private array  $middlewares = []; // middleware globais (aplicados em todas as rotas)
    private string $prefix      = ''; // prefixo atual de grupo


    // =========================================================
    // REGISTRO DE ROTAS
    // =========================================================

    public function get(string $path, array $handler, array $middlewares = []): void {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, array $handler, array $middlewares = []): void {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, array $handler, array $middlewares = []): void {
        $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    public function patch(string $path, array $handler, array $middlewares = []): void {
        $this->addRoute('PATCH', $path, $handler, $middlewares);
    }

    public function delete(string $path, array $handler, array $middlewares = []): void {
        $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    /**
     * Agrupa rotas com prefixo e middleware compartilhados
     * 
     * @param string   $prefix      Prefixo do grupo ex: '/api'
     * @param array    $middlewares Middlewares aplicados a todas as rotas do grupo
     * @param callable $callback    Função que registra as rotas do grupo
     */
    public function group(string $prefix, array $middlewares, callable $callback): void {
        $previousPrefix      = $this->prefix;
        $previousMiddlewares = $this->middlewares;

        $this->prefix      = $previousPrefix . $prefix;
        $this->middlewares = array_merge($previousMiddlewares, $middlewares);

        $callback($this);

        // Restaura o estado anterior (suporte a grupos aninhados)
        $this->prefix      = $previousPrefix;
        $this->middlewares = $previousMiddlewares;
    }

    /**
     * Registra uma rota internamente
     */
    private function addRoute(string $method, string $path, array $handler, array $middlewares): void {
        $fullPath = $this->prefix . $path;

        $this->routes[] = [
            'method'      => $method,
            'path'        => $fullPath,
            'pattern'     => $this->buildPattern($fullPath), // regex compilada
            'handler'     => $handler,
            'middlewares' => array_merge($this->middlewares, $middlewares),
        ];
    }


    // =========================================================
    // DISPATCH — processa a requisição atual
    // =========================================================

    /**
     * Encontra a rota correspondente e executa o Controller
     * 
     * @param Request  $request   Requisição HTTP atual
     * @param array    $container Mapa de dependências do dependencies.php
     */
    public function dispatch(Request $request, array $container): void {
        $method = $request->method();
        $path   = $request->path();

        foreach ($this->routes as $route) {
            // Verifica método HTTP
            if ($route['method'] !== $method) {
                continue;
            }

            // Tenta fazer match do path com o pattern da rota
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            // Extrai parâmetros de rota (ex: {id} → ['id' => '42'])
            $params = $this->extractParams($route['path'], $matches);
            $request->setRouteParams($params);

            // Executa middlewares da rota em ordem
            $this->runMiddlewares($route['middlewares'], $request, $container);

            // Resolve e executa o Controller
            $this->runHandler($route['handler'], $request, $container);
            return;
        }

        // Nenhuma rota encontrada
        // Verifica se o path existe com método diferente (405 vs 404)
        if ($this->pathExistsWithDifferentMethod($path, $method)) {
            Response::error('Método não permitido', 405, 'MethodNotAllowed')->send();
        }

        Response::notFound("Rota não encontrada: {$method} {$path}")->send();
    }


    // =========================================================
    // EXECUÇÃO DO HANDLER E MIDDLEWARES
    // =========================================================

    /**
     * Executa os middlewares em ordem
     * Suporta sintaxe com parâmetro: 'RoleMiddleware:admin,professional'
     */
    private function runMiddlewares(array $middlewares, Request $request, array $container): void {
        foreach ($middlewares as $middlewareDefinition) {
            // Separa a classe do parâmetro de role: 'RoleMiddleware:admin' → ['RoleMiddleware', 'admin']
            [$middlewareClass, $params] = $this->parseMiddlewareDefinition($middlewareDefinition);

            // Resolve do container ou instancia diretamente
            $middleware = $container[$middlewareClass] ?? new $middlewareClass();

            // Injeta roles se for o RoleMiddleware
            if ($params && method_exists($middleware, 'setRoles')) {
                $roles = array_map('trim', explode(',', $params));
                $middleware->setRoles($roles);
            }

            $middleware->handle($request, $container);
        }
    }

    /**
     * Separa a classe do parâmetro em definições de middleware
     * Ex: 'App\Middleware\RoleMiddleware:admin' → ['App\Middleware\RoleMiddleware', 'admin']
     * Ex: 'App\Middleware\AuthMiddleware'        → ['App\Middleware\AuthMiddleware', null]
     */
    private function parseMiddlewareDefinition(string $definition): array {
        if (!str_contains($definition, ':')) {
            return [$definition, null];
        }

        [$class, $params] = explode(':', $definition, 2);
        return [$class, $params];
    }

    /**
     * Resolve o Controller do container e executa o método
     */
    private function runHandler(array $handler, Request $request, array $container): void {
        [$controllerClass, $method] = $handler;

        // Resolve Controller do container de dependências
        $controller = $container[$controllerClass] ?? null;

        if (!$controller) {
            // Tenta instanciar diretamente se não estiver no container
            if (class_exists($controllerClass)) {
                $controller = new $controllerClass();
            } else {
                Response::serverError("Controller '{$controllerClass}' não encontrado")->send();
            }
        }

        if (!method_exists($controller, $method)) {
            Response::serverError("Método '{$method}' não existe em '{$controllerClass}'")->send();
        }

        // Executa o método do Controller
        $response = $controller->$method($request);

        // Controllers podem retornar um Response ou enviar diretamente
        if ($response instanceof Response) {
            $response->send();
        }
    }


    // =========================================================
    // HELPERS DE PATTERN MATCHING
    // =========================================================

    /**
     * Converte o path com placeholders em uma regex
     * Ex: /appointments/{id} → #^/appointments/(?P<id>[^/]+)$#
     */
    private function buildPattern(string $path): string
    {
        // Escapa a barra e outros caracteres especiais de regex
        $escaped = preg_quote($path, '#');

        // Substitui os placeholders escapados por grupos nomeados
        // preg_quote transforma {id} em \{id\}
        $pattern = preg_replace('#\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\}#', '(?P<$1>[^/]+)', $escaped);

        return "#^{$pattern}$#";
    }

    /**
     * Extrai parâmetros nomeados do resultado do preg_match
     * Filtra apenas as chaves string (grupos nomeados), ignorando os índices numéricos
     */
    private function extractParams(string $routePath, array $matches): array
    {
        return array_filter(
            $matches,
            fn($key) => is_string($key),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Verifica se o path existe com outro método HTTP (para 405 vs 404)
     */
    private function pathExistsWithDifferentMethod(string $path, string $currentMethod): bool
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $currentMethod
                && preg_match($route['pattern'], $path)
            ) {
                return true;
            }
        }
        return false;
    }
}
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
Class Router {
    private array $routes = [];
    private array $middlewares = []; // middlewares globais (aplicado em todas as rotas)
    private string $prefix = ''; // prefixo atual de grupo

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
     * Encontra a rota correspondente e executa Controller
     * 
     * @param Request $request Requisição HTTP atual
     * @param array $container Mapa de dependências do dependencies.php
     */
    public function dispatch(Request $request, array $container): void {
        $method = $request->method();
        $path = $request->path();

        foreach($this->routes as $route) {
            // verifica método HTTP
            if ($route['method'] !== $method) {
                continue;
            }

            // tenta fazer match do path com pattern da rota
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            // extrai o parametro da rota (ex: {id} -> ['id' => '24])
            $params = $this->extractParams($route['path'], $matches);
            $request->setRouteParams($params);

            // executa middlewares da rota em ordem
            $this->runMiddlewares($route['middlewares'], $request, $container);

            // resolve e executa o Controller
            $this->runHandles($route['handler'], $request, $container);
            return;
        }

        // nenuma rota encontrada
        // verifica se o path existe 
        if ($this->pathExistWithDifferentMethod($path, $method)) {
            Response::error('Método não permitido', 405, 'MethodNotAllowed')->send();
        }

        Response::notFound("Rota não encontrada: {$method} {$path}")->send();
    }
    
}
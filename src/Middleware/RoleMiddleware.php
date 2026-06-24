<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\UnauthorizedException;

/**
 * RoleMiddleware - Restringe acesso por role do usuário autenticado
 * 
 * IMPORTANTE: Deve ser usado APÓS o AuthMiddleware, pois depende
 * do usuário já estar injetado no Request via $request->user().
 * 
 * USO no routes.php:
 *   // Restringe ao role 'admin'
 *   $router->group('', [RoleMiddleware::class . ':admin'], function($router) {
 *       $router->get('/users', [UserController::class, 'index']);
 *   });
 * 
 *   // Restringe a 'admin' ou 'professional'
 *   $router->group('', [RoleMiddleware::class . ':admin,professional'], function($router) {
 *       $router->get('/appointments', [AppointmentController::class, 'index']);
 *   });
 * 
 * O Router extrai o role após os dois pontos e injeta no middleware via setRole()
 */
class RoleMiddleware
{
    /**
     * Roles permitidos para a rota atual
     * Preenchido pelo Router a partir da sintaxe RoleMiddleware::class . ':admin'
     */
    private array $allowedRoles = [];

    /**
     * Injeta os roles permitidos (chamado pelo Router antes do handle)
     */
    public function setRoles(array $roles): void
    {
        $this->allowedRoles = $roles;
    }

    /**
     * Executa a verificação de role
     * 
     * @param Request $request
     * @param array   $container
     */
    public function handle(Request $request, array $container): void
    {
        // Usuário deve ter sido injetado pelo AuthMiddleware
        $user = $request->user();

        if (!$user) {
            // AuthMiddleware não foi executado antes — erro de configuração
            Response::unauthorized('Usuário não autenticado')->send();
        }

        // Se nenhum role foi especificado, permite qualquer usuário autenticado
        if (empty($this->allowedRoles)) {
            return;
        }

        // Verifica se o role do usuário está na lista de permitidos
        if (!in_array($user->getRole(), $this->allowedRoles, true)) {
            Response::forbidden(
                "Acesso negado: sua role '{$user->getRole()}' não tem permissão para este recurso"
            )->send();
        }
    }
}
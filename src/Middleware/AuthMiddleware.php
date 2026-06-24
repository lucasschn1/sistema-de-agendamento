<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Service\AuthService;
use App\Exceptions\UnauthorizedException;

/**
 * AuthMiddleware - Valida o JWT em rotas protegidas
 * 
 * Executado antes de qualquer Controller em rotas do grupo /api.
 * Se o token for válido, injeta o usuário autenticado no Request
 * para que os Controllers possam acessá-lo via $request->user().
 * 
 * FLUXO:
 *   1. Extrai o Bearer token do header Authorization
 *   2. Valida o token via AuthService
 *   3. Injeta o User no Request
 *   4. Passa para o próximo middleware ou Controller
 * 
 * Se falhar em qualquer etapa → 401 Unauthorized e encerra
 */
class AuthMiddleware {
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Executa a validação do token
     * 
     * @param Request $request   Requisição atual
     * @param array   $container Container de dependências
     */
    public function handle(Request $request, array $container): void {
        // Extrai o token do header Authorization: Bearer <token>
        $token = $request->bearerToken();

        if (!$token) {
            Response::unauthorized('Token de autenticação não fornecido')->send();
        }

        try {
            // Valida o token e retorna o User autenticado
            $user = $this->authService->validateToken($token);

            // Injeta o usuário autenticado no Request
            // Controllers acessam via $request->user()
            $request->setUser($user);

        } catch (UnauthorizedException $e) {
            Response::unauthorized($e->getMessage())->send();

        } catch (\Throwable $e) {
            // Qualquer erro inesperado na validação do token → 401
            Response::unauthorized('Falha na autenticação')->send();
        }
    }
}
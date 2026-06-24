<?php
namespace App\Controllers;
 
use App\Core\Request;
use App\Core\Response;
use App\Services\UserService;
use App\Exceptions\ValidationException;
use App\Exceptions\UserNotFoundException;
use App\Exceptions\DuplicateUserException;
use App\Exceptions\InactiveUserException;
use App\Exceptions\UserHasFutureAppointmentsException;
use App\Exceptions\UnauthorizedException;
 
/**
 * UserController - Gerencia usuários da clínica
 * 
 * Rotas públicas: nenhuma
 * Rotas autenticadas (qualquer role):
 *   GET   /api/me               → me()
 *   PUT   /api/me               → updateMe()
 *   PATCH /api/me/password      → updatePassword()
 * 
 * Rotas exclusivas admin:
 *   GET   /api/users             → index()
 *   GET   /api/users/{id}        → show()
 *   POST  /api/users/patient     → storePatient()
 *   POST  /api/users/professional→ storeProfessional()
 *   PUT   /api/users/{id}        → update()
 *   PATCH /api/users/{id}/deactivate → deactivate()
 *   PATCH /api/users/{id}/restore    → restore()
 *   GET   /api/users/search      → search()
 *   GET   /api/users/stats       → stats()
 */
Class UserController {
    private UserService $userService;

    public function __construct(UserService $userService) {
        $this->userService = $userService;
    }

    // =========================================================
    // PERFIL DO USUÁRIO LOGADO
    // =========================================================

    /**
     * GET /api/me
     * Retorna os dados do usuário autenticado
     */
    public function me(Request $request): Response {
        $user = $request->user();
        return Response::json($user->toPublicArray());
    }

    
}
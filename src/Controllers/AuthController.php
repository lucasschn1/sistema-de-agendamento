<?php

namespace App\Controllers;
 
use App\Core\Request;
use App\Core\Response;
use App\Service\AuthService;
use App\Exceptions\ValidationException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\InactiveUserException;

/**
 * AuthController - Gerencia autenticação da API
 * 
 * Rotas:
 *  POST /auth/login -> login()
 *  POST /auth/refresh -> refresh()
 */
Class AuthController {
    private AuthService $authService;

    public function __construct(AuthService $authService) {
        $this->authService = $authService;
    }

    // =========================================================
    // POST /auth/login
    // =========================================================

    /**
     * Autentica o usuário e retorna os tokens JWT
     * 
     * Body esperado:
     * {
     *   "email": "carlos@clinica.com",
     *   "password": "senha123"
     * }
     * 
     * Resposta 200:
     * {
     *   "success": true,
     *   "data": {
     *     "access_token": "eyJ...",
     *     "refresh_token": "eyJ...",
     *     "token_type": "Bearer",
     *     "expires_in": 28800,
     *     "user": { "id": 1, "name": "...", "role": "professional" }
     *   }
     * }
     */
    public function login(Request $request): Response {
        try {
            $email = $request->input('email', '');
            $password = $request->input('password', '');

            $result = $this->authService->login($email, $password);

            return Response::json($result, 200, 'Login realizado com sucesso');

        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());

        } catch (UnauthorizedException $e) {
            return Response::unauthorized($e->getMessage());

        } catch(InactiveUserException $e) {
            return Response::error($e->getMessage(), 403, 'InactiveUserException');

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    // =========================================================
    // POST /auth/refresh
    // =========================================================

    /**
     * Renova o access token usando um refresh token válido
     * 
     * Body esperado:
     * {
     *   "refresh_token": "eyJ..."
     * }
     * 
     * Resposta 200:
     * {
     *   "success": true,
     *   "data": {
     *     "access_token": "eyJ...",
     *     "token_type": "Bearer",
     *     "expires_in": 28800
     *   }
     * }
     */
    public function refresh(Request $request): Response {
        try {
            $refreshToken = $request->input('refresh_token', '');
 
            if (empty($refreshToken)) {
                return Response::validationError([
                    'refresh_token' => 'Refresh token é obrigatório'
                ]);
            }
 
            $result = $this->authService->refresh($refreshToken);
 
            return Response::json($result, 200, 'Token renovado com sucesso');
 
        } catch (UnauthorizedException $e) {
            return Response::unauthorized($e->getMessage());
 
        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }
}
<?php
namespace App\Controllers;
 
use App\Core\Request;
use App\Core\Response;
use App\Services\UserService;
use App\Exceptions\ValidationException;
use App\Exceptions\user\UserNotFoundException;
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

    /**
     * PUT /api/me
     * Atualiza dados do própio perfil (nome, telefone, bio, etc)
     * 
     * Body esperado:
     * {
     *  "name": "string",
     *  "phone": "string",
     *  "bio": "srting",
     *  "specialty": "string"
     * }
     */
    public function updateMe(Request $request): Response {
        try {
            $userId = $request->user()->getId();

            // campos que o próprio usuário pode alterar
            // não permite alterar role, email ou CPF por aqui
            $allowedFields = ['name', 'phone', 'bio', 'specialty'];
            $data = array_intersect($request->body(), array_flip($allowedFields));

            $this->userService->updateUser($userId, $data);

            $updateUser = $this->userService->getUserById($userId);
            return Response::json($updateUser->toPublicArray(), 200, 'Perfil atualizado');

        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());

        } catch (UserNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * PATCH /api/me/password
     * Altera a própria senha
     * 
     * Body esperado: 
     * {
     *  "current_password": "senha_atual",
     *  "new_password": "nova_senha"
     * }
     */
    public function updatePassword(Request $request): Response {
        try {
            $userId = $request->user()->getId();
            $currentPassword = $request->input('current_password', '');
            $newPassword = $request->input('new_password', '');

            if (empty($currentPassword) || empty($newPassword)) {
                return Response::validationError([
                    'password' => 'Senha atual e nova são obrigatórias'
                ]);
            }

            $this->userService->updatePassword($userId, $currentPassword, $newPassword);

            return Response::json(null, 200, 'Senha alterada com sucesso');

        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage(), 400);

        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    // =========================================================
    // ADMIN — LISTAGEM E BUSCA
    // =========================================================
    
    /**
     * GET /api/users
     * Lista todos os usuários (com filtro opcional por role)
     * 
     * Query params:
     *  ?role=patient|professional|admin
     *  ?active=true|false
     */
    public function index(Request $request): Response {
        try {
            $role = $request->query('role');
            $activeOnly = $request->query('active', 'true') === 'true';

            $users = match($role) {
                'patient' => $this->userService->getAllPatients($activeOnly),
                'professional' => $this->userService->getAllProfessionals($activeOnly),

                default => array_merge(
                    $this->userService->getAllPatients($activeOnly),
                    $this->userService->getAllProfessionals($activeOnly)
                )
            };

            $data = array_map(fn($u) => $u->toPublicArray(), $users);
            return Response::json($data);

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * GET /api/users/{id}
     * Retorna dados de um usuário específico
     */
    public function show(Request $request) {
        try {
            $id = (int) $request->param('id');
            $user = $this->userService->getUserById($id);

            return Response::json($user->toPublicArray());

        } catch (UserNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * GET /api/users/search
     * Busca usuários por nome (busca parcial)
     * 
     * Query params:
     *   ?name=joão
     *   ?type=Psicólogo (filtra profissionais por tipo)
     */
    public function search(Request $request): Response {
        try {
            $name = $request->query('name', '');
            $type = $request->query('type');
 
            if (!empty($type)) {
                $users = $this->userService->getProfessionalByType($type);
            } else {
                $users = $this->userService->searchByName($name);
            }
 
            $data = array_map(fn($u) => $u->toPublicArray(), $users);
            return Response::json($data);
 
        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());
 
        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * GET /api/users/stats
     * Retorna contadores de usuários por tipo
     * 
     * Resposta: 
     * {
     *  "patients": 42,
     *  "professional": 5,
     *  "admins": 1
     * }
     */
    public function stats(Request $request): Response {
        try {
            return Response::json($this->userService->getUserStats());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    // =========================================================
    // ADMIN — CRIAÇÃO
    // =========================================================
    
    /**
     * POST /api/users/patient
     * Cria um novo paciente
     * 
     * Body esperado:
     * {
     *  "name": "João Silva",
     *  "email": "joao@email.com",
     *  "password": "senha123", 
     *  "cpf": "123.456.789-00", (opcional)
     *  "phone": 11-99999-000" (opcional)
     *  "birthdate": "1990-08-15" (opcional)
     * }
     */
    public function storePatient(Request $request): Response {
        try {
            $id = $this->userService->createPatient($request->body());

            $user = $this->userService->getUserById($id);
            return Response::created($user->toPublicArray());

        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());

        } catch (DuplicateUserException $e) {
            return Response::conflict($e->getMessage());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * POST /api/users/professional
     * Cria um novo profissional (psicólogo, psicopedagogo, etc.)
     * 
     * Body esperado:
     * {
     *   "name": "Dr. Carlos Silva",
     *   "email": "carlos@clinica.com",
     *   "password": "senha123",
     *   "professional_type": "Psicólogo",
     *   "council_id": "CRP 06/123456",  (opcional)
     *   "specialty": "TCC",             (opcional)
     *   "bio": "...",                   (opcional)
     *   "cpf": "...",                   (opcional)
     *   "phone": "..."                  (opcional)
     * }
     */
    public function storeProfessional(Request $request): Response {
        try {
            $id = $this->userService->createProfessional($request->body());

            $user = $this->userService->getUserById($id);
            return Response::created($user->toPublicArray());

        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());

        } catch (DuplicateUserException $e) {
            return Response::conflict($e->getMessage());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    // =========================================================
    // ADMIN — ATUALIZAÇÃO E DELEÇÃO
    // =========================================================

    /**
     * PUT /api/users/{id}
     * Atualiza dados de qualquer usuário (admin)
     * Permite alterar mais campos que o /me
     */
    public function update(Request $request): Response {
        try {
            $id = (int) $request->param('id');

            $this->userService->updateUser($id, $request->body());

            $user = $this->userService->getUserById($id);
            return Response::json($user->toPublicArray(), 200, 'Usuário atualizado com sucesso');

        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());

        } catch (UserNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (DuplicateUserException $e) {
            return Response::conflict($e->getMessage());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * PATCH /api/users/{id}/deactivate
     * Desativa usuário (soft-delete)
     * Bloqueio se houver agendamentos futuros ativos
     */
    public function deactivate(Request $request): Response {
        try {
            $id = (int) $request->param('id');

            // Impede que o admin desative a si mesmo
            if ($id === $request->user()->getId()) {
                Return Response::error('Você não pode desativar a sua própria conta', 400);
            }

            $this->userService->deactivateUser($id);

            return Response::json(null, 200, 'Usuário desativado com sucesso');

        } catch (UserNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (UserHasFutureAppointmentsException $e) {
            return Response::error($e->getMessage(), 400, 'UserHasFutureAppointmentsException');

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * PATCH /api/users/{id}/restore
     * Reativa usuário desativado
     */
    public function restore(Request $request): Response {
        try {
            $id = (int) $request->param('id');
 
            $this->userService->reactivateUser($id);
 
            return Response::json(null, 200, 'Usuário reativado com sucesso');
 
        } catch (UserNotFoundException $e) {
            return Response::notFound($e->getMessage());
 
        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }
}
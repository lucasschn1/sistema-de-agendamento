<?php
namespace App\Services;

use App\Repositories\UserRepository;
use App\Exceptions\user\DuplicateUserException;
use App\Exceptions\user\InvalidEmailException;
use App\Exceptions\user\WeakPasswordException;
use App\Exceptions\ValidationException;
use App\Models\User;
use DomainException;
use App\Exceptions\user\InactiveUserException;
use InvalidArgumentException;
use App\Exceptions\user\InvalidUserRoleException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\user\UserNotFoundException;
use App\Exceptions\user\UserHasFutureAppointmentsException;

/**
 * Camada de Serviço para gerenciamento de usuários
 * 
 * Responsabilidades:
 * - validação de regras de negócio antes de persistir os dados
 * - transformação e sanatização de dados
 * - tratamento de exceções 
 */

class UserService {
    private UserRepository $userRepo;

    public function __construct(UserRepository $userRepo) {
        $this->userRepo = $userRepo;
    }

     /**
     * =======================================================================
     * CRIAÇÃO DE USUÁRIOS
     * =======================================================================
     */

     /**
     * Cria um novo paciente
     * 
     * @param array $data [
     *   'name' => string (required),
     *   'email' => string (required),
     *   'password' => string (required, plain text),
     *   'cpf' => string (optional),
     *   'phone' => string (optional),
     *   'birthdate' => string|DateTime (optional)
     * ]
     * @throws ValidationException
     * @throws InvalidEmailException
     * @throws WeakPasswordException
     * @throws DuplicateUserException
     * @return int ID do usuário criado
     */
    public function createPatient(array $data): int {

        // validaçoes de entrada
        $this->validateRequiredFields($data, ['name', 'email','password']);
        $this->validateEmail($data['email']);
        $this->validatePassword($data['password']);

        // sanitiza dados
        $data = $this->sanitizeUserData($data);
        $data['role'] = 'patient';

        // Hasheia senha
        $data['password'] = password_hash($data['password'], PASSWORD_ARGON2ID);

        // cria User object
        $user = new User($data);

        try {
            return $this->userRepo->createPatient($user);

        } catch (DomainException $e) {
            if (str_contains($e->getMessage(), 'Email já cadastrado')) {
                throw new DuplicateUserException('e-mail');
            }

            if (str_contains($e->getMessage(), 'CPF já cadastrado')) {
                throw new DuplicateUserException('CPF');
            }
            throw $e;
        }
    }

    /**
     * Cria um novo profissional ('psicologo', 'psicopedagogo')
     * 
     * @param array $data [
     *   'name' => string (required),
     *   'email' => string (required),
     *   'password' => string (required, plain text),
     *   'cpf' => string (optional),
     *   'phone' => string (optional),
     *   'birthdate' => string|DateTime (optional),
     *   'professional_type' => string (required) ex: 'Psicólogo', 'Psicopedagogo',
     *   'council_id' => string (optional) ex: 'CRP 06/123456',
     *   'specialty' => string (optional),
     *   'bio' => string (optional)
     * ]
     * @throws ValidationException
     * @throws InvalidEmailException
     * @throws WeakPasswordException
     * @throws DuplicateUserException
     * @return int ID do usuário criado
     */
    public function createProfessional(array $data): int {
        // validação de entrada
        $this->validateRequiredFields($data, ['name', 'email', 'password', 'professional_type']);
        $this->validateEmail($data['email']);
        $this->validatePassword($data['password']);

        // validação específica de profissional
        if (empty(trim($data['professional_type']))) {
            throw new ValidationException(['professional_type' => 'Tipo de profissional é obrigatório']);
        }

        // sanitiza dados
        $data = $this->sanitizeUserData($data);
        $data['role'] = 'professional';

        // hasheia a senha
        $data['password'] = password_hash($data['password'], PASSWORD_ARGON2ID);

        // cria User object
        $user = new User($data);

        try {
            return $this->userRepo->createProfessional($user);
        } catch (DomainException $e) {
            if (str_contains($e->getMessage(), 'Email já cadastrado')) {
                throw new DuplicateUserException('e-mail');
            }

            if (str_contains($e->getMessage(), 'CPF já cadastrado')) {
                throw new DuplicateUserException('CPF');
            }

            if (str_contains($e->getMessage(), 'registro profissional')) {
                throw new DuplicateUserException('número de registro profissional (CRP/CRM)');
            }

            throw $e;
        }
    }

    /**
     * Cria um novo administrador
     * 
     * @param array $data
     * @throws ValidationException
     * @throws InvalidEmailException
     * @throws WeakPasswordException
     * @throws DuplicateUserException
     * @return int ID do usuário criado
     */
    public function createAdmin(array $data):int {
        // validações de entrada
        $this->validateRequiredFields($data, ['name', 'email', 'password']);
        $this->validateEmail($data['email']);
        $this->validatePassword($data['password']);

        // sanitiza dados
        $data = $this->sanitizeUserData($data);
        $data['role'] = 'admin';

        // hasheia senha
        $data['password'] = password_hash($data['password'], PASSWORD_ARGON2ID);

        // cria User obeject
        $user = new User($data);

        try {
            return $this->userRepo->createAdmin($user);
        } catch (DomainException $e) {
            if (str_contains($e->getMessage(), 'Email já cadastrado')) {
                throw new DuplicateUserException('e-mail');
            }

            throw $e;
        }
    }

    // =========================================================
    // ATUALIZAÇÃO DE USUÁRIOS
    // =========================================================

    /**
     * Atualiza dados do usuáario
     * 
     * @param int $userId
     * @param array $data - Dados a atualizar
     * @throws UserNotFoundException
     * @throws InvalidEmailException
     * @throws DuplicateUserException
     * @return bool
     */
    public function updateUser(int $userId, array $data): bool {
        // busca usuário existente
        $user = $this->userRepo->findById($userId);

        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        // valida o email fornecido
        if (isset($data['email'])) {
            $this->validateEmail($data['email']);
        }

        $data = $this->sanitizeUserData($data);

        // merge com dados existentes
        $updateData = array_merge($user->toArray(), $data);

        // remove a senha do array se vier (NÃO atualiza por aqui)
        unset($updateData['password']);

        // cria novo User object com dados atualizados
        $updateUser = new User($updateData);

        try {
            return $this->userRepo->update($updateUser);

        } catch(DomainException $e) {
            if (str_contains($e->getMessage(), 'Email já cadastrado')) {
            throw new DuplicateUserException('e-mail');
            }

            if (str_contains($e->getMessage(), 'CPF já registrado')) {
                throw new DuplicateUserException('CPF');
            }

            if (str_contains($e->getMessage(), 'registro profissional')) {
                throw new DuplicateUserException('número de registro profissional');
            }

            throw $e;
        }
    }

    /**
     * Atualiza -apenas- a senha do usuário
     * 
     * @param int $userId
     * @param string $currentPassword - Senha atual (para validação)
     * @param string $newPassword
     * @throws UserNotFoundException
     * @throws WeakPasswordException
     * @throws InvalidArgumentException  Se senha atual não confere
     * @return bool
     */
    public function updatePassword(int $userId, string $currentPassword, string $newPassword): bool {
        $user = $this->userRepo->findById($userId);

        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        // valida a senha
        if (!$user->validatePassword($currentPassword)) {
            throw new InvalidArgumentException('Senha atual incorreta');
        }

        // valida nova senha
        $this->validatePassword($newPassword);

        // atualiza
        return $this->userRepo->updatePassword($userId, $newPassword);
    }

    /**
     * Redefine a senha (sem verificar senha atual - uso administrativo)
     * 
     * @param int $userId
     * @param string $newPassword
     * @throws UserNotFoundException
     * @throws WeakPasswordException
     * @return bool
     */
    public function resetPassword(int $userId, string $newPassword): bool {
        // busca usuário no banco de dados
        $user = $this->userRepo->findById($userId);

        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        // valida a senha nova
        $this->validatePassword($newPassword);

        // atualiza
        return $this->userRepo->updatePassword($userId, $newPassword);
    }

    // =========================================================
    // DESATIVAÇÃO E DELEÇÃO
    // =========================================================

    /**
     * Desativa usuário (soft delete)
     * 
     * IMPORTANTE: usa a stored procedure 'sp_delete_user' que verifica
     * se há algum agendamento futuro antes de permitir a desativação
     * 
     * @param int $userId
     * @throws UserNotFoundException
     * @throws UserHasFutureAppointmentsException 
     * @return bool
     */
    public function deactivateUser(int $userId): bool {
        // verifica se usuário existe
        $user = $this->userRepo->findById($userId);

        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        try {
            // chama repository que usa sp_delete_user
            return $this->userRepo->delete($userId);
        } catch (DomainException $e) {
            if (str_contains($e->getMessage(), 'agendamentos futuros')) {
                throw new UserHasFutureAppointmentsException();
            }
            throw $e;
        }
    }

    /**
     * Restaura usuária desativado
     * 
     * @param int $userId
     * @throws UserNotFoundException
     * @return bool
     */
    public function reactivateUser(int $userId): bool {
        try {
            return $this->userRepo->restore($userId);

        } catch(DomainException $e) {
            throw new UserNotFoundException($userId);
        }
    }

    // =========================================================
    // CONSULTAS E BUSCAS
    // =========================================================

    /**
     * Busca usuário por ID
     * 
     * @param int $userId
     * @param bool $includeDeleted
     * @throws UserNotFoundException
     * @return User
     */
    public function getUserById(int $userId, bool $includeDeleted = false): User {
        $user = $this->userRepo->findById($userId);

        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        return $user;
    }

    /**
     * Busca usuário por email
     * 
     * @param string $email
     * @throws UserNotFoundException
     * @return User
     */
    public function getUserByEmail(string $email): User {
        $user = $this->userRepo->findByEmail($email);

        if (!$user) {
            throw new UserNotFoundException(0);
        }

        return $user;
    }

    /**
     * Lista todos os pacientes ativos
     * 
     * @param bool $activeOnly
     * @return User[]
     */
    public function getAllPatients(bool $activeOnly = true): array {
        return $this->userRepo->getAllPatientes($activeOnly);
    }

    /**
     * Lista todos profissionais ativos
     * 
     * @param bool $activeOnly
     * @return User[]
     */
    public function getAllProfessionals(bool $activeOnly = true): array {
        return $this->userRepo->getAllProfessionals($activeOnly);
    }

    /**
     * Busca profissionais por tipo
     * 
     * @param string $professionalType Ex: 'Psicólogo', 'Psicopedagogo'
     * @param bool $activeOnly
     * @return User[]
     */
    public function getProfessionalByType(string $professionalType, bool $activeOnly = true): array {
        return $this->userRepo->findProfessionalsByType($professionalType, $activeOnly);
    }

    /**
     * Busca usuários por nome (busca parcial)
     * 
     * @param string $name
     * @param bool $activeOnly
     * @return User[]
     */
    public function searchByName(string $name, bool $activeOnly = true): array {
        if (strlen($name) < 2) {
            throw new ValidationException(['name' => 'Nome deve ter pelo menos 2 caracteres']);
        }

        return $this->userRepo->searchByName($name, $activeOnly);
    }

    // =========================================================
    // AUTENTICAÇÃO
    // =========================================================

    /**
     * Autentica usuário por email e senha
     * 
     * @param string $email
     * @param string $password Senha tem texto puro
     * @throws InvalidEmailException
     * @throws InvalidArgumentException Se credenciais inválidas
     * @throws InactiveUserException  Se usuário está desativado
     * @return User
     */
    public function authenticate(string $email, string $password): User {
        // valida formato do email
        $this->validateEmail($email);

        try {
            $user = $this->userRepo->authenticate($email, $password);

            if (!$user) {
                throw new InvalidArgumentException('E-mail ou senha incorretos');
            }

            return $user;

        } catch (DomainException $e) {
            // repositório lança DomainException se usuário estiver desativado
            if (str_contains($e->getMessage(),'desativado')) {
                throw new InactiveUserException();
            }
            throw $e;
        }
    }

    // =========================================================
    // VALIDAÇÕES E REGRAS DE NEGÓCIO
    // =========================================================
 
    /**
     * Valida se um usuário pode ser usado em um agendamento
     * 
     * @param int $userId
     * @param string $expectedRole 'patient' ou 'professional'
     * @throws UserNotFoundException
     * @throws InactiveUserException
     * @throws InvalidUserRoleException
     * @return User
     */
    public function validateUserForAppointment(int $userId, string $expectedRole): User {
        $user = $this->userRepo->findById($userId);

        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        if (!$user->isActive()) {
            $type = $expectedRole === 'patient' ? 'Paciente' : 'Profissional';
            throw new InactiveUserException($type);
        }

        if ($user->getRole() !== $expectedRole) {
            throw new InvalidUserRoleException($expectedRole, $user->getRole());
        }

        return $user;
    }

    /**
     * Verifica se usuário tem  permissão de admin
     * 
     * @param User $user
     * @throws UnauthorizedException;
     * @return void
     */
    public function requireAdmin(User $user): void {
        if (!$user->isAdmin()) {
            throw new UnauthorizedException('executar ação administrativa');
        }
    }

    // =========================================================
    // MÉTODOS PRIVADOS - VALIDAÇÕES
    // =========================================================
     
    /**
     * Valida campos obrigaórios
     * 
     * @param array $data
     * @param array $requiredFields
     * @throws ValidationException
     * @return void
     */
    private function validateRequiredFields(array $data, array $requiredFields) {
        $missing = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                $missing[$field] = "Campo {$field} é obrigatório";
            }

            if (!empty($missing)) {
                throw new ValidationException($missing);
            }
        }
    }

    /**
     * Valida formato do e-mail
     * 
     * @param string $email
     * @throws InvalidEmailException
     * @return void
     */
    public function validateEmail(string $email): void {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException($email);
        }

        // validação mais segura: impede emails temporarios conhecidos
        $blockDomains = ['tempmail.com', 'throwaway.email', '10minutemail.com'];

        $domain = substr(strrchr($email, "@"), 1);

        if (in_array(strtolower($domain), $blockDomains)) {
            throw new InvalidEmailException($email . 'domínio temporário não permitido');
        }
    }

    /**
     * Valida força da senha
     * 
     * @param string $password
     * @throws WeakPasswordException
     * @return void
     */
    private function validatePassword(string $password): void {
        $minLength = 6;

        if (strlen($password) < $minLength) {
            throw new WeakPasswordException("Senha deve ter no mínimo {$minLength} caracteres");
        }

        // regras mais fortes
        // if (!preg_match('/[A-Z]/', $password)) {
        //     throw new WeakPasswordException("Senha deve conter pelo menos uma letra maiúscula");
        // }
        // if (!preg_match('/[0-9]/', $password)) {
        //     throw new WeakPasswordException("Senha deve conter pelo menos um número");
        // }
    }

    /**
     * Sanitiza array $data
     * 
     * @param array $data
     * @return array
     */
    private function sanitizeUserData(array $data): array {
        // trim em string
        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        if (isset($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }

        if (isset($data['cpf'])) {
            // remove formatação do CPF
            $data[ 'cpf'] = preg_replace('/[^0-9]/','', $data['cpf']);

            // reformata 12345678901 -> 123.456.789-01
            if (strlen($data['cpf']) === 11) {
                $data['cpf'] = substr($data['cpf'],0,3) . '.' . 
                               substr($data['cpf'],3,3) . '.' . 
                               substr($data['cpf'],6,3) . '-' . 
                               substr($data['cpf'],9,2);          
            }
        }

        if (isset($data['phone'])) {
            // remove formatação do telefone
            $data['phone'] = preg_replace('/[^0-9]/', '', $data['phone']);
        }

        if (isset($data['professional_type'])) {
            $data['professional_type'] = trim($data['professional_type']);
        }
 
        if (isset($data['council_id'])) {
            $data['council_id'] = strtoupper(trim($data['council_id']));
        }
 
        return $data;
    }

    // =========================================================
    // ESTATÍSTICAS E RELATÓRIOS
    // =========================================================
 
    /**
     * Retorna contadores de usuários por tipo
     * 
     * @return array ['patients' => int, 'professionals' => int, 'admins' => int]
     */
    public function getUserStats(): array
    {
        return [
            'patients' => $this->userRepo->countByRole('patient', true),
            'professionals' => $this->userRepo->countByRole('professional', true),
            'admins' => $this->userRepo->countByRole('admin', true),
        ];
    }
}


<?php
namespace App\Repositories;

use App\Models\User; 
use PDO;
use PDOException;
use InvalidArgumentException;
use DomainException;

class UserRepository {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /*
    ==============================================================================
    MÉTODOS DE BUSCA (READ)
    ===============================================================================
    */

    /** 
        *   busca usuario por id
        *   @param int $id
        *   @param bool $includeDeleted - se true, retorna o mesmo usuario soft-deleted
        *   @return User|null
     */

    public function findById(int $id, bool $includeDeleted = false): ?User {
        try {
            $sql = "SELECT * FROM users WHERE id = :id";

            if (!$includeDeleted) {
                $sql .= " AND deleted_at IS NULL";
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            return $data ? new User($data) : null;

        } catch (PDOException $e) {
            error_log("Error fetching user by id: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * busca usuario por email
     * @param string $email
     * @param bool $includeDeleted - se true, retorna o mesmo usuario soft-deleted
     */

    public function findByEmail(string $email, bool $includeDeleted = false): ?User {
        try {
            $sql = "SELECT * FROM users WHERE email = :email";

            if (!$includeDeleted) {
                $sql .= " AND deleted_at IS NULL";
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['email' => $email]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            return $data ? new User($data) : null;
        } catch (PDOException $e) {
            error_log("Erro ao buscar usuário por email: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * busca usuario por CPF
     * @param bool $includeDeleted - se true, retorna os usuarios soft-deleted
     * @return User|null
     */

    public function findByCpf(string $cpf, bool $includeDeleted = false): ?User {
        try {
            $sql = "SELECT * FROM users WHERE cpf = :cpf";

            if (!$includeDeleted) {
                $sql .= " AND deleted_at IS NULL";
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['cpf' => $cpf]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            return $data ? new User($data) : null;

        } catch (PDOException $e) {
            error_log("Erro ao buscar usuário por CPF: " . $e->getMessage());
            throw $e;
        }
    }

    /** 
     *  busca usuario por 'role' 
     * @param string $role 'admin' , 'patient', 'professional'
     * @param bool $activeOnly - se true, retorna os usuarios active=true
     * @return User[]
     */

    public function findByRole(string $role, bool $activeOnly = false): array {
        try {
            $sql = "SELECT * FROM users WHERE role = :role AND deleted_at IS NULL";

            if ($activeOnly) {
                $sql .= " AND active = 1";
            }

            $sql .= " ORDER BY name";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['role' => $role]);
            $results = $stmt->fetch(PDO::FETCH_ASSOC);

            return array_map(fn($data) => new User($data), $results); // Retorna um array de objetos User

        } catch (PDOException $e) {
            error_log("Erro ao buscar usuários por role: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     *  Lista os pacientes ativos 
     *  @return User[]
     */

    public function getAllPatientes(bool $activeOnly = true): array {
        return $this->findByRole('patient', $activeOnly);
    }

    /**
     *  Lista todos os profissionais - 'Piscólogo', 'Psicopedagogo'
     *  @return User[]
     */

    public function getAllProfessionals(bool $activeOnly = true): array {
        return $this->findByRole('professional', $activeOnly);
    }

    /**
     *  Busca profissionais por tipo
     *  @param string $professionalType 'Psicólogo', 'Psicopedagogo'
     *  @return User[]
     */

    public function findProfessionalsByType(string $professionalType, bool $activeOnly = true): array {
        try {
            $sql = "SELECT * FROM users WHERE role = 'professional'
                AND professional_type = :type AND deleted_at IS NULL";

            if ($activeOnly) {
                $sql .= " AND active = 1";
            }

            $sql .= " ORDER by name";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['type' => $professionalType]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn($data) => new User($data), $results); // Retorna um array de objetos User
        } catch (PDOException $e) {
            error_log("Erro ao buscar profissionais por tipo: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     *  Busca profissionais por 'council_id' (registro profissional)
     * @param string $councilId 
     * @return User|null
     */

    public function findByCouncilId(string $councilId, bool $includeDeleted = false): ?User {
        try {
            $sql = "SELECT * FROM users WHERE council_id = :councilId";

            if (!$includeDeleted) {
                $sql .= " AND deleted_at IS NULL";
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['councilId' => $councilId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            return $data ? new User($data) : null;

        } catch (PDOException $e) {
            error_log("Erro ao buscar profissional por council_id: " . $e->getMessage());
            throw $e;
        }
    }

    /*
    ==============================================================================
    MÉTODOS DE CRIAÇÃO (CREATE)
    ===============================================================================
    */

    /**
     *  Cria um novo paciente
     *  @throws InvalidArgumentException se os dados forem inválidos
     *  @throws DomainException se ocorrer um erro de domínio (ex: email já existe)
     */

    public function createPatient(User $user): int {
        if ($user->getRole() !== 'patient') {
            throw new InvalidArgumentException("O usuário deve ter a role 'patient' para ser criado como paciente.");
        }

        return $this->insert($user);
    }

    /**
     *  Cria um novo profissional (psicologo, psicopedagogo)
     *  @throws InvalidArgumentException se os dados forem inválidos
     *  @throws DomainException se ocorrer um erro de domínio (ex: email já existe)
     */

    public function createProfessional(User $user): int {
        if ($user->getRole() !== 'professional') {
            throw new InvalidArgumentException("O usuário deve ter a role 'professional' para ser criado como profissional.");
        }

        // council ID é opcional (psicopedagogos podem não ter), mas se fornecido, deve ser único
        if ($user->getCouncilId()) {
            $this->checkCouncilIdUnique($user->getCouncilId(), null);
        }

        return $this->insert($user);
    }

    /**
     *  Cria um novo admin
     *  @throws InvalidArgumentException se os dados forem inválidos
     *  @throws DomainException se ocorrer um erro de domínio (ex: email já existe)o usuário criado
     */

    public function createAdmin(User $user): int {
        if ($user->getRole() !== 'admin') {
            throw new InvalidArgumentException("O usuário deve ter a role 'admin' para ser criado como admin.");
        }

        return $this->insert($user);
    }

     /** 
      *  Insere um novo usuário no banco de dados (privado, usado pelos métodos públicos de criação)
      *  @param User $user 
      */

     private function insert(User $user): int {

        // verifica duplicidade do email
        $this->checkEmailUnique($user->getEmail(), null);

        if ($user->getCpf()) {
            $this->checkCpfUnique($user->getCpf(), null);
        }

        try {
            $sql = "INSERT  INTO users (
                    name, email, password, cpf, phone, birthdate, role,
                    professional_type, council_id, specialty, bio, active
                    ) VALUES (
                           :name, :email, :password, :cpf, :phone, :birthdate, :role,
                           :professional_type, :council_id, :specialty, :bio, :active
                           )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'name'              => $user->getName(),
                'email'             => $user->getEmail(),
                'password'          => $user->getPasswordHash(),
                'cpf'               => $user->getCpf(),
                'phone'             => $user->getPhone(),
                'birthdate'         => $user->getBirthdate()?->format('Y-m-d'),
                'role'              => $user->getRole(),
                'professional_type' => $user->getProfessionalType(),
                'council_id'        => $user->getCouncilId(),
                'specialty'         => $user->getSpecialty(),
                'bio'               => $user->getBio(),
                'active'            => $user->isActive() ? 1 : 0
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erro ao inserir usuário: " . $e->getMessage());
            throw $e;
        }
    }

    /*
    ==============================================================================
    MÉTODOS DE ATUALIZAÇÃO (UPDATE)
    ===============================================================================
    */

    /**
     *  Atualiza 0s dados do usuario (exceto senha, que tem método específico)
     *  @throws InvalidArgumentException se o usuario não existir ou for soft-deleted
     *  @throws DomainException se ocorrer um erro de domínio (ex: email já existe)
     */

    public function update(User $user): bool {

        // Verifica se o usuário existe e não está soft-deleted
        if (!$user->getId() || $this->findById($user->getId())) {
            throw new InvalidArgumentException(
                "Usuário com ID " . $user->getId() . " não encontrado"
            );
        }

        // verificando duplicidade (ignorando o próprio registro)
        $this->checkEmailUnique($user->getEmail(), $user->getId());

        if ($user->getCpf()) {
            $this->checkCpfUnique($user->getCpf(), $user->getId());
        }

        if ($user ->getCouncilId()) {
            $this->checkCouncilIdUnique($user->getCouncilId(), $user->getId());
        }

        try{
            $sql = "UPDATE user SET
                    name = :name,
                    email = :email,
                    cpf = :cpf,
                    phone = :phone
                    birthdate = :birthdate,
                    professional_type = :professional_type,
                    council_id = :council_id
                    specialty = :specialty,
                    bio = :bio,
                    active = :active
                    WHERE id = :id AND deleted_at IS NULL";

            $stmt = $this->pdo->prepare($sql);

            return $stmt->execute([
                'id'                => $user->getId(),
                'name'              => $user->getName(),
                'email'             => $user->getEmail(),
                'cpf'               => $user->getCpf(),
                'phone'             => $user->getPhone(),
                'birthdate'         => $user->getBirthdate(),
                'professional_type' => $user->getProfessionalType(),
                'council_id'        => $user->getCouncilId(),
                'specialty'         => $user->getSpecialty(),
                'bio'               => $user->getBio(),
                'active'            => $user->isActive() ? 1 :0,
            ]);
        } catch (PDOException $e) {
            error_log("Erro ao atualizar usuário: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     *  Atualiza apenas a senha do usuario
     *  @param int $userId
     *  @param string $newPlainPassword - Senha em texto puro (sera hasheada)
     */

    public function updatePassword(int $userId, string $newPlainPassword): bool {
        if (strlen($newPlainPassword) < 6) {
            throw new InvalidArgumentException("Senha deve ter no mínimo 6 caracteres");
        }

        try {
            $hashedPassword = password_hash($newPlainPassword, PASSWORD_ARGON2ID);

            $stmt = $this->pdo->prepare(
                "UPDATE users SET password = :password WHERE id = :id AND deleted_at IS NULL"
            );

            return $stmt->execute([
                'id' => $userId,
                'password' => $hashedPassword,
            ]);
        } catch (PDOException $e) {
            error_log("Erro ao atualizar senha: " .$e->getMessage());
            throw $e;
        }
    }


    /*
    ==============================================================================
    MÉTODOS DE DELEÇÃO (soft delete)
    ===============================================================================
    */

    /**
     *  Soft delete: marca usuário como deletado
     ** IMPORTANTE: usa a stores procedure do banco que verifica agendamentos futuros
     *  
     * @throws DomainException se usuario tiver agendamentos futuros ativos
     */

     public function delete(int $id): bool {
        try {
            // chama a store procedure que faz as validações
            $stmt = $this->pdo->prepare("CALL sp_delete_user(:user_id)");
            $stmt->execute(['user_id' => $id]);

            return true;
        } catch(PDOException $e) {
            // a stored procedure lança erro SE houver agendamentos futuros
            if (str_contains($e->getMessage(), 'agendamentos futuros')) {
                throw new DomainException(
                    "Não é possível desativar o usuário: existem agendamentos futuros vinculados. " .
                    "Cancele-os antes de desativar o cadastro."
                );
            }

            error_log("Erro ao deletar usuário: " . $e->getMessage());
            throw $e;
        }
     }

     /**
      *  *HARD DELETE: remove permanentemente do banco
      *   !USE SÓ PARA TESTES/CORREÇÕES
      */

     public function hardDelete(int $id): bool {
        try {
            $stmt = $this->pdo->prepare("DELETE from users WHERE id = :id");
            return $stmt->execute(['id' => $id]);

        } catch (PDOException $e) {
            // violação de FK: há agendamentos vinculados
            if ($e->getCode() === '2300') {
                throw new DomainException("Não é possível deletar permanentemente: existem registros vinculados");
            }
        }

        error_log("Erro ao deletar usuário (hard delete): " .$e->getMessage());
        throw $e;
    }

    /**
     *  Restaura um usuario soft-deleted
     */

    public function restore(int $id): bool {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE users
                SET deleted_at = NULL, active = 1
                WHERE id = :id AND deleted_at IS NOT NULL"
            );

            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                throw new InvalidArgumentException("Usuário não encontrado ou não está desativado");
            }

            return true;
        } catch(PDOException $e) {
            error_log("Erro ao restaurar usuário: " . $e->getMessage());
            throw $e;
        }
    } 

    /*
    ==============================================================================
    MÉTODOS DE AUTENTIFICAÇÃO
    ===============================================================================
    */

    /**
     *  Autentica usuário por email e senha
     *  @return User|null - retorna User se credencias válidas, null caso contrário
     */

    public function authenticate(string $email, string $plainPassword): ?User {
        $user = $this->findByEmail($email);

        // usuario desativado não pode fazer login
        if (!$user->isActive()) {
            throw new DomainException("Usuário desativado");
        }

        // valida a senha
        if (!$user->validatePassword($plainPassword)) {
            return null;
        }

        return $user;
    }

    /*
    ==============================================================================
    VALIDAÇÕES PRIVADAS (uniques)
    ===============================================================================
    */

    private function checkEmailUnique(string $email, ?int $excludeId = null): void {
        $existing = $this->findByEmail($email);

        if ($existing && (!$excludeId || $existing->getId() !== $excludeId)) {
            throw new DomainException("Email já cadastrado");
        }
    }

    private function checkCpfUnique(string $cpf, ?int $excludeId = null): void {
        $existing = $this->findByCpf($cpf);

        if ($existing && (!$excludeId || $existing->getId() !== $excludeId)) {
            throw new DomainException("CPF já cadastrado");
        }
    }

    private function checkCouncilIdUnique(string $councilId, ?int $excludeId = null): void
    {
        $existing = $this->findByCouncilId($councilId);

        if ($existing && (!$excludeId || $existing->getId() !== $excludeId)) {
            throw new DomainException("Número de registro profissional (CRP/CRM) já cadastrado");
        }
    }

    /*
    ==============================================================================
    MÉTODOS AUXILIARES
    ===============================================================================
    */

    /**
     *  Conta total de usuario por role
     */

    public function countByRole(string $role, bool $activeOnly = true): int {
        try{
            $sql = "SELECT COUNT(*) FROM users WHERE role = :role AND deleted_at IS NULL";

            if ($activeOnly) {
                $sql .= " AND active = 1";
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['role' => $role]);

            return (int) $stmt->fetchColumn();

        } catch (PDOException $e) {
            error_log("Erro ao contar usuários: " . $e->getMessage());
            return 0;
        }
    }

    /**
     *  Lista todos os usuários (com paginação)
     *  @param int $limit - Quantidade de registros por página
     *  @param int $offset - Deslocamento (página * limit)
     *  @return User[]
     */

    public function findAll(int $limit = 50, int $offset = 0, bool $includeDeleted = false): array {
        try {
            $sql = "SELECT * FROM users";

            if (!$includeDeleted) {
                $sql .= " WHERE deleted_at IS NULL";
            }

            $sql .= " ORDER BY name LIMIT :limit :offset";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn($data) => new User($data), $results);

        } catch (PDOException $e) {
            error_log("Erro ao listas usuários: " . $e->getMessage());
            return [];
        }
    }

    /**
     *  Busca usuário por nome (busca parcial)
     *  @param string $name - Nome
     *  @return User[]
     */

    public function searchByName(string $name, bool $activeOnly = true): array {
        try {
            $sql = "SELECT * FROM users WHERE name LIKE :name AND deleted_at IS NULL";

            if ($activeOnly) {
                $sql .= " AND active = 1";
            }

            $sql .= " ORDER BY name LIMIT 50";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['name' => "%{name}%"]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn($data) => new User($data), $results);

        } catch (PDOException $e) {
            error_log("Erro ao buscar usuário por nome " . $e->getMessage());
            return [];
        }
    }

}
<?php
namespace App\Models;

use DateTime;

Class User {
    private ?int $id;
    private string $name;
    private string $email;
    private string $password;
    private string $cpf;
    private string $phone;
    private ?DateTime $birthdate;
    private string $role; // 'admin', 'psychologist', 'patient'
    
    // area profissional - null para pacientes/admin
    private ?string $professionalType; // 'psychologist', 'educational psychologist.', etc.
    private ?string $councilId; // CRP - null para pacientes/admin
    private ?string $specialty; 
    private ?string $bio;

    // area de controle
    private bool $active;
    private ?DateTime $deleted_at;
    private ?DateTime $created_at;
    private ?DateTime $updated_at;



    public function __construct(array $data) {
        $this->id = isset($data['id']) ? (int) $data['id'] : null;
        $this->name = $data['name'] ?? '';
        $this->email = $data['email'] ?? '';

        // senha HASHEADA ao receber os dados (ex: do controller) - se já estiver hasheada, não precisa hashear de novo
        $raw = $data['password'] ?? '';
        $this->password = self::isHashed($raw) ? $raw : password_hash($raw, PASSWORD_ARGON2ID);

        $this->cpf = $data['cpf'] ?? '';
        $this->phone = $data['phone'] ?? '';

        $this->birthdate = isset($data['birthdate']) ? self::parseDate($data['birthdate']) : null; 

        $this->role = $data['role'] ?? 'patient'; // default para paciente

        // area profissional - só relevante para psicólogos, mas pode ser nula para pacientes/admin
        $this->professionalType = $data['professionalType'] ?? null;
        $this->councilId = $data['councilId'] ?? null;
        $this->specialty = $data['specialty'] ?? null;
        $this->bio = $data['bio'] ?? null;

        // controle de status
        $this->active = (bool) ($data['active'] ?? true); // default para ativo
        $this->deleted_at = self::parseDate($data['deleted_at'] ?? null);
        $this->created_at = self::parseDate($data['created_at'] ?? null);
        $this->updated_at = self::parseDate($data['updated_at'] ?? null);
    }

    // GETTERS
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getEmail(): string { return $this->email; }
    public function getRole(): string { return $this->role; }
    public function getCpf(): ?string { return $this->cpf; }
    public function getPhone(): ?string { return $this->phone; }
    public function getBirthdate(): ?DateTime { return $this->birthdate; }
    public function getProfessionalType(): ?string { return $this->professionalType;}
    public function getCouncilId(): ?string { return $this->councilId;}
    public function getSpecialty(): ?string { return $this->specialty;}
    public function getBio(): ?string { return $this->bio; }
    public function isActive(): bool { return $this->active; }
    public function getDeletedAt(): ?DateTime { return $this->deleted_at;}
    public function getCreatedAt(): ?DateTime { return $this->created_at;}
    public function getUpdatedAt(): ?DateTime { return $this->updated_at;}

    // ---- VERIFICAÇÃO DE ROLE ----
    public function isPatient(): bool {
        return $this->role === 'patient';
    }

    public function isPsychologist(): bool {
        return $this->role === 'psychologist';
    }

    public function isAdmin(): bool {
        return $this->role === 'admin';
    }

    public function isProfessional(): bool {
        return $this->role === 'professional';
    }

    // verifica se tem CRP 
    public function hasConcilId(): bool {
        return !empty($this->councilId);
    }

    // soft delete - marcar como inativo 
    public function isDeleted(): bool {
        return $this->deleted_at !== null;
    }

    // validação da senha do usuario
    public function validatePassword(string $plainPassword): bool { 
        return password_verify($plainPassword, $this->password);
    }

    // usado pelo repository para salvar no banco (nunca expoe a senha em texto puro)
    public function getPasswordHash(): string {
        return $this->password;
    }

    // ---- SERIALIZAÇÃO ----
    // PARA USO INTERNO (EX: PERSINTÊNCIA NO BANCO, LOGS INTERNOS) - SEM EXPOR SENHA 
    public function toArray(): array { // [ ] JSON econde?
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'email'             => $this->email,
            'cpf'               => $this->cpf,
            'phone'             => $this->phone,
            'birthdate'         => $this->birthdate?->format('Y-m-d'),
            'role'              => $this->role,
            'professional_type' => $this->professionalType,
            'council_id'        => $this->councilId,
            'specialty'         => $this->specialty,
            'bio'               => $this->bio,
            'active'            => $this->active,
            'deleted_at'        => $this->deleted_at?->format('Y-m-d H:i:s'),
            'created_at'        => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'        => $this->updated_at?->format('Y-m-d H:i:s'),

        ];
    }

    // ---- PARA RESPOSTAS DE API - SEM CAMPOS SENSÍVEIS (EX: SENHA) ----
    public function toPublicArray(): array {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'email'             => $this->email,
            'cpf'               => $this->cpf,
            'phone'             => $this->phone,
            'birthdate'         => $this->birthdate?->format('Y-m-d'),
            'role'              => $this->role,
            'professional_type' => $this->professionalType,
            'council_id'        => $this->councilId,
            'specialty'         => $this->specialty,
            'bio'               => $this->bio,
            'active'            => $this->active,
            'created_at'        => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    
    /* 
       ==============================================================================
        HELPER METHODS (PRIVATE)
       ============================================================================== 
    */ 

    //  converte string de data do banco para DateTime (pode ser nulo)
    private static function parseDate(?string $dvalue): ?DateTime {
        if (empty($dvalue)) return null;

        $dt = DateTime::createFromFormat('Y-m-d H:i:S', $dvalue)
            ?: DateTime::createFromFormat('Y-m-d', $dvalue);

        return $dt ?: null;
    }


    // detecta se a senha já está hasheada (para evitar duplo hash ao atualizar)
    private static function isHashed(string $value): bool {
        // formato de hash (ex: começa com $2y$ para bcrypt ou $argon2id$ para Argon2)
        return strlen($value) > 20 && (
            str_starts_with($value, '$2y$') ||     // bcrypt
            str_starts_with($value, '$argon2id$')  // Argon2
        );
    }
}
?>
<?php
namespace App\Services;

use App\Repositories\UserRepository;
use DuplicateUserException;
use InvalidEmailException;
use WeakPasswordException;
use App\Models\User;

class UserService {

    public function __construct(private UserRepository $userRepo) {}

    public function createUserPatient(array $data): int {
       if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException($data['email']);
        }

         if (strlen($data['password']) < 6) {
            throw new WeakPasswordException();
        }

        if ($this->userRepo->findByEmail($data['email'])) {
            throw new DuplicateUserException('e-mail');
        }

        if ($this->userRepo->findByCpf($data['cpf'])) {
            throw new DuplicateUserException('CPF');
        }
        
        unset($data['role']); 
        unset($data['active']);
        unset($data['id']);
        unset($data['deleted_at']);

        $patient = new User($data);

        return $this->userRepo->createPatient($patient);
    }

    private function validateData(array $data): array {
        $data = [
        'name' => trim($data['name']),
        'email' => strtolower(trim($data['email'])),
        'cpf' => preg_replace('/\D/', '', $data['cpf']),
        'password' => password_hash(
            $data['password'],
            PASSWORD_DEFAULT
        ),
        'role' => 'patient',
        'active' => true

        return $data['id']->getId();
    }
}
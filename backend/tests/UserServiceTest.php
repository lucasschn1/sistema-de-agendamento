<?php

namespace Tests;

use App\Services\UserService;
use App\Repositories\UserRepository;
use App\Models\User;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

use App\Exceptions\ValidationException;
use App\Exceptions\user\InvalidEmailException;
use App\Exceptions\user\DuplicateUserException;
use App\Exceptions\user\WeakPasswordException;
use App\Exceptions\user\UserNotFoundException;
use App\Exceptions\user\UserHasFutureAppointmentsException;
use App\Exceptions\user\InactiveUserException;
use App\Exceptions\user\InvalidUserRoleException;

/**
 * UserServiceTest
 * 
 * Testa a camada de negócio do UserService de forma isolada,
 * usando mocks para o UserRepository.
 * 
 * Rodar: vendor/bin/phpunit tests/UserServiceTest.php
 */
class UserServiceTest extends TestCase
{
    /** @var UserRepository&MockObject */
    private MockObject   $userRepositoryMock;
    private UserService  $service;


    protected function setUp(): void
    {
        $this->userRepositoryMock = $this->createMock(UserRepository::class);
        $this->service = new UserService($this->userRepositoryMock);
    }


    // =========================================================
    // HELPERS PRIVADOS
    // =========================================================

    private function makeUser(
    int    $id            = 1,
    string $role          = 'patient',
    bool   $active        = true,
    bool   $deleted       = false,
    bool   $validPassword = true
): MockObject {
    $mock = $this->createMock(User::class);
    $mock->method('getId')->willReturn($id);
    $mock->method('getRole')->willReturn($role);
    $mock->method('isActive')->willReturn($active);
    $mock->method('isDeleted')->willReturn($deleted);
    $mock->method('isPatient')->willReturn($role === 'patient');
    $mock->method('isProfessional')->willReturn($role === 'professional');
    $mock->method('isAdmin')->willReturn($role === 'admin');
    $mock->method('getName')->willReturn('Usuário Teste');
    $mock->method('getEmail')->willReturn('teste@email.com');
    $mock->method('getCpf')->willReturn('123.456.789-00');
    $mock->method('getCouncilId')->willReturn(null);
    $mock->method('getProfessionalType')->willReturn(null);
    $mock->method('getBirthdate')->willReturn(null);
    $mock->method('getPhone')->willReturn(null);
    $mock->method('validatePassword')->willReturn($validPassword);
    $mock->method('toArray')->willReturn([
        'id'    => $id,
        'name'  => 'Usuário Teste',
        'email' => 'teste@email.com',
        'role'  => $role,
    ]);
    $mock->method('toPublicArray')->willReturn([
        'id'    => $id,
        'name'  => 'Usuário Teste',
        'email' => 'teste@email.com',
        'role'  => $role,
    ]);
    return $mock;
}

    private function validPatientData(): array
    {
        return [
            'name'     => 'João Silva',
            'email'    => 'joao@email.com',
            'password' => 'senha123',
            'cpf'      => '123.456.789-00',
            'phone'    => '11-99999-0000',
        ];
    }

    private function validProfessionalData(): array
    {
        return [
            'name'              => 'Dr. Carlos Silva',
            'email'             => 'carlos@clinica.com',
            'password'          => 'senha123',
            'professional_type' => 'Psicólogo',
            'council_id'        => 'CRP 06/123456',
            'specialty'         => 'TCC',
        ];
    }


    // =========================================================
    // TESTES — createPatient()
    // =========================================================

    public function testCreatePatientSuccessfully(): void
    {
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('createPatient')
            ->willReturn(1);

        $id = $this->service->createPatient($this->validPatientData());
        $this->assertEquals(1, $id);
    }

    public function testCreatePatientThrowsIfNameIsMissing(): void
    {
        $this->expectException(ValidationException::class);

        $data = $this->validPatientData();
        unset($data['name']);
        $this->service->createPatient($data);
    }

    public function testCreatePatientThrowsIfEmailIsMissing(): void
    {
        $this->expectException(ValidationException::class);

        $data = $this->validPatientData();
        unset($data['email']);
        $this->service->createPatient($data);
    }

    public function testCreatePatientThrowsIfPasswordIsMissing(): void
    {
        $this->expectException(ValidationException::class);

        $data = $this->validPatientData();
        unset($data['password']);
        $this->service->createPatient($data);
    }

    public function testCreatePatientThrowsIfEmailIsInvalid(): void
    {
        $this->expectException(InvalidEmailException::class);

        $data          = $this->validPatientData();
        $data['email'] = 'email-invalido';
        $this->service->createPatient($data);
    }

    public function testCreatePatientThrowsIfEmailHasTemporaryDomain(): void
    {
        $this->expectException(InvalidEmailException::class);

        $data          = $this->validPatientData();
        $data['email'] = 'joao@tempmail.com';
        $this->service->createPatient($data);
    }

    public function testCreatePatientThrowsIfPasswordIsTooShort(): void
    {
        $this->expectException(WeakPasswordException::class);

        $data             = $this->validPatientData();
        $data['password'] = '123';
        $this->service->createPatient($data);
    }

    public function testCreatePatientThrowsIfEmailIsDuplicate(): void
    {
        $this->expectException(DuplicateUserException::class);

        $this->userRepositoryMock
            ->method('createPatient')
            ->willThrowException(new \DomainException('Email já cadastrado'));

        $this->service->createPatient($this->validPatientData());
    }

    public function testCreatePatientThrowsIfCpfIsDuplicate(): void
    {
        $this->expectException(DuplicateUserException::class);

        $this->userRepositoryMock
            ->method('createPatient')
            ->willThrowException(new \DomainException('CPF já cadastrado'));

        $this->service->createPatient($this->validPatientData());
    }

    public function testCreatePatientSanitizesEmail(): void
    {
        // Verifica que o email é normalizado para lowercase
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('createPatient')
            ->with($this->callback(function (User $user) {
                return $user->getEmail() === 'joao@email.com';
            }))
            ->willReturn(1);

        $data          = $this->validPatientData();
        $data['email'] = 'JOAO@EMAIL.COM';
        $this->service->createPatient($data);
    }


    // =========================================================
    // TESTES — createProfessional()
    // =========================================================

    public function testCreateProfessionalSuccessfully(): void
    {
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('createProfessional')
            ->willReturn(2);

        $id = $this->service->createProfessional($this->validProfessionalData());
        $this->assertEquals(2, $id);
    }

    public function testCreateProfessionalThrowsIfProfessionalTypeIsMissing(): void
    {
        $this->expectException(ValidationException::class);

        $data = $this->validProfessionalData();
        unset($data['professional_type']);
        $this->service->createProfessional($data);
    }

    public function testCreateProfessionalThrowsIfProfessionalTypeIsEmpty(): void
    {
        $this->expectException(ValidationException::class);

        $data                      = $this->validProfessionalData();
        $data['professional_type'] = '   ';
        $this->service->createProfessional($data);
    }

    public function testCreateProfessionalThrowsIfCouncilIdIsDuplicate(): void
    {
        $this->expectException(DuplicateUserException::class);

        $this->userRepositoryMock
            ->method('createProfessional')
            ->willThrowException(new \DomainException('registro profissional já cadastrado'));

        $this->service->createProfessional($this->validProfessionalData());
    }

    public function testCreateProfessionalWorksWithoutCouncilId(): void
    {
        // Psicopedagogos não têm CRP — deve funcionar sem council_id
        $this->userRepositoryMock
            ->method('createProfessional')
            ->willReturn(3);

        $data = $this->validProfessionalData();
        unset($data['council_id']);
        $data['professional_type'] = 'Psicopedagogo';

        $id = $this->service->createProfessional($data);
        $this->assertEquals(3, $id);
    }


    // =========================================================
    // TESTES — updateUser()
    // =========================================================

    public function testUpdateUserSuccessfully(): void
    {
        $user = $this->makeUser(1);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn($user);

        $this->userRepositoryMock
            ->expects($this->once())
            ->method('update')
            ->willReturn(true);

        $result = $this->service->updateUser(1, ['name' => 'Novo Nome']);
        $this->assertTrue($result);
    }

    public function testUpdateUserThrowsIfNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn(null);

        $this->service->updateUser(99, ['name' => 'Novo Nome']);
    }

    public function testUpdateUserThrowsIfEmailIsInvalid(): void
    {
        $this->expectException(InvalidEmailException::class);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn($this->makeUser(1));

        $this->service->updateUser(1, ['email' => 'invalido']);
    }

    public function testUpdateUserThrowsIfEmailIsDuplicate(): void
    {
        $this->expectException(DuplicateUserException::class);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn($this->makeUser(1));

        $this->userRepositoryMock
            ->method('update')
            ->willThrowException(new \DomainException('Email já cadastrado'));

        $this->service->updateUser(1, ['email' => 'outro@email.com']);
    }


    // =========================================================
    // TESTES — updatePassword()
    // =========================================================

    public function testUpdatePasswordSuccessfully(): void
    {
        $user = $this->makeUser(1);
        $user->method('validatePassword')->willReturn(true);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn($user);

        $this->userRepositoryMock
            ->expects($this->once())
            ->method('updatePassword')
            ->willReturn(true);

        $result = $this->service->updatePassword(1, 'senhaAtual', 'novaSenha123');
        $this->assertTrue($result);
    }

    public function testUpdatePasswordThrowsIfCurrentPasswordIsWrong(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $user = $this->makeUser(1, validPassword: false);

        $user->method('validatePassword')->willReturn(false);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn($user);

        $this->service->updatePassword(1, 'senhaErrada', 'novaSenha123');
    }

    public function testUpdatePasswordThrowsIfNewPasswordIsTooShort(): void
    {
        $this->expectException(WeakPasswordException::class);

        $user = $this->makeUser(1);
        $user->method('validatePassword')->willReturn(true);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn($user);

        $this->service->updatePassword(1, 'senhaAtual', '123');
    }

    public function testUpdatePasswordThrowsIfUserNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn(null);

        $this->service->updatePassword(99, 'senhaAtual', 'novaSenha123');
    }


    // =========================================================
    // TESTES — deactivateUser()
    // =========================================================

    public function testDeactivateUserSuccessfully(): void
    {
        $user = $this->makeUser(1);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn($user);

        $this->userRepositoryMock
            ->expects($this->once())
            ->method('delete')
            ->willReturn(true);

        $result = $this->service->deactivateUser(1);
        $this->assertTrue($result);
    }

    public function testDeactivateUserThrowsIfNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn(null);

        $this->service->deactivateUser(99);
    }

    public function testDeactivateUserThrowsIfHasFutureAppointments(): void
    {
        $this->expectException(UserHasFutureAppointmentsException::class);

        $user = $this->makeUser(1);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn($user);

        $this->userRepositoryMock
            ->method('delete')
            ->willThrowException(new \DomainException('agendamentos futuros'));

        $this->service->deactivateUser(1);
    }


    // =========================================================
    // TESTES — authenticate()
    // =========================================================

    public function testAuthenticateSuccessfully(): void
    {
        $user = $this->makeUser(1, 'professional', true);
        $user->method('validatePassword')->willReturn(true);

        $this->userRepositoryMock
            ->method('authenticate')
            ->willReturn($user);

        $result = $this->service->authenticate('carlos@clinica.com', 'senha123');
        $this->assertInstanceOf(User::class, $result);
    }

    public function testAuthenticateThrowsIfEmailIsInvalid(): void
    {
        $this->expectException(InvalidEmailException::class);

        $this->service->authenticate('email-invalido', 'senha123');
    }

    public function testAuthenticateThrowsIfCredentialsAreWrong(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->userRepositoryMock
            ->method('authenticate')
            ->willReturn(null);

        $this->service->authenticate('carlos@clinica.com', 'senhaErrada');
    }

    public function testAuthenticateThrowsIfUserIsInactive(): void
    {
        $this->expectException(InactiveUserException::class);

        $this->userRepositoryMock
            ->method('authenticate')
            ->willThrowException(new \DomainException('desativado'));

        $this->service->authenticate('carlos@clinica.com', 'senha123');
    }


    // =========================================================
    // TESTES — validateUserForAppointment()
    // =========================================================

    public function testValidateUserForAppointmentSuccessfully(): void
    {
        $patient = $this->makeUser(1, 'patient', true);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn($patient);

        $result = $this->service->validateUserForAppointment(1, 'patient');
        $this->assertInstanceOf(User::class, $result);
    }

    public function testValidateUserForAppointmentThrowsIfNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn(null);

        $this->service->validateUserForAppointment(99, 'patient');
    }

    public function testValidateUserForAppointmentThrowsIfInactive(): void
    {
        $this->expectException(InactiveUserException::class);

        $user = $this->makeUser(1, 'patient', false);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn($user);

        $this->service->validateUserForAppointment(1, 'patient');
    }

    public function testValidateUserForAppointmentThrowsIfWrongRole(): void
    {
        $this->expectException(InvalidUserRoleException::class);

        $professional = $this->makeUser(1, 'professional', true);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn($professional);

        // Espera 'patient' mas encontrou 'professional'
        $this->service->validateUserForAppointment(1, 'patient');
    }


    // =========================================================
    // TESTES — searchByName()
    // =========================================================

    public function testSearchByNameSuccessfully(): void
    {
        $this->userRepositoryMock
            ->method('searchByName')
            ->willReturn([$this->makeUser(1)]);

        $results = $this->service->searchByName('João');
        $this->assertCount(1, $results);
    }

    public function testSearchByNameThrowsIfQueryIsTooShort(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->searchByName('J'); // menos de 2 caracteres
    }


    // =========================================================
    // TESTES — getUserStats()
    // =========================================================

    public function testGetUserStatsReturnsAllRoles(): void
    {
        $this->userRepositoryMock
            ->method('countByRole')
            ->willReturnMap([
                ['patient',      true, 10],
                ['professional', true, 3],
                ['admin',        true, 1],
            ]);

        $stats = $this->service->getUserStats();

        $this->assertArrayHasKey('patients', $stats);
        $this->assertArrayHasKey('professionals', $stats);
        $this->assertArrayHasKey('admins', $stats);
        $this->assertEquals(10, $stats['patients']);
        $this->assertEquals(3, $stats['professionals']);
        $this->assertEquals(1, $stats['admins']);
    }
}
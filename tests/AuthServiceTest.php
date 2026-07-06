<?php

namespace Tests;

use App\Service\AuthService;
use App\Repositories\UserRepository;
use App\Models\User;

use App\Exceptions\ValidationException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\user\InactiveUserException;
use App\Exceptions\user\UserNotFoundException;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * AuthServiceTest
 * 
 * Testa a camada de autenticação JWT do AuthService de forma isolada.
 * 
 * IMPORTANTE:
 * O JWT_SECRET precisa estar definido para os testes de geração/validação.
 * O setUp() define via $_ENV diretamente.
 * 
 * Rodar: vendor/bin/phpunit tests/AuthServiceTest.php
 */
class AuthServiceTest extends TestCase {

    /** @var UserRepository&MockObject */
    private MockObject  $userRepositoryMock;
    private AuthService $service;


    protected function setUp(): void
    {
        // Define variáveis de ambiente para os testes
        $_ENV['JWT_SECRET']    = bin2hex(random_bytes(32));
        $_ENV['JWT_EXPIRATION'] = '3600'; // 1 hora
        $_ENV['JWT_ALGORITHM'] = 'HS256';
        $_ENV['APP_URL']       = 'http://localhost';

        $this->userRepositoryMock = $this->createMock(UserRepository::class);
        $this->service = new AuthService($this->userRepositoryMock);
    }

    protected function tearDown(): void
    {
        // Limpa variáveis de ambiente após cada teste
        unset($_ENV['JWT_SECRET'], $_ENV['JWT_EXPIRATION'], $_ENV['JWT_ALGORITHM']);
    }


    // =========================================================
    // HELPERS PRIVADOS
    // =========================================================

    /**
     * Cria um mock de User com valores configuráveis.
     *
     * IMPORTANTE: os valores de isActive/isDeleted/validatePassword devem
     * ser definidos AQUI, no momento da criação do mock, e não sobrescritos
     * depois via ->method(...)->willReturn(...) em cima do mock já criado.
     */
    private function makeActiveUser(
        int    $id            = 1,
        string $role          = 'professional',
        bool   $isActive      = true,
        bool   $isDeleted     = false,
        bool   $validPassword = true
    ): MockObject {
        $mock = $this->createMock(User::class);
        $mock->method('getId')->willReturn($id);
        $mock->method('getRole')->willReturn($role);
        $mock->method('getName')->willReturn('Dr. Carlos');
        $mock->method('getEmail')->willReturn('carlos@clinica.com');
        $mock->method('isActive')->willReturn($isActive);
        $mock->method('isDeleted')->willReturn($isDeleted);
        $mock->method('validatePassword')->willReturn($validPassword);
        $mock->method('toPublicArray')->willReturn([
            'id'   => $id,
            'name' => 'Dr. Carlos',
            'role' => $role,
        ]);
        return $mock;
    }


    // =========================================================
    // TESTES — Construtor
    // =========================================================

    public function testThrowsIfJwtSecretIsMissing(): void
    {
        $this->expectException(\RuntimeException::class);

        unset($_ENV['JWT_SECRET']);
        new AuthService($this->userRepositoryMock);
    }

    public function testThrowsIfJwtSecretIsEmpty(): void
    {
        $this->expectException(\RuntimeException::class);

        $_ENV['JWT_SECRET'] = '';
        new AuthService($this->userRepositoryMock);
    }


    // =========================================================
    // TESTES — login()
    // =========================================================

    public function testLoginSuccessfully(): void
    {
        $user = $this->makeActiveUser(1, 'professional');

        $this->userRepositoryMock
            ->method('findByEmail')
            ->willReturn($user);

        $result = $this->service->login('carlos@clinica.com', 'senha123');

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals('Bearer', $result['token_type']);
        $this->assertNotEmpty($result['access_token']);
        $this->assertNotEmpty($result['refresh_token']);
    }

    public function testLoginThrowsIfEmailIsEmpty(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->login('', 'senha123');
    }

    public function testLoginThrowsIfPasswordIsEmpty(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->login('carlos@clinica.com', '');
    }

    public function testLoginThrowsIfUserNotFound(): void
    {
        $this->expectException(UnauthorizedException::class);

        $this->userRepositoryMock
            ->method('findByEmail')
            ->willReturn(null);

        $this->service->login('naoexiste@email.com', 'senha123');
    }

    public function testLoginThrowsIfPasswordIsWrong(): void
    {
        $this->expectException(UnauthorizedException::class);

        $user = $this->makeActiveUser(validPassword: false);

        $this->userRepositoryMock
            ->method('findByEmail')
            ->willReturn($user);

        $this->service->login('carlos@clinica.com', 'senhaErrada');
    }

    public function testLoginThrowsIfUserIsInactive(): void
    {
        $this->expectException(InactiveUserException::class);

        $user = $this->makeActiveUser(isActive: false);

        $this->userRepositoryMock
            ->method('findByEmail')
            ->willReturn($user);

        $this->service->login('carlos@clinica.com', 'senha123');
    }

    public function testLoginThrowsIfUserIsDeleted(): void
    {
        // Usuário deletado (soft delete) não deve conseguir logar
        // A mensagem deve ser idêntica à de credenciais erradas — segurança
        $this->expectException(UnauthorizedException::class);

        $user = $this->makeActiveUser(isDeleted: true);

        $this->userRepositoryMock
            ->method('findByEmail')
            ->willReturn($user);

        $this->service->login('carlos@clinica.com', 'senha123');
    }

    public function testLoginReturnsCorrectExpirationTime(): void
    {
        $_ENV['JWT_EXPIRATION'] = '7200'; // 2 horas
        $this->service = new AuthService($this->userRepositoryMock);

        $user = $this->makeActiveUser();

        $this->userRepositoryMock
            ->method('findByEmail')
            ->willReturn($user);

        $result = $this->service->login('carlos@clinica.com', 'senha123');
        $this->assertEquals(7200, $result['expires_in']);
    }


    // =========================================================
    // TESTES — validateToken()
    // =========================================================

    public function testValidateTokenSuccessfully(): void
    {
        $user = $this->makeActiveUser(1);

        $this->userRepositoryMock
            ->method('findByEmail')
            ->willReturn($user);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn($user);

        // Gera um token real via login
        $loginResult = $this->service->login('carlos@clinica.com', 'senha123');
        $token       = $loginResult['access_token'];

        $result = $this->service->validateToken($token);
        $this->assertInstanceOf(User::class, $result);
    }

    public function testValidateTokenThrowsIfTokenIsInvalid(): void
    {
        $this->expectException(UnauthorizedException::class);

        $this->service->validateToken('token.invalido.aqui');
    }

    public function testValidateTokenThrowsIfTokenIsExpired(): void
    {
        $this->expectException(UnauthorizedException::class);

        // Cria service com expiração de -1 segundo (já expirado)
        $_ENV['JWT_EXPIRATION'] = '-1';
        $expiredService = new AuthService($this->userRepositoryMock);

        $user = $this->makeActiveUser();
        $this->userRepositoryMock
            ->method('findByEmail')
            ->willReturn($user);

        $loginResult = $expiredService->login('carlos@clinica.com', 'senha123');
        $token       = $loginResult['access_token'];

        // Valida com o service normal — vai rejeitar token expirado
        $this->service->validateToken($token);
    }

    public function testValidateTokenThrowsIfUserIsInactive(): void
    {
        $this->expectException(UnauthorizedException::class);

        $activeUser   = $this->makeActiveUser(1);
        $inactiveUser = $this->makeActiveUser(1, isActive: false);

        // Login com usuário ativo
        $this->userRepositoryMock
            ->method('findByEmail')
            ->willReturn($activeUser);

        $loginResult = $this->service->login('carlos@clinica.com', 'senha123');
        $token       = $loginResult['access_token'];

        // Usuário foi desativado após o login
        $this->userRepositoryMock
            ->method('findById')
            ->willReturn($inactiveUser);

        $this->service->validateToken($token);
    }

    public function testValidateTokenThrowsIfRefreshTokenIsUsedAsAccessToken(): void
    {
        $this->expectException(UnauthorizedException::class);

        $user = $this->makeActiveUser(1);
        $this->userRepositoryMock
            ->method('findByEmail')
            ->willReturn($user);

        $loginResult  = $this->service->login('carlos@clinica.com', 'senha123');
        $refreshToken = $loginResult['refresh_token'];

        // Tenta usar o refresh token como access token
        $this->service->validateToken($refreshToken);
    }


    // =========================================================
    // TESTES — refresh()
    // =========================================================

    public function testRefreshSuccessfully(): void
    {
        $user = $this->makeActiveUser(1);

        $this->userRepositoryMock
            ->method('findByEmail')
            ->willReturn($user);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn($user);

        $loginResult  = $this->service->login('carlos@clinica.com', 'senha123');
        $refreshToken = $loginResult['refresh_token'];

        $result = $this->service->refresh($refreshToken);

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertNotEmpty($result['access_token']);
    }

    public function testRefreshThrowsIfAccessTokenIsUsedAsRefreshToken(): void
    {
        $this->expectException(UnauthorizedException::class);

        $user = $this->makeActiveUser(1);
        $this->userRepositoryMock
            ->method('findByEmail')
            ->willReturn($user);

        $loginResult = $this->service->login('carlos@clinica.com', 'senha123');
        $accessToken = $loginResult['access_token'];

        // Tenta usar o access token como refresh token
        $this->service->refresh($accessToken);
    }

    public function testRefreshThrowsIfTokenIsInvalid(): void
    {
        $this->expectException(UnauthorizedException::class);

        $this->service->refresh('token.invalido');
    }

    public function testRefreshThrowsIfUserNoLongerExists(): void
    {
        $this->expectException(UserNotFoundException::class);

        $user = $this->makeActiveUser(1);
        $this->userRepositoryMock
            ->method('findByEmail')
            ->willReturn($user);

        $loginResult  = $this->service->login('carlos@clinica.com', 'senha123');
        $refreshToken = $loginResult['refresh_token'];

        // Usuário foi removido após o login
        $this->userRepositoryMock
            ->method('findById')
            ->willReturn(null);

        $this->service->refresh($refreshToken);
    }

    public function testRefreshThrowsIfUserIsInactive(): void
    {
        $this->expectException(InactiveUserException::class);

        $activeUser   = $this->makeActiveUser(1);
        $inactiveUser = $this->makeActiveUser(1, isActive: false);

        $this->userRepositoryMock
            ->method('findByEmail')
            ->willReturn($activeUser);

        $loginResult  = $this->service->login('carlos@clinica.com', 'senha123');
        $refreshToken = $loginResult['refresh_token'];

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn($inactiveUser);

        $this->service->refresh($refreshToken);
    }


    // =========================================================
    // TESTES — decodeToken()
    // =========================================================

    public function testDecodeTokenReturnsPayload(): void
    {
        $user = $this->makeActiveUser(42, 'admin');

        $this->userRepositoryMock
            ->method('findByEmail')
            ->willReturn($user);

        $loginResult = $this->service->login('carlos@clinica.com', 'senha123');
        $token       = $loginResult['access_token'];

        $payload = $this->service->decodeToken($token);

        $this->assertEquals(42,       $payload->sub);
        $this->assertEquals('admin',  $payload->role);
        $this->assertEquals('access', $payload->type);
    }

    public function testDecodeTokenThrowsIfTokenIsMalformed(): void
    {
        $this->expectException(UnauthorizedException::class);

        $this->service->decodeToken('nao.e.um.jwt.valido');
    }

    public function testDecodeTokenThrowsIfSignatureIsInvalid(): void
    {
        $this->expectException(UnauthorizedException::class);

        // Token assinado com secret diferente
        $_ENV['JWT_SECRET'] = bin2hex(random_bytes(32));
        $otherService = new AuthService($this->userRepositoryMock);

        $user = $this->makeActiveUser();
        $this->userRepositoryMock
            ->method('findByEmail')
            ->willReturn($user);

        $tokenFromOtherService = $otherService->login('carlos@clinica.com', 'senha123');
        $token = $tokenFromOtherService['access_token'];

        // Valida com o service original (secret diferente) — deve rejeitar
        $this->service->decodeToken($token);
    }
}
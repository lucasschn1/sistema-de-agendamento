<?php

namespace App\Service;

use App\Repositories\UserRepository;
use App\Models\User;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\InactiveUserException;
use App\Exceptions\UserNotFoundException;
use App\Exceptions\ValidationException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

/**
 * AuthService - Gerenciamento de Autenticação JWT
 * 
 * Responsabilidades:
 * - Autenticar usuário por email e senha
 * - Gerar access token (curta duração)
 * - Gerar refresh token (longa duração)
 * - Validar e decodificar tokens
 * - Renovar access token via refresh token
 * 
 * NÃO faz:
 * - Acesso direto ao banco (delega ao UserRepository)
 * - Verificação de permissões por role (responsabilidade do RoleMiddleware)
 */
class AuthService {
    private UserRepository $userRepository;

    private string $secret;
    private int    $expiration;
    private string $algorithm;

    /**
     * Duração do refresh token: 30 dias em segundos
     */
    private const REFRESH_TOKEN_EXPIRATION = 2592000;


    public function __construct(UserRepository $userRepository) {
        $this->userRepository = $userRepository;

        // Lê configurações do .env
        $this->secret     = $_ENV['JWT_SECRET']    ?? '';
        $this->expiration = (int) ($_ENV['JWT_EXPIRATION'] ?? 28800); // 8h padrão
        $this->algorithm  = $_ENV['JWT_ALGORITHM'] ?? 'HS256';

        if (empty($this->secret)) {
            throw new \RuntimeException(
                "JWT_SECRET não configurado. Gere uma chave com: php -r \"echo bin2hex(random_bytes(32));\""
            );
        }
    }


    // =========================================================
    // AUTENTICAÇÃO
    // =========================================================

    /**
     * Autentica usuário e retorna access + refresh tokens
     * 
     * @param string $email
     * @param string $password Senha em texto puro
     * @throws ValidationException   Se email ou senha estiverem vazios
     * @throws UnauthorizedException Se credenciais inválidas
     * @throws InactiveUserException Se usuário estiver desativado
     * @return array [
     *   'access_token'  => string,
     *   'refresh_token' => string,
     *   'token_type'    => 'Bearer',
     *   'expires_in'    => int,
     *   'user'          => array
     * ]
     */
    public function login(string $email, string $password): array {
        // Validações básicas de entrada
        if (empty(trim($email)) || empty(trim($password))) {
            throw new ValidationException([
                'credentials' => 'E-mail e senha são obrigatórios'
            ]);
        }

        // Busca usuário pelo email
        $user = $this->userRepository->findByEmail($email);

        // Não diferencia "usuário não existe" de "senha errada" — segurança
        if (!$user || !$user->validatePassword($password)) {
            throw new UnauthorizedException('E-mail ou senha incorretos');
        }

        // Verifica se está ativo
        if (!$user->isActive()) {
            throw new InactiveUserException();
        }

        // Verifica soft delete
        if ($user->isDeleted()) {
            throw new UnauthorizedException('E-mail ou senha incorretos');
        }

        return [
            'access_token'  => $this->generateAccessToken($user),
            'refresh_token' => $this->generateRefreshToken($user),
            'token_type'    => 'Bearer',
            'expires_in'    => $this->expiration,
            'user'          => $user->toPublicArray(),
        ];
    }

    /**
     * Renova o access token usando um refresh token válido
     * 
     * @param string $refreshToken
     * @throws UnauthorizedException Se refresh token inválido ou expirado
     * @throws UserNotFoundException Se usuário não existir mais
     * @throws InactiveUserException Se usuário foi desativado
     * @return array ['access_token' => string, 'expires_in' => int]
     */
    public function refresh(string $refreshToken): array {
        // Decodifica e valida o refresh token
        $payload = $this->decodeToken($refreshToken);

        // Garante que é um refresh token, não um access token
        if (($payload->type ?? '') !== 'refresh') {
            throw new UnauthorizedException('Token inválido para renovação');
        }

        // Busca o usuário pelo ID do payload
        $user = $this->userRepository->findById((int) $payload->sub);

        if (!$user) {
            throw new UserNotFoundException((int) $payload->sub);
        }

        if (!$user->isActive() || $user->isDeleted()) {
            throw new InactiveUserException();
        }

        return [
            'access_token' => $this->generateAccessToken($user),
            'token_type'   => 'Bearer',
            'expires_in'   => $this->expiration,
        ];
    }

    /**
     * Valida um access token e retorna o usuário correspondente
     * Usado pelo AuthMiddleware em cada requisição protegida
     * 
     * @param string $token
     * @throws UnauthorizedException Se token inválido, expirado ou usuário inativo
     * @return User
     */
    public function validateToken(string $token): User {
        $payload = $this->decodeToken($token);

        // Garante que é um access token
        if (($payload->type ?? '') !== 'access') {
            throw new UnauthorizedException('Tipo de token inválido');
        }

        // Busca usuário atualizado do banco
        // Garante que desativações/deleções em tempo real são respeitadas
        $user = $this->userRepository->findById((int) $payload->sub);

        if (!$user || !$user->isActive() || $user->isDeleted()) {
            throw new UnauthorizedException('Usuário inativo ou não encontrado');
        }

        return $user;
    }

    /**
     * Extrai o payload de um token sem validar o usuário no banco
     * Útil para leitura rápida de claims (role, id) sem query extra
     * 
     * @throws UnauthorizedException Se token inválido ou expirado
     */
    public function decodeToken(string $token): object {
        try {
            return JWT::decode($token, new Key($this->secret, $this->algorithm));

        } catch (ExpiredException $e) {
            throw new UnauthorizedException('Token expirado. Faça login novamente');

        } catch (SignatureInvalidException $e) {
            throw new UnauthorizedException('Assinatura do token inválida');

        } catch (BeforeValidException $e) {
            throw new UnauthorizedException('Token ainda não é válido');

        } catch (\Exception $e) {
            throw new UnauthorizedException('Token inválido');
        }
    }


    // =========================================================
    // GERAÇÃO DE TOKENS
    // =========================================================

    /**
     * Gera o access token (curta duração — padrão 8h)
     * Carrega os dados necessários para o middleware funcionar sem query no banco
     */
    private function generateAccessToken(User $user): string {
        $now = time();

        $payload = [
            // Claims padrão JWT (RFC 7519)
            'iss' => $_ENV['APP_URL'] ?? 'clinica-api', // issuer
            'iat' => $now,                               // issued at
            'exp' => $now + $this->expiration,           // expiration
            'sub' => $user->getId(),                     // subject (user id)

            // Claims customizados
            'type' => 'access',
            'name' => $user->getName(),
            'role' => $user->getRole(),
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * Gera o refresh token (longa duração — 30 dias)
     * Contém apenas o mínimo necessário para renovar o access token
     */
    private function generateRefreshToken(User $user): string {
        $now = time();

        $payload = [
            'iss'  => $_ENV['APP_URL'] ?? 'clinica-api',
            'iat'  => $now,
            'exp'  => $now + self::REFRESH_TOKEN_EXPIRATION,
            'sub'  => $user->getId(),
            'type' => 'refresh',
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }
}
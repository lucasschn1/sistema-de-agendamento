<?php

namespace App\Config;

use PDO;
use PDOException;
use RuntimeException;

Class Database {
    private static ?Database $instance = null;
    private PDO $pdo;

    private string $host;
    private string $dbname;
    private string $user;  
    private string $password;
    private string $port;

    private function __construct() {

        // valida as variaveis de ambiente antes de conectar
        $this->validateEnvVars();

        $this->host = $_ENV['DB_HOST'];

        $this->dbname = $_ENV['DB_NAME'];

        $this->user = $_ENV['DB_USER'];

        $this->password = $_ENV['DB_PASS'];

        $this->port = $_ENV['DB_PORT'] ?? '3306'; // porta padrão

        $this->connect();
    }

    // metodo connect privado (gerenciado pela propria classe)
    private function connect(): void {
        $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset=utf8mb4";

        try {
            $this->pdo = new PDO($dsn, $this->user, $this->password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

        } catch (PDOException $e) {
            throw new RuntimeException(
                "Falha ao conectar com banco de dados: " . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    public static function getInstance(): static {
        if (self::$instance === null) {
            // se não existe - cria a primeira e única instancia
            self::$instance = new self();
        }
        return self::$instance;
    }

    // função que retorna um objeto PDO pra queries
    public function getConnection(): PDO {
        return $this->pdo;
    }

    /**
     * Reseta a instância Singleton
     * 
     * EXCLUSIVO PARA TESTES — permite criar uma conexão limpa por teste
     * sem interferência entre casos de teste
     */
    public static function resetInstance(): void {
        self::$instance = null;
    }

    /**
     * Valida que as variáveis de ambiente obrigatórias estão definidas
     * Falha cedo e com mensagem clara, não na hora da query
     * 
     * @throws RuntimeException
     */
    private function validateEnvVars(): void {
        $required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
        $missing  = [];
 
        foreach ($required as $var) {
            if (empty($_ENV[$var])) {
                $missing[] = $var;
            }
        }
 
        if (!empty($missing)) {
            throw new RuntimeException(
                "Variáveis de ambiente ausentes: " . implode(', ', $missing) .
                ". Verifique seu arquivo .env"
            );
        }
    }
 
    // Bloqueia clonagem e deserialização — padrão Singleton
    private function __clone() {}
    public function __wakeup(): never {
        throw new RuntimeException("Não é possível deserializar um Singleton");
    }
}
?> 
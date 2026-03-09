<?php
Class Database {
    private static ?Database $instance = null;
    private static ?PDO $pdo = null;

    private string $host = 'localhost';
    private string $dbname = 'nome_do_banco';
    private string $user = 'root';
    private string $password = '';

    private function __construct() {
        $this->connect();
    }

    // metodo connect privado (gerenciado pela propria classe)
    private function connect(): PDO {
        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";

    
        try{
            self::$pdo = new PDO($dsn, $this->user, $this->password);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return self::$pdo;
        }
        catch(PDOException $e) {
            die("Erro de conexão: " . $e->getMessage());
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            // se não existe - cria a primeira e única instancia
            self::$instance = new self();
        }
        return self::$instance;
    }

    // função que retorna um objeto PDO pra queries
    public function getConnection(): PDO {
        return self::$pdo;
    }
}
?> 
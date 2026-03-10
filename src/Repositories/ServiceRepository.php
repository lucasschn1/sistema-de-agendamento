<?php
class ServiceRepository {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * =============================================================================
     * MÉTODOS DE BUSCA (READ)
     * =============================================================================
     */

    /**
     * Busca serviço por ID
     * @param int $id
     * @param bool $includeDeleted - se true -> retorna mesmo serviços soft-deleted
     * @return Service|null
     */
    public function findById(int $id, bool $includeDeleted = false): ?Service {
        try {
            $sql = "SELECT * FROM services WHERE id = :id";

            if (!$includeDeleted) {
                $sql .= " AND deleted IS NULL";
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            return $data ? new Service($data) : null;

        } catch(PDOException $e) {
            error_log("Erro ao buscar serviço por ID: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Busca serviço por nome exato
     */
    public function findByName(string $name, bool $includeDeleted = false): ?Service {
        try {
            $sql = "SELECT * FROM services WHERE name = :name";

            if (!$includeDeleted) {
                $sql .= " AND deleted_at IS NULL";
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['name' => $name]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            return $data ? new Service($data) : null;

        } catch (PDOException $e) {
            error_log("Erro ao buscar serviço por nome: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Bucas serviço por categoria
     * @param string $category - 'Individual', 'Casal', 'Familiar'
     * @return Service[]
     */
    public function findByCategory(string $category, bool $activeOnly): array {
        try{
            $sql = "SELECT * FROM services WHERE category = :category AND deleted_at IS NULL";

            if ($activeOnly) {
                $sql .= " AND active = 1";
            }

            $sql .= " ORDER BY name";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['category' => $category]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn($data) => new Service($data), $results);

        } catch (PDOException $e) {
            error_log("Erro ao buscar serviços por categoria: " . $e->getMessage());
            throw $e;
        }
    }
}
?>
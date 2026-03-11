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

    /**
     * Lista todos os serviços ativos
     * @return Service[]
     */
    public function getAllActive(): array {
        try {
            $sql = "SELECT * FROM services
            WHERE deleted_at IS NULL AND
            active = 1 ORDER BY category, name";

            $stmt = $this->pdo->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn($data) => new Service($data), $results);

        } catch (PDOException $e){
            error_log("Erro ao listar serviços ativos: " . $e->getMessage());
            throw $e; 
        }
    }
    /**
     * Lista todos os serviços (incluindo os inativos)
     * @return Service[]
     */
    public function getAll(bool $includeDeleted = false): array {
        try {
            $sql = "SELECT * FROM services";

            if (!$includeDeleted) {
                $sql .= " WHERE deleted_at IS NULL";
            }

            $sql .= " ORDER BY category, name";

            $stmt = $this->pdo->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn($data) =>  new Service($data), $results);

        } catch(PDOException $e) {
            error_log("Erro ao listar serviços: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Busca serviços por faixa de preço
     * @return Service[]
     */
    public function findByPriceRange(float $minPrice, float $maxPrice, bool $activeOnly = true): array {
        try {
            $sql = "SELECT * FROM services
            WHERE price BETWEEN :min AND :max
            AND deleted_at is NULL";

            if ($activeOnly) {
                $sql .= " AND active = 1";
            }

            $sql .= " ORDER BY price";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'min' => $minPrice,
                'max' => $maxPrice
            ]);

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn($data) => new Service($data), $results);

        } catch (PDOException $e) {
            error_log("Erro ao buscar serviços por faixa de preço: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Busca serviço por duração
     * @return Service[]
     */
    public function findByDuration(int $durationMinutes, bool $activeOnly = true): array {
        try {
            $sql = "SELECT * FROM service
            WHERE duration_minutes = :duration
            AND deleted_at IS NULL";

            if ($activeOnly) {
                $sql .= " AND active = 1";
            }

            $sql .= " ORDER BY name";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['duration' => $durationMinutes]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn($data) => new Service($data), $results);

        } catch (PDOException $e) {
            error_log("Erro ao buscar serviços por duração: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * =============================================================================
     * MÉTODOS DE CRIAÇÃO (CREATE)
     * =============================================================================
     */

    /**
     * Cria um novo serviço
     * @throws DomaiException - se nome já existir
     */
    public function create(Service $service): int {

        // verifica duplicidade
        $this->CheckNameUnique($service->getName(), null);

        try {
            $sql = "INSERT INTO  services (
                    name, description, price, duration_minutes, category, active
                    ) VALUES (
                    :name, :description, :price, :duration_minutes, :category, :active
                    )";

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                'name'             => $service->getName(),
                'description'      => $service->getDeletedAt(),
                'price'            => $service->getPrice(),
                'duration_minutes' => $service->getDurationMinutes(),
                'category'         => $service->getCategory(),
                'active'           => $service->isActive() ? 1 : 0,
            ]);

            return (int) $this->pdo->lastInsertId();

        } catch(PDOException $e) {
            error_log("Erro ao criar serviço: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * =============================================================================
     * MÉTODOS DE ATUALIZAÇÃO (UPDATE)
     * =============================================================================
     */

    /**
     * Atualiza dados do serviço
     * @throws InvalidArgumentException - se serviço não existir
     * @throws DomainException - se nome já existir
     */
    public function update(Service $service): bool {
        if (!$service->getId() || !$this->findById($service->getId())) {
            throw new InvalidArgumentException(
                "Serviço com ID " . $service->getId() . " não encontrado"
            );
        }
        // verifica a duplicidade de nome (ignorando o própri registro)
        $this->checkNameUnique($service->getName(), $service->getId());

        try {
            $sql = "UPDATE services SET
                        name = :name,
                        descrption = :description,
                        price = :price,
                        duration_minutes = :duration_minutes,
                        category = :category,
                        active = :active
                    WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);

            return $stmt->execute([
                'id'               => $service->getId(),
                'name'             => $service->getName(),
                'description'      => $service->getDescription(),
                'price'            => $service->getPrice(),
                'duration_minutes' => $service->getDurationMinutes(),
                'category'         => $service->getCategory(),
                'active'           => $service->isActive() ? 1 : 0,

            ]);
        } catch(PDOException $e) {
            error_log("Erro ao atualizar serviço: " . $e->getMessage());
            throw $e;      
        }
    }

    /**
     * Atualiza apenas o preço do serviço
     */
    public function updatePrice(int $serviceId, float $NewPrice) {
        if ($NewPrice < 0) {
            throw new InvalidArgumentException("Preço não pode ser negativo");
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE services
                SET price = :price
                WHERE id = :d AND deleted_at IS NULL"
            );

            return $stmt->execute([
                'id' => $serviceId,
                'price' => $NewPrice,
            ]);

        } catch(PDOException $e) {
            error_log("Erro ao atualizar preço: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Ativa um serviço
     */
    public function activate(int $serviceId): bool {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE services
                SET active = 1
                WHERE id = :id AND deleted_at IS NULL"
            );

            return $stmt->execute(['id' => $serviceId]);

        } catch (PDOException $e) {
            error_log("Erro ao ativar serviço: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Desativa um serviço (ainda fica visível, mas não disponível pra novos agendamentos)
     */
    public function desativate(int $serviceId): bool {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE service
                SET active = 0
                WHERE id = :id AND
                deleted_at IS NULL"
            );

            return $stmt->execute(['id' => $serviceId]);

        } catch (PDOException $e) {
            error_log("Erro ao desativar serviço: " . $e->getMessage());
            throw $e;
        }
    }



    
}

?>
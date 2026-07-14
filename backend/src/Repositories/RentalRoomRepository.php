<?php
namespace App\Repositories;

use App\Models\RentalRoom;
use PDO;
use PDOException;
use InvalidArgumentException;
use DomainException;

class RentalRoomRepository {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * =============================================================================
     * MÉTODOS DE BUSCA (READ)
     * =============================================================================
     */

    public function findById(int $id, bool $includeDeleted = false): ?RentalRoom {
        try {
            $sql = "SELECT * FROM rental_rooms WHERE id = :id";

            if (!$includeDeleted) {
                $sql .= " AND deleted_at IS NULL";
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            return $data ? new RentalRoom($data) : null;

        } catch (PDOException $e) {
            error_log("Erro ao buscar sala de sublocação por ID: " . $e->getMessage());
            throw $e;
        }
    }

    public function findByName(string $name, bool $includeDeleted = false): ?RentalRoom {
        try {
            $sql = "SELECT * FROM rental_rooms WHERE name = :name";

            if (!$includeDeleted) {
                $sql .= " AND deleted_at IS NULL";
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['name' => $name]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            return $data ? new RentalRoom($data) : null;

        } catch (PDOException $e) {
            error_log("Erro ao buscar sala de sublocação por nome: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @return RentalRoom[]
     */
    public function getAllActive(): array {
        try {
            $sql = "SELECT * FROM rental_rooms WHERE deleted_at IS NULL AND active = 1 ORDER BY name";

            $stmt = $this->pdo->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn($data) => new RentalRoom($data), $results);

        } catch (PDOException $e) {
            error_log("Erro ao listar salas de sublocação ativas: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @return RentalRoom[]
     */
    public function getAll(bool $includeDeleted = false): array {
        try {
            $sql = "SELECT * FROM rental_rooms";

            if (!$includeDeleted) {
                $sql .= " WHERE deleted_at IS NULL";
            }

            $sql .= " ORDER BY name";

            $stmt = $this->pdo->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn($data) => new RentalRoom($data), $results);

        } catch (PDOException $e) {
            error_log("Erro ao listar salas de sublocação: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verifica se a sala tem reservas ativas (não canceladas/não deletadas) ou recorrências ativas
     * Usado pra bloquear a desativação de uma sala em uso
     */
    public function hasActiveBookingsOrRecurrences(int $roomId): bool {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM rental_bookings
                 WHERE rental_room_id = :room_id
                   AND status = 'scheduled'
                   AND deleted_at IS NULL
                   AND start_time >= NOW()"
            );
            $stmt->execute(['room_id' => $roomId]);
            if ((int) $stmt->fetchColumn() > 0) {
                return true;
            }

            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM rental_recurrences
                 WHERE rental_room_id = :room_id AND active = 1"
            );
            $stmt->execute(['room_id' => $roomId]);

            return (int) $stmt->fetchColumn() > 0;

        } catch (PDOException $e) {
            error_log("Erro ao verificar uso da sala de sublocação: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * =============================================================================
     * MÉTODOS DE CRIAÇÃO (CREATE)
     * =============================================================================
     */

    /**
     * @throws DomainException Se nome já existir
     */
    public function create(RentalRoom $room): int {
        $this->checkNameUnique($room->getName());

        try {
            $sql = "INSERT INTO rental_rooms (name, active) VALUES (:name, :active)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'name'   => $room->getName(),
                'active' => $room->isActive() ? 1 : 0,
            ]);

            return (int) $this->pdo->lastInsertId();

        } catch (PDOException $e) {
            error_log("Erro ao criar sala de sublocação: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * =============================================================================
     * MÉTODOS DE ATUALIZAÇÃO (UPDATE)
     * =============================================================================
     */

    /**
     * @throws InvalidArgumentException Se sala não existir
     * @throws DomainException Se nome já existir
     */
    public function update(RentalRoom $room): bool {
        if (!$room->getId() || !$this->findById($room->getId())) {
            throw new InvalidArgumentException("Sala de sublocação #{$room->getId()} não encontrada");
        }

        $this->checkNameUnique($room->getName(), $room->getId());

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE rental_rooms SET name = :name, active = :active WHERE id = :id"
            );

            return $stmt->execute([
                'id'     => $room->getId(),
                'name'   => $room->getName(),
                'active' => $room->isActive() ? 1 : 0,
            ]);

        } catch (PDOException $e) {
            error_log("Erro ao atualizar sala de sublocação: " . $e->getMessage());
            throw $e;
        }
    }

    public function activate(int $roomId): bool {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE rental_rooms SET active = 1 WHERE id = :id AND deleted_at IS NULL"
            );

            return $stmt->execute(['id' => $roomId]);

        } catch (PDOException $e) {
            error_log("Erro ao ativar sala de sublocação: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * =============================================================================
     * MÉTODOS DE DELEÇÃO (soft delete)
     * =============================================================================
     */

    public function delete(int $id): bool {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE rental_rooms SET deleted_at = NOW(), active = 0 WHERE id = :id AND deleted_at IS NULL"
            );
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                throw new InvalidArgumentException("Sala de sublocação #{$id} não encontrada ou já desativada");
            }

            return true;

        } catch (PDOException $e) {
            error_log("Erro ao desativar sala de sublocação: " . $e->getMessage());
            throw $e;
        }
    }

    public function restore(int $id): bool {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE rental_rooms SET deleted_at = NULL, active = 1 WHERE id = :id AND deleted_at IS NOT NULL"
            );
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                throw new InvalidArgumentException("Sala de sublocação #{$id} não encontrada ou não está desativada");
            }

            return true;

        } catch (PDOException $e) {
            error_log("Erro ao restaurar sala de sublocação: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * =============================================================================
     * HELPERS PRIVADOS
     * =============================================================================
     */

    private function checkNameUnique(string $name, ?int $excludeId = null): void {
        $existing = $this->findByName($name);

        if ($existing && $existing->getId() !== $excludeId) {
            throw new DomainException("Já existe uma sala de sublocação com o nome '{$name}'");
        }
    }
}

<?php
namespace App\Repositories;

use App\Models\RentalRecurrence;
use PDO;
use PDOException;
use DateTime;

class RentalRecurrenceRepository {
    private PDO $pdo;
    private UserRepository $userRepo;
    private RentalRoomRepository $roomRepo;

    public function __construct(PDO $pdo, UserRepository $userRepo, RentalRoomRepository $roomRepo) {
        $this->pdo = $pdo;
        $this->userRepo = $userRepo;
        $this->roomRepo = $roomRepo;
    }

    public function findById(int $id, bool $loadRelations = true): ?RentalRecurrence {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM rental_recurrences WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$data) {
                return null;
            }

            $recurrence = new RentalRecurrence($data);

            if ($loadRelations) {
                $this->loadRelations($recurrence);
            }

            return $recurrence;

        } catch (PDOException $e) {
            error_log("Erro ao buscar recorrência de sublocação: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @return RentalRecurrence[]
     */
    public function getAllActive(): array {
        try {
            $stmt = $this->pdo->query("SELECT * FROM rental_recurrences WHERE active = 1 ORDER BY id DESC");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->hydrate($results);

        } catch (PDOException $e) {
            error_log("Erro ao listar recorrências de sublocação ativas: " . $e->getMessage());
            throw $e;
        }
    }

    public function create(
        int $tenantUserId,
        int $rentalRoomId,
        string $period,
        int $dayOfWeek,
        DateTime $startDate,
        ?DateTime $endDate,
        float $price
    ): int {
        try {
            $sql = "INSERT INTO rental_recurrences
                    (tenant_user_id, rental_room_id, period, day_of_week, start_date, end_date, price)
                    VALUES (:tenant_user_id, :rental_room_id, :period, :day_of_week, :start_date, :end_date, :price)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'tenant_user_id' => $tenantUserId,
                'rental_room_id' => $rentalRoomId,
                'period'         => $period,
                'day_of_week'    => $dayOfWeek,
                'start_date'     => $startDate->format('Y-m-d'),
                'end_date'       => $endDate?->format('Y-m-d'),
                'price'          => $price,
            ]);

            return (int) $this->pdo->lastInsertId();

        } catch (PDOException $e) {
            error_log("Erro ao criar recorrência de sublocação: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Encerra a recorrência a partir de uma data (liberação manual pelo admin)
     */
    public function deactivate(int $id, DateTime $fromDate): bool {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE rental_recurrences
                 SET active = 0, end_date = :end_date
                 WHERE id = :id AND active = 1"
            );

            $stmt->execute([
                'id'       => $id,
                'end_date' => (clone $fromDate)->modify('-1 day')->format('Y-m-d'),
            ]);

            return $stmt->rowCount() > 0;

        } catch (PDOException $e) {
            error_log("Erro ao encerrar recorrência de sublocação: " . $e->getMessage());
            throw $e;
        }
    }

    private function loadRelations(RentalRecurrence $recurrence): void {
        $tenant = $this->userRepo->findById($recurrence->getTenantUserId());
        if ($tenant) {
            $recurrence->setTenant($tenant);
        }

        $room = $this->roomRepo->findById($recurrence->getRentalRoomId());
        if ($room) {
            $recurrence->setRoom($room);
        }
    }

    private function hydrate(array $results): array {
        $recurrences = array_map(fn($data) => new RentalRecurrence($data), $results);

        foreach ($recurrences as $recurrence) {
            $this->loadRelations($recurrence);
        }

        return $recurrences;
    }
}

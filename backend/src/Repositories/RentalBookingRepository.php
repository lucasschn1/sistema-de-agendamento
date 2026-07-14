<?php
namespace App\Repositories;

use App\Models\RentalBooking;
use PDO;
use PDOException;
use DateTime;
use DomainException;

class RentalBookingRepository {
    private PDO $pdo;
    private UserRepository $userRepo;
    private RentalRoomRepository $roomRepo;

    public function __construct(PDO $pdo, UserRepository $userRepo, RentalRoomRepository $roomRepo) {
        $this->pdo = $pdo;
        $this->userRepo = $userRepo;
        $this->roomRepo = $roomRepo;
    }

    /**
     * =======================================================================
     * MÉTODOS DE BUSCA (READ)
     * =======================================================================
     */

    public function findById(int $id, bool $loadRelations = true, bool $includeDeleted = false): ?RentalBooking {
        try {
            $sql = "SELECT * FROM rental_bookings WHERE id = :id";

            if (!$includeDeleted) {
                $sql .= " AND deleted_at IS NULL";
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$data) {
                return null;
            }

            $booking = new RentalBooking($data);

            if ($loadRelations) {
                $this->loadRelations($booking);
            }

            return $booking;

        } catch (PDOException $e) {
            error_log("Erro ao buscar reserva de sublocação por ID: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Busca reservas num intervalo de datas (visão de calendário/listagem)
     * @return RentalBooking[]
     */
    public function findByDateRange(DateTime $startDate, DateTime $endDate, bool $loadRelations = true): array {
        try {
            $sql = "SELECT * FROM rental_bookings
                    WHERE booking_date BETWEEN :start_date AND :end_date
                    AND deleted_at IS NULL
                    ORDER BY start_time";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'start_date' => $startDate->format('Y-m-d'),
                'end_date'   => $endDate->format('Y-m-d'),
            ]);

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->hydrateBookings($results, $loadRelations);

        } catch (PDOException $e) {
            error_log("Erro ao buscar reservas de sublocação por período: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Última data de reserva já gerada para uma recorrência — usado pra saber
     * até onde já foi gerado e "abastecer" mais semanas de recorrências sem end_date
     */
    public function findMaxBookingDateForRecurrence(int $recurrenceId): ?DateTime {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT MAX(booking_date) FROM rental_bookings WHERE rental_recurrence_id = :recurrence_id"
            );
            $stmt->execute(['recurrence_id' => $recurrenceId]);
            $max = $stmt->fetchColumn();

            return $max ? new DateTime($max) : null;

        } catch (PDOException $e) {
            error_log("Erro ao buscar última data de reserva da recorrência: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reservas não recorrentes (avulsas) ainda não faturadas dentro de um mês, agrupáveis por tenant
     * Inclui tanto o horário de exceção (período 'avulso') quanto reservas esporádicas
     * de um bloco completo (manhã/tarde/noite) sem recorrência — ambas são cobradas só no fim do mês
     * Usado pelo fechamento mensal de faturas
     * @return RentalBooking[]
     */
    public function findUnbilledAvulsoByMonth(DateTime $monthStart, DateTime $monthEnd): array {
        try {
            $sql = "SELECT * FROM rental_bookings
                    WHERE is_recurring = 0
                    AND rental_invoice_id IS NULL
                    AND status = 'scheduled'
                    AND deleted_at IS NULL
                    AND booking_date BETWEEN :start_date AND :end_date
                    ORDER BY tenant_user_id, start_time";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'start_date' => $monthStart->format('Y-m-d'),
                'end_date'   => $monthEnd->format('Y-m-d'),
            ]);

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->hydrateBookings($results, false);

        } catch (PDOException $e) {
            error_log("Erro ao buscar avulsos não faturados: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * =======================================================================
     * MÉTODOS DE CRIAÇÃO (CREATE)
     * =======================================================================
     */

    public function create(
        int $rentalRoomId,
        int $tenantUserId,
        DateTime $bookingDate,
        string $period,
        DateTime $startTime,
        DateTime $endTime,
        float $price,
        bool $isRecurring = false,
        ?int $rentalRecurrenceId = null
    ): int {
        try {
            $sql = "INSERT INTO rental_bookings
                    (rental_room_id, tenant_user_id, rental_recurrence_id, is_recurring,
                     booking_date, period, start_time, end_time, price)
                    VALUES
                    (:rental_room_id, :tenant_user_id, :rental_recurrence_id, :is_recurring,
                     :booking_date, :period, :start_time, :end_time, :price)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'rental_room_id'       => $rentalRoomId,
                'tenant_user_id'       => $tenantUserId,
                'rental_recurrence_id' => $rentalRecurrenceId,
                'is_recurring'         => $isRecurring ? 1 : 0,
                'booking_date'         => $bookingDate->format('Y-m-d'),
                'period'               => $period,
                'start_time'           => $startTime->format('Y-m-d H:i:s'),
                'end_time'             => $endTime->format('Y-m-d H:i:s'),
                'price'                => $price,
            ]);

            return (int) $this->pdo->lastInsertId();

        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Conflito de horário')) {
                throw new DomainException('Conflito de horário: sala já reservada neste período');
            }

            if (str_contains($e->getMessage(), 'não podem ser recorrentes')) {
                throw new DomainException('Reservas avulsas não podem ser recorrentes');
            }

            error_log("Erro ao criar reserva de sublocação: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * =======================================================================
     * MÉTODOS DE ATUALIZAÇÃO (UPDATE)
     * =======================================================================
     */

    public function cancel(int $bookingId, ?string $reason = null): bool {
        try {
            $sql = "UPDATE rental_bookings
                    SET status = 'cancelled', cancellation_reason = :reason
                    WHERE id = :id
                    AND status = 'scheduled'
                    AND deleted_at IS NULL";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $bookingId, 'reason' => $reason]);

            if ($stmt->rowCount() === 0) {
                throw new DomainException('Não é possível cancelar: reserva não encontrada ou já cancelada');
            }

            return true;

        } catch (PDOException $e) {
            error_log("Erro ao cancelar reserva de sublocação: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cancela todas as reservas futuras de uma recorrência a partir de uma data
     * (usado na liberação manual do admin, ex: inadimplência)
     *
     * @return int Número de reservas canceladas
     */
    public function cancelFutureByRecurrence(int $recurrenceId, DateTime $fromDate, ?string $reason = null): int {
        try {
            $sql = "UPDATE rental_bookings
                    SET status = 'cancelled', cancellation_reason = :reason
                    WHERE rental_recurrence_id = :recurrence_id
                      AND booking_date >= :from_date
                      AND status = 'scheduled'
                      AND deleted_at IS NULL";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'recurrence_id' => $recurrenceId,
                'from_date'     => $fromDate->format('Y-m-d'),
                'reason'        => $reason,
            ]);

            return $stmt->rowCount();

        } catch (PDOException $e) {
            error_log("Erro ao cancelar reservas futuras da recorrência: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Marca um lote de reservas avulsas como faturadas (fechamento mensal)
     */
    public function markAsBilled(array $bookingIds, int $invoiceId): void {
        if (empty($bookingIds)) {
            return;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
            $sql = "UPDATE rental_bookings SET rental_invoice_id = ? WHERE id IN ({$placeholders})";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge([$invoiceId], $bookingIds));

        } catch (PDOException $e) {
            error_log("Erro ao marcar reservas como faturadas: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * =======================================================================
     * MÉTODOS PRIVADOS - HIDRATAÇÃO
     * =======================================================================
     */

    private function loadRelations(RentalBooking $booking): void {
        $room = $this->roomRepo->findById($booking->getRentalRoomId());
        if ($room) {
            $booking->setRoom($room);
        }

        $tenant = $this->userRepo->findById($booking->getTenantUserId());
        if ($tenant) {
            $booking->setTenant($tenant);
        }
    }

    private function hydrateBookings(array $results, bool $loadRelations): array {
        $bookings = array_map(fn($data) => new RentalBooking($data), $results);

        if ($loadRelations) {
            foreach ($bookings as $booking) {
                $this->loadRelations($booking);
            }
        }

        return $bookings;
    }
}

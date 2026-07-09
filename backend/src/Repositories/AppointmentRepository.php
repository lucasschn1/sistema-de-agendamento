<?php
namespace App\Repositories;

use App\Models\Appointment;
use App\Models\User;
use App\Models\Service;
use PDO;
use PDOException;
use DateTime;
use InvalidArgumentException;
use DomainException;

class AppointmentRepository {
    private PDO $pdo;
    private UserRepository $userRepo;
    private ServiceRepository $serviceRepo;

    public function __construct(PDO $pdo, UserRepository $userRepo, ServiceRepository $serviceRepo) {
        $this->pdo = $pdo;
        $this->userRepo = $userRepo;
        $this->serviceRepo = $serviceRepo;
    }

    /**
     * =======================================================================
     * MÉTODOS DE BUSCA (READ)
     * =======================================================================
     */

    
    /**
     * Busca agendamentos por ID
     * @param int $id
     * @param bool $loadRelations - se true carrega User e Service nos objetos
     * @param bool $includeDeleted - se true retorne mesmo agendamentos soft deleted
     * @return Appointment|null
     */
    public function findById(int $id, bool $loadRelations = true, bool $includeDeleted = false): ?Appointment {
        try {
            $sql = "SELECT * FROM appointments WHERE id = :id";

            if (!$includeDeleted) {
                $sql .= " AND deleted_at IS NULL";
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$data) {
                return null;
            }

            $appointment = new Appointment($data);

            if ($loadRelations) {
                $this->loadRelations($appointment);           
            }

            return $appointment;

        } catch (PDOException $e) {
            error_log("Erro ao buscar agendamento por ID: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Busca agendamento de um paciente
     * @return Appointment[]
     */
    public function findByPatient(int $patientId, bool $loadRelations = true): array {
        try {
            $sql = "SELECT * FROM appointments
                    WHERE patient_id = :patient_id
                    AND deleted_at IS NULL
                    ORDER BY start_time DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['patient_id' => $patientId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->hydrateAppointments($results, $loadRelations);

        } catch (PDOException $e) {
            error_log("Erro ao buscar agendamentos por paciente: " . $e->getMessage());
            throw $e;
        }
    }

    public function findByProfessional(int $professionalId, bool $loadRelations = true) : array {
        try {
            $sql = "SELECT * FROM appointments
                    WHERE professional_id = :professional_id
                    AND deleted_at IS NULL
                    ORDER by start_time DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['professional_id' => $professionalId]);       
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->hydrateAppointments($results, $loadRelations);

        } catch (PDOException $e) {
            error_log("Erro ao buscar agendamentos por profissional: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Busca agendamentos por periodo
     * @param DateTime $startDate (data inicial)
     * @param DateTime $endDate (data final)
     * @return Appointment[]
     */
    public function findByDateRange(DateTime $startDate, DateTime $endDate, bool $loadRelations = true): array {
        try {
            $sql = "SELECT * FROM appointments
                    WHERE start_time >= :start_date
                    AND start_time < :end_date
                    AND deleted_at IS NULL
                    ORDER BY start_time";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'start_date' => $startDate->format('Y-m-d 00:00:0'),
                'end_date' => $endDate->modify('+1 day')->format('Y-m-d 00:00:0'),
            ]);

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->hydrateAppointments($results, $loadRelations);

        } catch (PDOException $e) {
            error_log("Erro ao buscar agendamento por período: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Busca agendamentos de profissional em um dia especifico
     * @return Appointment[]
     */
    public function findByProfessionalAndDate(int $professionalId, DateTime $date, bool $loadRelations = true): array {
        try {
            $sql = "SELECT * FROM appointments
                    WHERE professional_id = :professional_id
                    AND DATE(start_time) = :date
                    AND deleted_at IS NULL
                    ORDER BY start_time";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'professional_id' => $professionalId,
                'date' => $date->format('Y-m-d'),
            ]);

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->hydrateAppointments($results, $loadRelations);
        } catch(PDOException $e) {
            error_log("Erro ao buscar agendamentos por profissional e data: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Busca agendamentos por status
     * @param string $status 'schedule', 'confirmed', 'completed', 'cancelled', 'no-show'
     * @return Appointment[]
     */
    public function findByStatus(string $status, bool $loadRelations):array {
        try {
            $sql = "SELECT * FROM appointments
                    WHERE status = :status
                    AND deleted_at IS NULL
                    ORDER BY start_time DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['status' => $status]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->hydrateAppointments($results, $loadRelations);

        } catch(PDOException $e) {
            error_log("Erro ao buscar agendamentos por status: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Busca agendamentos em um grupo de recorrência
     * @return Appointment[]
     */
    public function findByRecurrenceGroup(int $recurrenceGroup, bool $loadRelations = true): array {
        try {
            $sql = "SELECT * FROM appointments
                    WHERE recurrence_group_id = :group_id
                    AND deleted_at IS NULL
                    ORDER BY start_time";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['group_id' => $recurrenceGroup]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->hydrateAppointments($results, $loadRelations);

        } catch (PDOException $e) {
            error_log("Erro ao buscar agendamentos por grupo de recorreência: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Busca próximo agendamento (futuros e não cancelados)
     * @return Appointment[]
     */
    public function getUpcoming(int $limit = 50, bool $loadRelations = true): array {
        try {
            $sql = "SELECT * FROM appointments
                    WHERE start_time >= NOW()
                    AND status IN ('scheduled', 'confirmed')
                    AND deleted_at IS NULL
                    ORDER BY start_time
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit , PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->hydrateAppointments($results, $loadRelations);

        } catch(PDOException $e) {
            error_log("Erro ao bucar próximos agendamentos: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Busca agendamentos -NÃO- pagos (completados mas sem pagamentos)
     * @return Appointment[]
     */
    public function getUnpaid(bool $loadRelations = true): array {
        try {
            $sql = "SELECT * FROM appointments
                    WHERE paid = 0
                    AND status = 'completed'
                    AND deleted_at IS NULL
                    ORDER BY start_time";
            
            $stmt = $this->pdo->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->hydrateAppointments($results, $loadRelations);

        } catch (PDOException $e) {
            error_log("Erro ao buscar agendamentos não pagos: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * =======================================================================
     * MÉTODOS DE CRIAÇÃO (CREATE)
     * =======================================================================
     */

    /**
     * Cria a recorrência usando a stored procedure do banco
     * A procedure cria automaticamente todos os agendamentos do período
     * 
     * @param string $type 'semanal' ou 'quinzenal'
     * @param int $dayOfWeek 0=Domingo, 1=Segunda ... 6=Sábado
     * @return array ['recurrence_group_id' => int, 'sessoes_criadas' => int]
     */
    public function createRecurrence(
        int $patientId,
        int $professionalId,
        int $serviceId,
        string $type,
        int $dayOfWeek,
        string $startHour, //HH:MM:SS
        DateTime $startDate,
        ?DateTime $endDate = null,
        ?string $notes = null
    ): array {
        try {
            $stmt = $this->pdo->prepare("CALL sp_create_recurrence(?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $patientId,
                $professionalId,
                $serviceId,
                $type,
                $dayOfWeek,
                $startHour,
                $startDate->format('Y-m-d'),
                $endDate?->format('Y-m-d'),
                $notes
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'recurrence_group_id' => (int) $result['recurrence_group_id'],
                'sessoes_criadas' => (int) $result['sessoes_criadas']
            ];
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Conflito de horário')) {
                throw new DomainException(
                    "Horário indisponível para recorrência: conflito detectado em uma ou mais datas"
                );
            }

            if (str_contains($e->getMessage(), 'Serviço não encontrado')) {
                throw new InvalidArgumentException("Serviço não encontrado ou inativo");
            }

            error_log("Erro ao criar recorrêcia: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cria agendamento único usando a stored procedure do banco
     * a procedure busca automaticamente preço e duração do serviço
     */
    public function createUnique(
        int $patientId,
        int $professionalId,
        int $serviceId,
        DateTime $startTime,
        ?string $notes = null
    ): int {
        try {
            $stmt = $this->pdo->prepare("CALL sp_create_appointment(?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $patientId,
                $professionalId,
                $serviceId,
                $startTime->format('Y-m-d H:i:s'),
                $notes
            ]);
 
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) $result['appointment_id'];
 
        } catch (PDOException $e) {
            // Trigger de conflito de horário
            if (str_contains($e->getMessage(), 'Conflito de horário')) {
                throw new DomainException(
                    "Horário indisponível: o profissional já possui outro agendamento neste período"
                );
            }
 
            if (str_contains($e->getMessage(), 'Serviço não encontrado')) {
                throw new InvalidArgumentException("Serviço não encontrado ou inativo");
            }
 
            error_log("Erro ao criar agendamento: " . $e->getMessage());
            throw $e;
        }
    }

     /**
     * =======================================================================
     * MÉTODOS DE ATUALIZAÇÃO (UPDATE)
     * =======================================================================
     */

     /**
     * Atualiza dados do agendamento
     * NOTA: Não atualiza status, paid, ou payment - use métodos específicos
     */

     public function update(Appointment $appointment): bool {
        if (!$appointment->getId() || !$this->findById($appointment->getId(), false)) {
            throw new InvalidArgumentException(
                "Agendamento com ID " . $appointment->getId() . " não encontrado"
            );
        }

        try {
            $sql = "UPDATE appointments SET
                    patient_id = :patient_id,
                    professional_id = :professional_id,
                    service_id = :service_id,
                    start_time = :start_time,
                    duration_minutes = :duration_minutes,
                    price = :price,
                    notes = :notes
                    WHERE id = :id
                    AND deleted_at IS NULL";

            $stmt = $this->pdo->prepare($sql);

            return $stmt->execute([
                'id' => $appointment->getId(),
                'patient_id' => $appointment->getPatientId(),
                'professional_id' => $appointment->getProfessionalId(),
                'service_id' => $appointment->getServiceId(),
                'start_time' => $appointment->getStartTime()->format('Y-m-d H:i:s'),
                'duration_minutes' => $appointment->getDurationMinutes(),
                'price' => $appointment->getPrice(),
                'notes' => $appointment->getNotes(),
            ]);

        } catch (PDOException $e) {
            // Trigger de conflito de horário
            if (str_contains($e->getMessage(), 'Conflito de horário')) {
                throw new DomainException(
                    "Horário indisponível: o profissional já possui outro agendamento nesse período"
                );
            }

            error_log("Erro ao atualizar agendamento: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Confirma um agendamento
     */
    public function confirm(int $appointmentId): bool {
        return $this->updateStatus($appointmentId, 'confirmed');
    }

    /**
     * Marca como completado
     */
    public function complete(int $appointmentId): bool {
        return $this->updateStatus($appointmentId, 'completed');
    }

    /**
     * Cancela um agendamento
     */
    public function cancel(int $appointmentId, ?string $reason = null): bool {
        try {
            $sql = "UPDATE appointments
                    SET status = 'cancelled', cancellation_reason = :reason
                    WHERE id = :id
                    AND status IN ('scheduled', 'confirm')
                    AND deleted_at IS NULL";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id' => $appointmentId,
                'reason' => $reason
            ]);

            if ($stmt->rowCount() === 0) {
                throw new DomainException(
                    "Não é possível cancelar: agendamento não encontrado ou status inválido"
                );
            }

            return true;

        } catch (PDOException $e) {
            error_log("Erro ao cancelar agendamento: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Marca - no-show (paciente não apareceu)
     */
    public function markAsNoShow(int $appointmentId, ?string $reason = null) : bool {
        try {
            $sql = "UPDATE appointments
            SET status = 'no_show', cancellation_reason = :reason
            WHERE id = :id
            AND status IN ('scheduled', 'confirmed')
            AND deleted_at IS NULL";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $appointmentId,
            'reason' => $reason ?? 'Paciente não compareceu',
        ]);

        if ($stmt->rowCount() === 0) {
            throw new DomainException(
                "Não é possível marcar como no-show: agendamento não encontrado ou status inválido"
            );
        }

        return true;

        } catch (PDOException $e) {
            error_log("Erro ao marcar como no-show: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Registra pagementos usando a stored procedure
     */
    function registerPayment(int $appointmentId, string $paymentMethod, ?DateTime $paymentDate = null): bool {
        try {
            $stmt = $this->pdo->prepare("CALL sp_register_payment(?,?,?)");

            $stmt->execute([
                $appointmentId,
                $paymentMethod,
                $paymentDate ? $paymentDate->format('Y-m-d') : date("Y-m-d")
            ]);

            return true;

        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'status inválido para pagamento')) {
                throw new DomainException(
                    "Não é possível registrar pagamento: status do agendamento não permite"
                );
            }

            error_log("Erro ao registrat pagamento: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Desfaz o pagamento de um agendamento (estorno/correção)
     * Limpa paid, payment_method e payment_date do registro
     */
    public function undoPayment(int $appointmentId): bool {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE appointments
                 SET paid           = 0,
                     payment_method = NULL,
                     payment_date   = NULL
                 WHERE id = :id
                   AND paid = 1
                   AND deleted_at IS NULL"
            );
 
            $stmt->execute(['id' => $appointmentId]);
 
            if ($stmt->rowCount() === 0) {
                throw new DomainException(
                    "Não foi possível desfazer o pagamento: agendamento não encontrado, " .
                    "não está pago ou já foi deletado"
                );
            }
 
            return true;
 
        } catch (PDOException $e) {
            error_log("Erro ao desfazer pagamento #{$appointmentId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cancela recorrência a partir de uma data usando stored procedure
     */
    public function cancelRecurrence(int $recurrenceGroupId, DateTime $fromDate, ?string $reason = null): int {
        try {
            $stmt = $this->pdo->prepare("CALL sp_cancel_recurrence(?,?,?)");

            $stmt->execute([
                $recurrenceGroupId,
                $fromDate->format('Y-m-d'),
                $reason
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) $result['sessoes_canceladas'];

        } catch(PDOException $e) {
            error_log("Erro ao cancelar recorrência: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Atualiza status do agendamento (método privado genérico)
     */
    private function updateStatus(int $appointmentId, string $newStatus): bool {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE appointments
                SET status = :status
                WHERE id = :id AND deleted_at IS NULL"
            );

            return $stmt->execute([
                'id' => $appointmentId,
                'status' => $newStatus
            ]);

        } catch (PDOException $e) {
            error_log("Erro ao atualizar status: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * =======================================================================
     * MÉTODOS DE DELEÇÃO (SOFT DELETE)
     * =======================================================================
     */

    /**
     * Soft delete: marca agendamento como deletado
     */
    public function delete(int $id): bool {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE appointments
                SET deleted_at = NOW()
                WHERE id = :id AND deleted_at IS NULL"
            );

            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                throw new InvalidArgumentException(
                    "Agendamento com ID {$id} não encontrado ou já deletado"
                );
            }

            return true;

        } catch(PDOException $e) {
            error_log("Erro ao deletar agendamento: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Restaura um agendamento soft-deleted
     */
    public function restore(int $id): bool {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE appointments
                SET deleted_at = NULL
                WHERE id = :id AND deleted_at IS NOT NULL"
            );

            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                throw new InvalidArgumentException(
                    "Agendamento não encontrado ou não está deletado"
                );
            }

            return true;

        } catch (PDOException $e) {
            error_log("Erro ao restaurar agendamento: " . $e->getMessage());
            throw $e;
        }
    }

     /**
     * =======================================================================
     * MÉTODOS AUXILIARES / VALIDAÇÕES
     * =======================================================================
     */

    /**
     * Verifica se um horário está disponível para o profissional
     */
    public function isTimeSlotAvailable(
        int $professionalId,
        DateTime $startTime,
        int $durationMinutes,
        ?int $excludeAppoitmentId = null
    ): bool {
        try {
            $endTime = (clone $startTime)->modify("+{$durationMinutes} minutes");

            $sql = "SELECT COUNT(*) FROM appointments
                    WHERE professional_id = :professional_id
                    AND status NOT IN ('cancelled', 'no-show')
                    AND deleted_at IS NULL
                    AND start_time < :end_time
                    AND end_time > :start_time";

            if ($excludeAppoitmentId) {
                $sql .= " AND id != :exclude_id";
            }

            $stmt = $this->pdo->prepare($sql);


            $params = [
                'professional_id' => $professionalId,
                'start_time'      => $startTime->format('Y-m-d H:i:s'),
                'end_time'        => $endTime->format('Y-m-d H:i:s'),
            ];


            if ($excludeAppoitmentId) {
                $params['exclude_id'] = $excludeAppoitmentId;
            }

            $stmt->execute($params);

            return (int) $stmt->fetchColumn() === 0;
        } catch (PDOException $e) {
            error_log("Erro ao verificar disponibilidade: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Conta agendamentos por status
     */
    public function countByStatus(string $status): int {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM appointments
                WHERE status = :status AND deleted_at IS NULL"
            );

            $stmt->execute([
                'status' => $status
            ]);

            return (int) $stmt->fetchColumn();

        } catch (PDOException $e) {
            error_log("Erro ao contar agendamentos: " . $e->getMessage());
            return 0;
        }
    }
    

    /**
     * =======================================================================
     * MÉTODOS PRIVADOS - HIDRATAÇÃO
     * =======================================================================
     */

    /**
     * Carrega relacionamentos (User e Service) em um Appointment
     */
    private function loadRelations(Appointment $appointment): void {
        $patient = $this->userRepo->findById($appointment->getPatientId());
        if ($patient) {
            $appointment->setPatient($patient);
        }

        $professional = $this->userRepo->findById($appointment->getProfessionalId());
        if ($professional) {
            $appointment->setProfessional($professional);
        }

        $service = $this->serviceRepo->findById($appointment->getServiceId());
        if ($service) {
            $appointment->setService($service);
        }
    }

    /**
     * Converte array de dados do banco em array de Appointment objects
     */
    private function hydrateAppointments(array $results, bool $loadRelations): array {
        $appointments = array_map(fn($data) => new Appointment($data), $results);

        if ($loadRelations) {
            foreach ($appointments as $appointment) {
                $this->loadRelations($appointment);
            }
        }

        return $appointments;

    }
}
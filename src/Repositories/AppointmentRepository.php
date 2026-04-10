<?php
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

            return $this->hydrateAppointment($results, $loadRelations);

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
     * @return Appointmet[]
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
    public function findByProfessionalDate(int $professionalId, DateTime $date, bool $loadrelations = true): array {
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

            return $this->hydrateAppointmet($results, $loadRelations);
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

            return $this->hydrateAppointment($results, $loadRelations);

        } catch(PDOException $e) {
            error_log("Erro ao buscar agendamentos por status: " . $e->getMessage());
            throw $e;
        }
    }

}
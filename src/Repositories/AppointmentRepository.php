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

}
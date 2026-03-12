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

            $appointment= new Appointment($data);

            if ($loadRelations) {
                $this->loadRelations($appointment);           
            }

            return $appointment;

        } catch (PDOException $e) {
            error_log("Erro ao buscar agendamento por ID: " . $e->getMessage());
            throw $e;
        }
    }


}
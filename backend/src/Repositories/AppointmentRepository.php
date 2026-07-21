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

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->hydrateAppointments($results, $loadRelations);

        } catch (PDOException $e) {
            error_log("Erro ao buscar agendamentos não pagos: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Constrói a cláusula WHERE + bindings compartilhada por findPaidFiltered,
     * countPaidFiltered e getPaidAggregates — filtros opcionais são anexados
     * condicionalmente para que os três métodos nunca divirjam sobre o que
     * conta como "pago no período" (fonte única de verdade para o Financeiro)
     *
     * @param array{professional_id?:?int,patient_id?:?int,service_id?:?int,method?:?string,search?:?string} $filters
     * @return array{0:string,1:string,2:array} [joinSql, whereSql, bindings]
     */
    private function buildPaidFilterClause(DateTime $startDate, DateTime $endDate, array $filters = []): array {
        $joinSql = '';
        $whereSql = "WHERE a.paid = 1
                      AND a.deleted_at IS NULL
                      AND a.payment_date >= :start_date
                      AND a.payment_date <= :end_date";

        $bindings = [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date'   => $endDate->format('Y-m-d'),
        ];

        if (!empty($filters['professional_id'])) {
            $whereSql .= " AND a.professional_id = :professional_id";
            $bindings['professional_id'] = (int) $filters['professional_id'];
        }

        if (!empty($filters['patient_id'])) {
            $whereSql .= " AND a.patient_id = :patient_id";
            $bindings['patient_id'] = (int) $filters['patient_id'];
        }

        if (!empty($filters['service_id'])) {
            $whereSql .= " AND a.service_id = :service_id";
            $bindings['service_id'] = (int) $filters['service_id'];
        }

        if (!empty($filters['method'])) {
            $whereSql .= " AND a.payment_method = :method";
            $bindings['method'] = $filters['method'];
        }

        if (!empty($filters['search'])) {
            $joinSql = "LEFT JOIN users up ON up.id = a.patient_id
                        LEFT JOIN users uf ON uf.id = a.professional_id
                        LEFT JOIN services s ON s.id = a.service_id";
            // cada ocorrência precisa de seu próprio placeholder — PDO não aceita
            // o mesmo nome de parâmetro nomeado repetido com prepares reais
            $whereSql .= " AND (up.name LIKE :search1 OR uf.name LIKE :search2 OR s.name LIKE :search3)";
            $searchTerm = '%' . $filters['search'] . '%';
            $bindings['search1'] = $searchTerm;
            $bindings['search2'] = $searchTerm;
            $bindings['search3'] = $searchTerm;
        }

        return [$joinSql, $whereSql, $bindings];
    }

    /**
     * Busca pagamentos registrados em um período (competência = payment_date),
     * com filtros opcionais combinados — usado pelo Histórico de Pagamentos
     *
     * @return Appointment[]
     */
    public function findPaidFiltered(
        DateTime $startDate,
        DateTime $endDate,
        array $filters = [],
        int $limit = 20,
        int $offset = 0,
        bool $loadRelations = true
    ): array {
        try {
            [$joinSql, $whereSql, $bindings] = $this->buildPaidFilterClause($startDate, $endDate, $filters);

            $sql = "SELECT a.* FROM appointments a
                    {$joinSql}
                    {$whereSql}
                    ORDER BY a.payment_date DESC, a.start_time DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->pdo->prepare($sql);
            foreach ($bindings as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->hydrateAppointments($results, $loadRelations);

        } catch (PDOException $e) {
            error_log("Erro ao buscar pagamentos filtrados: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Conta pagamentos de um período com os mesmos filtros de findPaidFiltered
     * (paginação do Histórico de Pagamentos)
     */
    public function countPaidFiltered(DateTime $startDate, DateTime $endDate, array $filters = []): int {
        try {
            [$joinSql, $whereSql, $bindings] = $this->buildPaidFilterClause($startDate, $endDate, $filters);

            $sql = "SELECT COUNT(*) FROM appointments a {$joinSql} {$whereSql}";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindings);

            return (int) $stmt->fetchColumn();

        } catch (PDOException $e) {
            error_log("Erro ao contar pagamentos filtrados: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Agrega em SQL (não em loop PHP) os números do resumo de um período de
     * pagamentos — receita total, quantidade de pagamentos, pacientes e
     * profissionais distintos atendidos. Usa a mesma cláusula de filtro de
     * findPaidFiltered/countPaidFiltered para nunca divergir do que aparece
     * na listagem correspondente.
     *
     * @return array{total_revenue:float,payment_count:int,patient_count:int,professional_count:int}
     */
    public function getPaidAggregates(DateTime $startDate, DateTime $endDate, array $filters = []): array {
        try {
            [$joinSql, $whereSql, $bindings] = $this->buildPaidFilterClause($startDate, $endDate, $filters);

            $sql = "SELECT
                        COALESCE(SUM(a.price), 0) AS total_revenue,
                        COUNT(*) AS payment_count,
                        COUNT(DISTINCT a.patient_id) AS patient_count,
                        COUNT(DISTINCT a.professional_id) AS professional_count
                    FROM appointments a {$joinSql} {$whereSql}";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindings);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total_revenue'      => (float) $row['total_revenue'],
                'payment_count'      => (int) $row['payment_count'],
                'patient_count'      => (int) $row['patient_count'],
                'professional_count' => (int) $row['professional_count'],
            ];

        } catch (PDOException $e) {
            error_log("Erro ao agregar pagamentos: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Receita paga (payment_date no período) agrupada por método de pagamento
     * — mesma cláusula de filtro de getPaidAggregates, para nunca divergir do
     * "recebido" total do mesmo período
     *
     * @return array<string,float> ['PIX' => 750.0, 'Dinheiro' => 220.0, ...]
     */
    public function getPaidRevenueByMethod(DateTime $startDate, DateTime $endDate): array {
        try {
            [$joinSql, $whereSql, $bindings] = $this->buildPaidFilterClause($startDate, $endDate);

            $sql = "SELECT a.payment_method AS method, COALESCE(SUM(a.price), 0) AS total
                    FROM appointments a {$joinSql} {$whereSql}
                    GROUP BY a.payment_method";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindings);

            $result = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $result[$row['method'] ?? 'Indefinido'] = (float) $row['total'];
            }

            return $result;

        } catch (PDOException $e) {
            error_log("Erro ao agregar pagamentos por método: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Receita paga (payment_date no período) agrupada por profissional —
     * mesma cláusula de filtro de getPaidAggregates, para que a soma de todos
     * os profissionais bata exatamente com o "recebido" total do período
     *
     * @return array<int,array{received:float,payment_count:int}> indexado por professional_id
     */
    public function getPaidRevenueByProfessional(DateTime $startDate, DateTime $endDate): array {
        try {
            [, $whereSql, $bindings] = $this->buildPaidFilterClause($startDate, $endDate);

            // join direto por nome do profissional, mesmo quando ele não tem
            // nenhuma sessão com start_time no período (só pagamento registrado
            // nele) — evita uma segunda consulta só para resolver o nome
            $sql = "SELECT a.professional_id, uf.name AS professional_name,
                        COALESCE(SUM(a.price), 0) AS received, COUNT(*) AS payment_count
                    FROM appointments a
                    LEFT JOIN users uf ON uf.id = a.professional_id
                    {$whereSql}
                    GROUP BY a.professional_id, uf.name";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindings);

            $result = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $result[(int) $row['professional_id']] = [
                    'professional_name' => $row['professional_name'] ?? 'Desconhecido',
                    'received'          => (float) $row['received'],
                    'payment_count'     => (int) $row['payment_count'],
                ];
            }

            return $result;

        } catch (PDOException $e) {
            error_log("Erro ao agregar pagamentos por profissional: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Soma histórica de tudo que já foi recebido (todos os meses), sem passar
     * pelo limite de 365 dias do validador de período — usado pelo KPI
     * "Total recebido" do Resumo Financeiro
     */
    public function sumAllPaidRevenue(): float {
        try {
            $sql = "SELECT COALESCE(SUM(price), 0) FROM appointments WHERE paid = 1 AND deleted_at IS NULL";
            $stmt = $this->pdo->query($sql);
            return (float) $stmt->fetchColumn();

        } catch (PDOException $e) {
            error_log("Erro ao somar receita total recebida: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Busca agendamentos pagos, paginado e ordenado do mais recente pro mais antigo
     * Usa o índice idx_payment (paid, payment_date) — não carrega tudo em memória
     *
     * @return Appointment[]
     */
    public function findPaidPaginated(int $limit = 10, int $offset = 0, bool $loadRelations = true): array {
        try {
            $sql = "SELECT * FROM appointments
                    WHERE paid = 1
                    AND deleted_at IS NULL
                    ORDER BY payment_date DESC, start_time DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->hydrateAppointments($results, $loadRelations);

        } catch (PDOException $e) {
            error_log("Erro ao buscar pagamentos paginados: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Conta o total de agendamentos pagos (para paginação)
     */
    public function countPaid(): int {
        try {
            $sql = "SELECT COUNT(*) FROM appointments WHERE paid = 1 AND deleted_at IS NULL";
            $stmt = $this->pdo->query($sql);
            return (int) $stmt->fetchColumn();

        } catch (PDOException $e) {
            error_log("Erro ao contar pagamentos: " . $e->getMessage());
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
        ?DateTime $endDate,
        ?string $notes,
        float $price
    ): array {
        try {
            $stmt = $this->pdo->prepare("CALL sp_create_recurrence(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $patientId,
                $professionalId,
                $serviceId,
                $type,
                $dayOfWeek,
                $startHour,
                $startDate->format('Y-m-d'),
                $endDate?->format('Y-m-d'),
                $notes,
                $price
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
        ?string $notes,
        float $price
    ): int {
        try {
            $stmt = $this->pdo->prepare("CALL sp_create_appointment(?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $patientId,
                $professionalId,
                $serviceId,
                $startTime->format('Y-m-d H:i:s'),
                $notes,
                $price
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
     * Soft delete: esta sessão e todas as futuras (start_time >= $fromStartTime)
     * do mesmo grupo de recorrência — sessões passadas são preservadas
     *
     * @return int Número de sessões excluídas
     */
    public function deleteFromRecurrence(int $recurrenceGroupId, DateTime $fromStartTime): int {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE appointments
                SET deleted_at = NOW()
                WHERE recurrence_group_id = :group_id
                AND start_time >= :from_time
                AND deleted_at IS NULL"
            );

            $stmt->execute([
                'group_id' => $recurrenceGroupId,
                'from_time' => $fromStartTime->format('Y-m-d H:i:s'),
            ]);

            return $stmt->rowCount();

        } catch (PDOException $e) {
            error_log("Erro ao excluir sessões futuras da recorrência #{$recurrenceGroupId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Soft delete: todas as sessões do grupo de recorrência, passadas e futuras
     *
     * @return int Número de sessões excluídas
     */
    public function deleteRecurrenceGroup(int $recurrenceGroupId): int {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE appointments
                SET deleted_at = NOW()
                WHERE recurrence_group_id = :group_id
                AND deleted_at IS NULL"
            );

            $stmt->execute(['group_id' => $recurrenceGroupId]);

            return $stmt->rowCount();

        } catch (PDOException $e) {
            error_log("Erro ao excluir recorrência #{$recurrenceGroupId}: " . $e->getMessage());
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
     * HISTÓRICO (AUDITORIA)
     * =======================================================================
     */

    /**
     * Registra uma entrada no histórico de um agendamento
     */
    public function logHistory(
        int $appointmentId,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        ?int $changedByUserId,
        ?string $reason = null
    ): void {
        try {
            $sql = "INSERT INTO appointment_history
                    (appointment_id, action, from_status, to_status, changed_by_user_id, reason)
                    VALUES (:appointment_id, :action, :from_status, :to_status, :changed_by_user_id, :reason)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'appointment_id'      => $appointmentId,
                'action'              => $action,
                'from_status'         => $fromStatus,
                'to_status'           => $toStatus,
                'changed_by_user_id'  => $changedByUserId,
                'reason'              => $reason,
            ]);

        } catch (PDOException $e) {
            // Falha ao logar não deve quebrar a ação principal — só registra o erro
            error_log("Erro ao registrar histórico do agendamento #{$appointmentId}: " . $e->getMessage());
        }
    }

    /**
     * Busca o histórico de um agendamento, mais recente primeiro,
     * já com o nome de quem fez a alteração
     *
     * @return array
     */
    public function getHistory(int $appointmentId): array {
        try {
            $sql = "SELECT h.*, u.name AS changed_by_name
                    FROM appointment_history h
                    LEFT JOIN users u ON h.changed_by_user_id = u.id
                    WHERE h.appointment_id = :appointment_id
                    ORDER BY h.created_at DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['appointment_id' => $appointmentId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Erro ao buscar histórico do agendamento #{$appointmentId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * =======================================================================
     * MÉTODOS PRIVADOS - HIDRATAÇÃO
     * =======================================================================
     */

    /**
     * Carrega relacionamentos (User e Service) em um único Appointment
     */
    private function loadRelations(Appointment $appointment): void {
        $this->loadRelationsBatch([$appointment]);
    }

    /**
     * Carrega relacionamentos (User e Service) em uma lista de agendamentos
     * de uma só vez — 2 queries com `WHERE id IN (...)` no lugar de 3 queries
     * por agendamento (evita N+1: uma listagem de 100 agendamentos deixa de
     * gerar 300 SELECTs extras e passa a gerar só 2)
     *
     * @param Appointment[] $appointments
     */
    private function loadRelationsBatch(array $appointments): void {
        if (empty($appointments)) {
            return;
        }

        $userIds = [];
        $serviceIds = [];
        foreach ($appointments as $appointment) {
            $userIds[] = $appointment->getPatientId();
            $userIds[] = $appointment->getProfessionalId();
            $serviceIds[] = $appointment->getServiceId();
        }

        $users = $this->userRepo->findByIds($userIds);
        $services = $this->serviceRepo->findByIds($serviceIds);

        foreach ($appointments as $appointment) {
            if (isset($users[$appointment->getPatientId()])) {
                $appointment->setPatient($users[$appointment->getPatientId()]);
            }

            if (isset($users[$appointment->getProfessionalId()])) {
                $appointment->setProfessional($users[$appointment->getProfessionalId()]);
            }

            if (isset($services[$appointment->getServiceId()])) {
                $appointment->setService($services[$appointment->getServiceId()]);
            }
        }
    }

    /**
     * Converte array de dados do banco em array de Appointment objects
     */
    private function hydrateAppointments(array $results, bool $loadRelations): array {
        $appointments = array_map(fn($data) => new Appointment($data), $results);

        if ($loadRelations) {
            $this->loadRelationsBatch($appointments);
        }

        return $appointments;

    }
}
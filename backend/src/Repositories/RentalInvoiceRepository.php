<?php
namespace App\Repositories;

use App\Models\RentalInvoice;
use PDO;
use PDOException;
use DateTime;

class RentalInvoiceRepository {
    private PDO $pdo;
    private UserRepository $userRepo;

    public function __construct(PDO $pdo, UserRepository $userRepo) {
        $this->pdo = $pdo;
        $this->userRepo = $userRepo;
    }

    public function findById(int $id, bool $loadRelations = true): ?RentalInvoice {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM rental_invoices WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$data) {
                return null;
            }

            $invoice = new RentalInvoice($data);

            if ($loadRelations) {
                $this->loadRelations($invoice);
            }

            return $invoice;

        } catch (PDOException $e) {
            error_log("Erro ao buscar fatura de sublocação: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Idempotência do fechamento mensal: já existe fatura de avulso pra esse tenant/mês?
     */
    public function findAvulsoMonthlyInvoice(int $tenantUserId, DateTime $referenceMonth): ?RentalInvoice {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM rental_invoices
                 WHERE tenant_user_id = :tenant_user_id
                   AND type = 'avulso_monthly'
                   AND reference_month = :reference_month"
            );
            $stmt->execute([
                'tenant_user_id'  => $tenantUserId,
                'reference_month' => $referenceMonth->format('Y-m-01'),
            ]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            return $data ? new RentalInvoice($data) : null;

        } catch (PDOException $e) {
            error_log("Erro ao verificar fatura de avulso existente: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Idempotência da fatura mensal antecipada: já existe fatura desta recorrência pra esse mês?
     */
    public function findPeriodAdvanceInvoice(int $rentalRecurrenceId, DateTime $referenceMonth): ?RentalInvoice {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM rental_invoices
                 WHERE rental_recurrence_id = :rental_recurrence_id
                   AND type = 'period_advance'
                   AND reference_month = :reference_month"
            );
            $stmt->execute([
                'rental_recurrence_id' => $rentalRecurrenceId,
                'reference_month'      => $referenceMonth->format('Y-m-01'),
            ]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            return $data ? new RentalInvoice($data) : null;

        } catch (PDOException $e) {
            error_log("Erro ao verificar fatura antecipada existente: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @return RentalInvoice[]
     */
    public function findByTenant(int $tenantUserId): array {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM rental_invoices WHERE tenant_user_id = :tenant_user_id ORDER BY reference_month DESC"
            );
            $stmt->execute(['tenant_user_id' => $tenantUserId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn($data) => new RentalInvoice($data), $results);

        } catch (PDOException $e) {
            error_log("Erro ao listar faturas do locatário: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @return RentalInvoice[]
     */
    public function getAll(): array {
        try {
            $stmt = $this->pdo->query("SELECT * FROM rental_invoices ORDER BY reference_month DESC, created_at DESC");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->hydrateInvoices($results);

        } catch (PDOException $e) {
            error_log("Erro ao listar faturas de sublocação: " . $e->getMessage());
            throw $e;
        }
    }

    public function create(
        int $tenantUserId,
        string $type,
        DateTime $referenceMonth,
        float $amount,
        DateTime $dueDate,
        ?int $rentalRecurrenceId = null
    ): int {
        try {
            $sql = "INSERT INTO rental_invoices
                    (tenant_user_id, type, reference_month, rental_recurrence_id, amount, due_date)
                    VALUES (:tenant_user_id, :type, :reference_month, :rental_recurrence_id, :amount, :due_date)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'tenant_user_id'       => $tenantUserId,
                'type'                 => $type,
                'reference_month'      => $referenceMonth->format('Y-m-01'),
                'rental_recurrence_id' => $rentalRecurrenceId,
                'amount'               => $amount,
                'due_date'             => $dueDate->format('Y-m-d'),
            ]);

            return (int) $this->pdo->lastInsertId();

        } catch (PDOException $e) {
            error_log("Erro ao criar fatura de sublocação: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Acrescenta valor a uma fatura de avulso já existente (reserva tardia, criada
     * depois que o mês já tinha sido fechado). Se a fatura já estava paga, ela volta
     * pra 'pending' — o valor pago não cobre mais o total, precisa de atenção do admin
     */
    public function appendAmount(int $invoiceId, float $additionalAmount): bool {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE rental_invoices
                 SET amount = amount + :additional,
                     status = IF(status = 'paid', 'pending', status)
                 WHERE id = :id"
            );

            return $stmt->execute([
                'id'         => $invoiceId,
                'additional' => $additionalAmount,
            ]);

        } catch (PDOException $e) {
            error_log("Erro ao acrescentar valor à fatura de sublocação: " . $e->getMessage());
            throw $e;
        }
    }

    public function markAsPaid(int $invoiceId, string $paymentMethod, ?DateTime $paidDate = null): bool {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE rental_invoices
                 SET status = 'paid', payment_method = :payment_method, paid_date = :paid_date
                 WHERE id = :id"
            );

            return $stmt->execute([
                'id'             => $invoiceId,
                'payment_method' => $paymentMethod,
                'paid_date'      => ($paidDate ?? new DateTime())->format('Y-m-d'),
            ]);

        } catch (PDOException $e) {
            error_log("Erro ao marcar fatura de sublocação como paga: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Marca como 'overdue' faturas pendentes cujo vencimento já passou
     * Chamado sob demanda (ex: ao listar) — não precisa de cron próprio
     */
    public function refreshOverdueStatus(): int {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE rental_invoices
                 SET status = 'overdue'
                 WHERE status = 'pending' AND due_date < CURDATE()"
            );
            $stmt->execute();

            return $stmt->rowCount();

        } catch (PDOException $e) {
            error_log("Erro ao atualizar faturas vencidas: " . $e->getMessage());
            throw $e;
        }
    }

    private function loadRelations(RentalInvoice $invoice): void {
        $tenant = $this->userRepo->findById($invoice->getTenantUserId());
        if ($tenant) {
            $invoice->setTenant($tenant);
        }
    }

    private function hydrateInvoices(array $results): array {
        $invoices = array_map(fn($data) => new RentalInvoice($data), $results);

        foreach ($invoices as $invoice) {
            $this->loadRelations($invoice);
        }

        return $invoices;
    }
}

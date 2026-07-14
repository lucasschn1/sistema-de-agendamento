<?php
namespace App\Models;

use DateTime;

class RentalInvoice {
    private ?int $id;
    private int $tenantUserId;
    private string $type; // 'period_advance' | 'avulso_monthly'
    private DateTime $referenceMonth;
    private ?int $rentalRecurrenceId;

    private float $amount;
    private string $status; // 'pending' | 'paid' | 'overdue'
    private DateTime $dueDate;
    private ?DateTime $paidDate;
    private ?string $paymentMethod;

    private ?DateTime $created_at;
    private ?DateTime $updated_at;

    private ?User $tenant = null;

    public function __construct(array $data) {
        $this->id                 = isset($data['id']) ? (int) $data['id'] : null;
        $this->tenantUserId       = (int) ($data['tenant_user_id'] ?? 0);
        $this->type                = $data['type'] ?? 'avulso_monthly';
        $this->referenceMonth      = self::parseDate($data['reference_month'] ?? null) ?? new DateTime('first day of this month');
        $this->rentalRecurrenceId  = isset($data['rental_recurrence_id']) ? (int) $data['rental_recurrence_id'] : null;

        $this->amount         = (float) ($data['amount'] ?? 0.0);
        $this->status         = $data['status'] ?? 'pending';
        $this->dueDate        = self::parseDate($data['due_date'] ?? null) ?? new DateTime();
        $this->paidDate       = self::parseDate($data['paid_date'] ?? null);
        $this->paymentMethod  = $data['payment_method'] ?? null;

        $this->created_at = self::parseDate($data['created_at'] ?? null);
        $this->updated_at = self::parseDate($data['updated_at'] ?? null);
    }

    public function getId(): ?int { return $this->id; }
    public function getTenantUserId(): int { return $this->tenantUserId; }
    public function getType(): string { return $this->type; }
    public function getReferenceMonth(): DateTime { return $this->referenceMonth; }
    public function getRentalRecurrenceId(): ?int { return $this->rentalRecurrenceId; }
    public function getAmount(): float { return $this->amount; }
    public function getStatus(): string { return $this->status; }
    public function getDueDate(): DateTime { return $this->dueDate; }
    public function getPaidDate(): ?DateTime { return $this->paidDate; }
    public function getPaymentMethod(): ?string { return $this->paymentMethod; }

    public function isPeriodAdvance(): bool { return $this->type === 'period_advance'; }
    public function isAvulsoMonthly(): bool { return $this->type === 'avulso_monthly'; }
    public function isPaid(): bool { return $this->status === 'paid'; }
    public function isOverdue(): bool { return $this->status === 'overdue'; }

    public function getTenant(): ?User { return $this->tenant; }
    public function setTenant(User $tenant): void { $this->tenant = $tenant; }

    public function getFormattedAmount(): string {
        return 'R$ ' . number_format($this->amount, 2, ',', '.');
    }

    public function toPublicArray(bool $includeRelations = true): array {
        $data = [
            'id'              => $this->id,
            'type'            => $this->type,
            'reference_month' => $this->referenceMonth->format('Y-m'),
            'amount'          => $this->amount,
            'formatted_amount'=> $this->getFormattedAmount(),
            'status'          => $this->status,
            'due_date'        => $this->dueDate->format('Y-m-d'),
            'paid_date'       => $this->paidDate?->format('Y-m-d'),
            'payment_method'  => $this->paymentMethod,
        ];

        if ($includeRelations) {
            $data['tenant'] = $this->tenant?->toPublicArray();
        } else {
            $data['tenant_user_id'] = $this->tenantUserId;
        }

        return $data;
    }

    private static function parseDate(?string $value): ?DateTime {
        if (empty($value)) {
            return null;
        }

        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $value)
            ?: DateTime::createFromFormat('Y-m-d', $value);

        return $dt ?: null;
    }
}

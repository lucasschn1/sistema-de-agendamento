<?php
namespace App\Models;

use DateTime;
use InvalidArgumentException;
use DomainException;

class Appointment {
    // atributos de indentificação e estrutura
    private int $id;
    private int $patientId;
    private int $professionalId; 
    private int $serviceId;

    // recorrencia
    private ?int $recurrenceGroupId; // FK para agrupar sessões recorrentes
    private ?string $recurrenceType; // 'daily', 'weekly', 'monthly'

    // tempo
    private DateTime $startTime;
    private DateTime $endTime;
    private int $durationMinutes;

    // financeiro
    private float $price;
    private bool $paid;
    private ?string $paymentMethod;
    private ?DateTime $paymentDate;


    // status e observações
    private string $status; // 'pending', 'confirmed', 'cancelled', 'completed'
    private ?string $cancellationReason;
    private ?string $notes;

    //soft delete
    private ?DateTime $deleted_at;

    // timestamps
    private ?DateTime $created_at;
    private ?DateTime $updated_at;

    // relacionamentos - objetos injetados
    private ?User $patient = null;
    private ?User $professional = null;
    private ?Service $service = null;

    public function __construct(array $data) {
        $this->id = isset($data['id']) ? (int) $data['id'] : null;
        $this->patientId = (int) ($data['patient_id'] ?? 0);
        $this->professionalId = (int) ($data['professional_id'] ?? 0);
        $this->serviceId = (int) ($data['service_id'] ?? 0);

        $this->recurrenceGroupId = isset($data['recurrence_group_id']) 
            ? (int) $data['recurrence_group_id']
            : null;

        $this->recurrenceType = $data['recurrence_type'] ?? 'unico';

        $this->startTime = self::parseDateTime($data['start_time'] ?? 'now');
        $this->endTime = self::parseDateTime($data['end_time'] ?? 'now');
        $this->durationMinutes = (int) ($data['duration_minutes'] ?? 0);

        $this->price = (float) ($data['price'] ?? 0.0);
        $this->paid = (bool) ($data['paid'] ?? false);
        $this->paymentMethod = $data['payment_method'] ?? null;
        $this->paymentDate = self::parseDate($data['payment_date'] ?? null);

        $this->status = $data['status'] ?? 'scheduled';
        $this->cancellationReason = $data['cancellation_reason'] ?? null;
        $this->notes = $data['notes'] ?? null;

        $this->deleted_at = self::parseDateTime($data['deleted_at'] ?? null);
        $this->created_at = self::parseDateTime($data['created_at'] ?? null);
        $this->updated_at = self::parseDateTime($data['updated_at'] ?? null);
    }

    //GETTERS ID
    public function getId(): int { return $this->id; }
    public function getPatientId(): int { return $this->patientId; }
    public function getProfessionalId(): int { return $this->professionalId; }
    public function getServiceId(): int { return $this->serviceId; }

    // GETTERS - RECORRENCIA
    public function getRecurrenceGroupId(): ?int { return $this->recurrenceGroupId; }
    public function getRecurrenceType(): ?string { return $this->recurrenceType;}

    public function isRecurring(): bool {
        return $this->recurrenceGroupId !== null;
    }

    public function isUnique(): bool {
        return $this->recurrenceType === 'unico';
    }


    // GETTERS - TEMPO
    public function getStartTime(): DateTime {return $this->startTime; }
    public function getEndTime(): DateTime {return $this->endTime; }
    public function getDurationMinutes(): int { return $this->durationMinutes; }


    // GETTERS - FINACEIRO
    public function getPrice(): float { return $this->price; }
    public function isPaid(): bool { return $this->paid; }
    public function getPaymentMethod(): ?string { return $this->paymentMethod;}
    public function getPaymentDate(): ?DateTime { return $this->paymentDate; }

    // GETTERS - STATUS E OBSERVAÇÕES
    public function getStatus(): string { return $this->status; }
    public function getCancellationReason(): ?string { return $this->cancellationReason; }
    public function getNotes(): ?string { return $this->notes; }


    // GETTERS - CONTROLE
    public function getDeletedAt(): ?DateTime { return $this->deleted_at;}
    public function getCreatedAt(): ?DateTime { return $this->created_at; }
    public function getUpdatedAt(): ?DateTime { return $this->updated_at; }

    public function isDeleted(): bool {
        return $this->deleted_at !== null;
    }


    /*
    ================================================================
    VERIFICAÇÕES DE STATUS
    ================================================================
    */

    public function isScheduled(): bool { return $this->status === 'scheduled'; }
    public function isConfirmed(): bool { return $this->status === 'confirmed'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }
    public function isNoShow(): bool { return $this->status === 'no_show'; }

    public function isPending(): bool {
        return in_array($this->status, ['scheduled', 'confirmed']);
    }

    public function canBePaid(): bool {
        return in_array($this->status, ['completed', 'confirmed']) && !$this->paid;
    }

    public function canBeCancelled(): bool {
        return in_array($this->status, ['scheduled', 'confirmed']);
    }

    public function isFuture(): bool {
        return $this->startTime > new DateTime();   
    }

    public function isPast(): bool {
        return $this->endTime < new DateTime();
    }    
    
     /*
    ================================================================
    GETTERS DE RELACIONAMENTO (objetos)
    ================================================================
    */

    public function getPatient(): ?User { return $this->patient; }
    public function getProfessional(): ?User { return $this->professional; }
    public function getService(): ?Service { return $this->service; }


    /*
    ================================================================
    SETTERS DE RELACIONAMENTOS - INJETADOS
    ================================================================
    */

    public function setPatient(User $patient): void {
        if ($patient->getId() !== $this->patientId) {
            throw new InvalidArgumentException("Incompatibilidade no ID do paciente: esperado {$this->patientId}, obtido {$patient->getId()}");
        }

        $this->patient = $patient;
    }

    public function setProfessional(User $professional): void {
        if ($professional->getId() !== $this->professionalId) {
            throw new InvalidArgumentException("Incompatibilidade no ID do profissional: esperado {$this->professionalId}, obtido {$professional->getId()}");
        }
        $this->professional = $professional;
    }

    public function setService(Service $service): void {
        if ($service->getId() !== $this->serviceId) {
            throw new InvalidArgumentException("Incompatibilidade no ID do serviço: esperado {$this->serviceId}, obtido {$service->getId()}");
        }
        $this->service = $service;
    }

    /*
    ================================================================
    MÉTODOS DE AÇÃO - PAGAMENTOS   
    ================================================================
    */

    public function markAsPaid(string $method, ?DateTime $date = null): void {
        if (!$this->canBePaid()) {
            throw new DomainException("A consulta não pode ser marcada como paga no status atual: {$this->status}");
        }

        $this->paid = true;
        $this->paymentMethod = $method;
        $this->paymentDate = $date ?? new DateTime();
        $this->updated_at = new DateTime();
    }

    public function undoPayment(): void {
        if (!$this->paid) {
            throw new DomainException("A consulta já está marcada como não paga.");
        }
        $this->paid = false;
        $this->paymentMethod = null;
        $this->paymentDate = null;
        $this->updated_at = new DateTime();
    }

    /*
    ================================================================
    MÉTODOS DE AÇÃO - STATUS
    ================================================================
    */

    public function confirm(): void {
        if (!in_array($this->status, ['scheduled'])) {
            throw new DomainException("Não é possível confirmar: o status atual é '{$this->status}");
        }
        $this->status = 'confirmed';
        $this->updated_at = new DateTime();
    }

    public function complete(): void {
        if (!in_array($this->status, ['scheduled', 'confirmed'])) {
            throw new DomainException("Não é possível completar: o status atual é '{$this->status}'");
        }
        $this->status = 'completed';
        $this->updated_at = new DateTime();
    }

    public function cancel(?string $reason = null): void {
        if (!in_array($this->status, ['scheduled', 'confirmed'])) {
          throw new DomainException("Não é possível cancelar: o status atual é '{$this->status}'");  
        }
        $this->status = 'cancelled';
        $this->cancellationReason = $reason;
        $this->updated_at = new DateTime();
    }

    public function markAsNoShow(?string $reason = null): void { // motivo opcional para no-show (paciente não compareceu, profissional faltou, etc)
        if (!in_array($this->status, ['scheduled', 'confirmed'])) {
            throw new DomainException("Não é possível marcar como no-show: o status atual é '{$this->status}'");
        }
        $this->status = 'no_show';
        $this->cancellationReason = $reason;
        $this->updated_at = new DateTime();
    }


    /*
    ================================================================
    FORMATAÇÃO
    ================================================================
    */

    public function getFormattedPrice(): string {
        return 'R$ ' . number_format($this->price, 2, ',', '.');
    }

    public function getFormattedStartTime(string $format = 'd/m/Y H:i'): string {
        return $this->startTime->format($format);
    }

    public function getFormattedEndTime(string $format = 'd/m/Y H:i'): string {
        return $this->endTime->format($format);
    }

    public function getFormattedDuration(): string {
        $hours = floor($this->durationMinutes / 60);
        $minutes = $this->durationMinutes % 60;

        if ($hours > 0 && $minutes >0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }

    /*
    ================================================================
    SERIALIZAÇÃO
    ================================================================
    */

    public function toArray(): array { // para uso interno: persistência, manipulação, etc
        return [
            'id'                   => $this->id,
            'patient_id'           => $this->patientId,
            'professional_id'      => $this->professionalId,
            'service_id'           => $this->serviceId,
            'recurrence_group_id'  => $this->recurrenceGroupId,
            'recurrence_type'      => $this->recurrenceType,
            'start_time'           => $this->startTime->format('Y-m-d H:i:s'),
            'end_time'             => $this->endTime->format('Y-m-d H:i:s'),
            'duration_minutes'     => $this->durationMinutes,
            'price'                => $this->price,
            'paid'                 => $this->paid,
            'payment_method'       => $this->paymentMethod,
            'payment_date'         => $this->paymentDate?->format('Y-m-d'),
            'status'               => $this->status,
            'cancellation_reason'  => $this->cancellationReason,
            'notes'                => $this->notes,
            'deleted_at'           => $this->deleted_at?->format('Y-m-d H:i:s'),
            'created_at'           => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'           => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function toPublicArray(bool $includeRelation = true): array { // para exposição em APIs, interfaces, etc - somente dados relevantes e formatados
        $data = [
            'id'                  => $this->id,
            'start_time'          => $this->startTime->format('Y-m-d H:i:s'),
            'end_time'            => $this->endTime->format('Y-m-d H:i:s'),
            'formatted_start'     => $this->getFormattedStartTime(),
            'formatted_end'       => $this->getFormattedEndTime('H:i'),
            'duration_minutes'    => $this->durationMinutes,
            'formatted_duration'  => $this->getFormattedDuration(),
            'price'               => $this->price,
            'formatted_price'     => $this->getFormattedPrice(),
            'paid'                => $this->paid,
            'payment_method'      => $this->paymentMethod,
            'payment_date'        => $this->paymentDate?->format('d/m/Y'),
            'status'              => $this->status,
            'cancellation_reason' => $this->cancellationReason,
            'recurrence_type'     => $this->recurrenceType,
            'is_recurring'        => $this->isRecurring(),
            'notes'               => $this->notes,
        ];

        if ($includeRelation) {
            $data['patient'] = $this->patient?->toPublicArray();
            $data['professional'] = $this->professional?->toPublicArray();
            $data['service'] = $this->service?->toPublicArray();
        } else {
            // apenas IDs para referência ou se não houver objetos injetados
            $data['patient_id'] = $this->patientId;
            $data['professinal_id'] = $this->professionalId;
            $data['service_id'] = $this->serviceId;
        }

        return $data;
    }

    /*
    ================================================================
    HELLPERS PRIVADOS
    ================================================================
    */

    private static function parseDateTime(?string $value): ?DateTime {
        if (empty($value) || $value === 'now') {
            return $value === 'now' ? new DateTime(): null;
        }

        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $value);
        return $dt ?: null;
    }

    private static function parseDate(?string $value): ?DateTime {
        if (empty($value)) return null;
        
        $dt = DateTime::createFromFormat('Y-m-d', $value);
        return $dt ?: null;

    }

}
?>
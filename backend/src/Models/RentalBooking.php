<?php
namespace App\Models;

use DateTime;

class RentalBooking {
    private ?int $id;
    private int $rentalRoomId;
    private int $tenantUserId;

    private ?int $rentalRecurrenceId;
    private bool $isRecurring;

    private DateTime $bookingDate;
    private string $period;
    private DateTime $startTime;
    private DateTime $endTime;

    private float $price;
    private ?int $rentalInvoiceId;

    private string $status;
    private ?string $cancellationReason;

    private ?DateTime $deleted_at;
    private ?DateTime $created_at;
    private ?DateTime $updated_at;

    // relacionamentos carregados sob demanda
    private ?RentalRoom $room = null;
    private ?User $tenant = null;

    public function __construct(array $data) {
        $this->id                 = isset($data['id']) ? (int) $data['id'] : null;
        $this->rentalRoomId       = (int) ($data['rental_room_id'] ?? 0);
        $this->tenantUserId       = (int) ($data['tenant_user_id'] ?? 0);
        $this->rentalRecurrenceId = isset($data['rental_recurrence_id']) ? (int) $data['rental_recurrence_id'] : null;
        $this->isRecurring        = (bool) ($data['is_recurring'] ?? false);

        $this->bookingDate = self::parseDateTime($data['booking_date'] ?? null) ?? new DateTime();
        $this->period      = $data['period'] ?? 'avulso';
        $this->startTime   = self::parseDateTime($data['start_time'] ?? null) ?? new DateTime();
        $this->endTime     = self::parseDateTime($data['end_time'] ?? null) ?? new DateTime();

        $this->price           = (float) ($data['price'] ?? 0.0);
        $this->rentalInvoiceId = isset($data['rental_invoice_id']) ? (int) $data['rental_invoice_id'] : null;

        $this->status             = $data['status'] ?? 'scheduled';
        $this->cancellationReason = $data['cancellation_reason'] ?? null;

        $this->deleted_at = self::parseDateTime($data['deleted_at'] ?? null);
        $this->created_at = self::parseDateTime($data['created_at'] ?? null);
        $this->updated_at = self::parseDateTime($data['updated_at'] ?? null);
    }

    // GETTERS
    public function getId(): ?int { return $this->id; }
    public function getRentalRoomId(): int { return $this->rentalRoomId; }
    public function getTenantUserId(): int { return $this->tenantUserId; }
    public function getRentalRecurrenceId(): ?int { return $this->rentalRecurrenceId; }
    public function isRecurring(): bool { return $this->isRecurring; }
    public function getBookingDate(): DateTime { return $this->bookingDate; }
    public function getPeriod(): string { return $this->period; }
    public function getStartTime(): DateTime { return $this->startTime; }
    public function getEndTime(): DateTime { return $this->endTime; }
    public function getPrice(): float { return $this->price; }
    public function getRentalInvoiceId(): ?int { return $this->rentalInvoiceId; }
    public function getStatus(): string { return $this->status; }
    public function getCancellationReason(): ?string { return $this->cancellationReason; }
    public function getDeletedAt(): ?DateTime { return $this->deleted_at; }
    public function getCreatedAt(): ?DateTime { return $this->created_at; }
    public function getUpdatedAt(): ?DateTime { return $this->updated_at; }

    public function isScheduled(): bool { return $this->status === 'scheduled'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }
    public function isBilled(): bool { return $this->rentalInvoiceId !== null; }
    public function isAvulso(): bool { return $this->period === 'avulso'; }
    public function isPast(): bool { return $this->endTime < new DateTime(); }
    public function isFuture(): bool { return $this->startTime > new DateTime(); }

    public function getRoom(): ?RentalRoom { return $this->room; }
    public function getTenant(): ?User { return $this->tenant; }
    public function setRoom(RentalRoom $room): void { $this->room = $room; }
    public function setTenant(User $tenant): void { $this->tenant = $tenant; }

    public function getFormattedPrice(): string {
        return 'R$ ' . number_format($this->price, 2, ',', '.');
    }

    public function getFormattedStartTime(string $format = 'd/m/Y H:i'): string {
        return $this->startTime->format($format);
    }

    // SERIALIZAÇÃO

    public function toArray(): array {
        return [
            'id'                    => $this->id,
            'rental_room_id'        => $this->rentalRoomId,
            'tenant_user_id'        => $this->tenantUserId,
            'rental_recurrence_id'  => $this->rentalRecurrenceId,
            'is_recurring'          => $this->isRecurring,
            'booking_date'          => $this->bookingDate->format('Y-m-d'),
            'period'                => $this->period,
            'start_time'            => $this->startTime->format('Y-m-d H:i:s'),
            'end_time'              => $this->endTime->format('Y-m-d H:i:s'),
            'price'                 => $this->price,
            'rental_invoice_id'     => $this->rentalInvoiceId,
            'status'                => $this->status,
            'cancellation_reason'   => $this->cancellationReason,
            'deleted_at'            => $this->deleted_at?->format('Y-m-d H:i:s'),
            'created_at'            => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'            => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function toPublicArray(bool $includeRelations = true): array {
        $data = [
            'id'                  => $this->id,
            'booking_date'        => $this->bookingDate->format('Y-m-d'),
            'period'              => $this->period,
            'start_time'          => $this->startTime->format('Y-m-d H:i:s'),
            'end_time'            => $this->endTime->format('Y-m-d H:i:s'),
            'formatted_start'     => $this->getFormattedStartTime(),
            'is_recurring'        => $this->isRecurring,
            'rental_recurrence_id'=> $this->rentalRecurrenceId,
            'price'               => $this->price,
            'formatted_price'     => $this->getFormattedPrice(),
            'is_billed'           => $this->isBilled(),
            'status'              => $this->status,
            'cancellation_reason' => $this->cancellationReason,
        ];

        if ($includeRelations) {
            $data['room']   = $this->room?->toPublicArray();
            $data['tenant'] = $this->tenant?->toPublicArray();
        } else {
            $data['rental_room_id'] = $this->rentalRoomId;
            $data['tenant_user_id'] = $this->tenantUserId;
        }

        return $data;
    }

    private static function parseDateTime(?string $value): ?DateTime {
        if (empty($value)) {
            return null;
        }

        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $value)
            ?: DateTime::createFromFormat('Y-m-d', $value);

        return $dt ?: null;
    }
}

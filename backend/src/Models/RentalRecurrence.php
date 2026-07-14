<?php
namespace App\Models;

use DateTime;

class RentalRecurrence {
    private ?int $id;
    private int $tenantUserId;
    private int $rentalRoomId;

    private string $period; // manha | tarde | noite — nunca avulso
    private int $dayOfWeek; // 0=Domingo ... 6=Sábado

    private DateTime $startDate;
    private ?DateTime $endDate;
    private float $price;

    private bool $active;

    private ?DateTime $created_at;
    private ?DateTime $updated_at;

    private ?User $tenant = null;
    private ?RentalRoom $room = null;

    public function __construct(array $data) {
        $this->id           = isset($data['id']) ? (int) $data['id'] : null;
        $this->tenantUserId = (int) ($data['tenant_user_id'] ?? 0);
        $this->rentalRoomId = (int) ($data['rental_room_id'] ?? 0);

        $this->period    = $data['period'] ?? 'manha';
        $this->dayOfWeek = (int) ($data['day_of_week'] ?? 0);

        $this->startDate = self::parseDate($data['start_date'] ?? null) ?? new DateTime();
        $this->endDate   = self::parseDate($data['end_date'] ?? null);
        $this->price     = (float) ($data['price'] ?? 0.0);

        $this->active = (bool) ($data['active'] ?? true);

        $this->created_at = self::parseDate($data['created_at'] ?? null);
        $this->updated_at = self::parseDate($data['updated_at'] ?? null);
    }

    public function getId(): ?int { return $this->id; }
    public function getTenantUserId(): int { return $this->tenantUserId; }
    public function getRentalRoomId(): int { return $this->rentalRoomId; }
    public function getPeriod(): string { return $this->period; }
    public function getDayOfWeek(): int { return $this->dayOfWeek; }
    public function getStartDate(): DateTime { return $this->startDate; }
    public function getEndDate(): ?DateTime { return $this->endDate; }
    public function getPrice(): float { return $this->price; }
    public function isActive(): bool { return $this->active; }

    public function getTenant(): ?User { return $this->tenant; }
    public function getRoom(): ?RentalRoom { return $this->room; }
    public function setTenant(User $tenant): void { $this->tenant = $tenant; }
    public function setRoom(RentalRoom $room): void { $this->room = $room; }

    public function getFormattedPrice(): string {
        return 'R$ ' . number_format($this->price, 2, ',', '.');
    }

    public function toPublicArray(bool $includeRelations = true): array {
        $data = [
            'id'          => $this->id,
            'period'      => $this->period,
            'day_of_week' => $this->dayOfWeek,
            'start_date'  => $this->startDate->format('Y-m-d'),
            'end_date'    => $this->endDate?->format('Y-m-d'),
            'price'       => $this->price,
            'formatted_price' => $this->getFormattedPrice(),
            'active'      => $this->active,
        ];

        if ($includeRelations) {
            $data['tenant'] = $this->tenant?->toPublicArray();
            $data['room']   = $this->room?->toPublicArray();
        } else {
            $data['tenant_user_id'] = $this->tenantUserId;
            $data['rental_room_id'] = $this->rentalRoomId;
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

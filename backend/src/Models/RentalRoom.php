<?php
namespace App\Models;

use DateTime;

class RentalRoom {
    private ?int $id;
    private string $name;
    private bool $active;

    private ?DateTime $deleted_at;
    private ?DateTime $created_at;
    private ?DateTime $updated_at;

    public function __construct(array $data) {
        $this->id     = isset($data['id']) ? (int) $data['id'] : null;
        $this->name   = $data['name'] ?? '';
        $this->active = (bool) ($data['active'] ?? true);

        $this->deleted_at = self::parseDate($data['deleted_at'] ?? null);
        $this->created_at = self::parseDate($data['created_at'] ?? null);
        $this->updated_at = self::parseDate($data['updated_at'] ?? null);
    }

    // GETTERS
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function isActive(): bool { return $this->active; }
    public function getDeletedAt(): ?DateTime { return $this->deleted_at; }
    public function getCreatedAt(): ?DateTime { return $this->created_at; }
    public function getUpdatedAt(): ?DateTime { return $this->updated_at; }

    public function isDeleted(): bool {
        return $this->deleted_at !== null;
    }

    // SETTERS
    public function setName(string $newName): void {
        $this->name = $newName;
        $this->updated_at = new DateTime();
    }

    public function activate(): void {
        $this->active = true;
        $this->updated_at = new DateTime();
    }

    public function deactivate(): void {
        $this->active = false;
        $this->updated_at = new DateTime();
    }

    // SERIALIZAÇÃO

    public function toArray(): array {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'active'     => $this->active,
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function toPublicArray(): array {
        return [
            'id'     => $this->id,
            'name'   => $this->name,
            'active' => $this->active,
        ];
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

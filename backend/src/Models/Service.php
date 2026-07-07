<?php
namespace App\Models;

use DateTime;
use InvalidArgumentException;

class Service {
    private ?int $id;
    private string $name;
    private ?string $description;
    private float $price;
    private int $duration_minutes;
    private string $category;
    private bool $active;

    // soft delete
    private ?DateTime $deleted_at;

    // timestamps
    private ?DateTime $created_at;
    private ?DateTime $updated_at;

    public function __construct(array $data) {
        $this->id = isset($data['id']) ? (int) $data['id'] : null;
        $this->name = $data['name'] ?? '';
        $this->description = $data['description'] ?? null;
        $this->price = (float) ($data['price'] ?? 0.0);                         // forçando float
        $this->duration_minutes = (int) ($data['duration_minutes'] ?? 0);       // forçando int
        $this->category = $data['category'] ?? null;              
        $this->active = (bool) ($data['active'] ?? true);
                             
        $this->deleted_at = self::parseDate($data['deleted_at'] ?? null);
        $this->created_at = self::parseDate($dat['created_at'] ?? null);
        $this->updated_at = self::parseDate($data['updated_at'] ?? null);
    }

    // GETTERS
    public function getId(): int { return $this->id;}
    public function getName(): string { return $this->name;}
    public function getDescription(): string { return $this->description;}
    public function getPrice(): float { return $this->price;}
    public function getDurationMinutes(): int { return $this->duration_minutes;}
    public function getCategory(): string { return $this->category;}
    public function isActive(): bool { return $this->active;}
    public function getDeletedAt(): ?DateTime { return $this->deleted_at; }
    public function getCreatedAt(): ?DateTime { return $this->created_at;}
    public function getUpdatedAt(): ?DateTime  { return $this->updated_at;}


    // Verificações
    public function isDeleted(): bool {
        return $this->deleted_at !== null;
    }

    //SETTERS
    public function setPrice(float $newPrice): void {
        if ($newPrice < 0) {
            throw new InvalidArgumentException('O preço não pode ser negativo.');
        }

        $this->price = $newPrice;
        $this->updated_at = new DateTime(); // atualiza timestamp
       
    }

    public function setDescription(string $newDescription): void {
        $this->description = $newDescription;
        $this->updated_at = new DateTime(); // atualiza timestamp
    }

    public function setCategory(string $newCategory): void {
        $this->category = $newCategory;
        $this->updated_at = new DateTime(); // atualiza timestamp
    }

    public function activate(): void {
        $this->active = true;
        $this->updated_at = new DateTime(); // atualiza timestamp
    }

    public function deactivate(): void {
        $this->active = false;
        $this->updated_at = new DateTime(); // atualiza timestamp
    }

    public function getFormattedPrice(): string {
        return 'R$ ' . number_format($this->price, 2, ',', '.');
    }

    public function getFormattedDuration(): string {
        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0 && $minutes >0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }

    /*
    =======================================================================================================================
    SERIALIZAÇÃO PARA ARRAY - ÚTIL PARA RESPONDER JSON EM CONTROLLERS
    
     - NUNCA EXPOR CAMPOS SENSÍVEIS (EX: SENHA) - APENAS CAMPOS NECESSÁRIOS PARA RESPOSTA DE API
     - FORMATAÇÃO DE CAMPOS (EX: DATAS, PREÇOS) PARA PADRÕES DE API (EX: ISO 8601 PARA DATAS, STRING FORMATADA PARA PREÇOS)
    =======================================================================================================================
    */

    public function toArray(): array { // para uso interno, sem formatação específica
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'description'      => $this->description,
            'price'            => $this->price,
            'duration_minutes' => $this->duration_minutes,
            'category'         => $this->category,
            'active'           => $this->active,
            'deleted_at'       => $this->deleted_at?->format('Y-m-d H:i:s'),
            'created_at'       => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'       => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function toPublicArray(): Array { // para resposta de API, com formatação específica
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'description'        => $this->description,
            'price'              => $this->price, // valor numérico para cálculos
            'duration_minutes'   => $this->duration_minutes, // valor numérico para cálculos
            'formatted_price'    => $this->getFormattedPrice(), // preço formatado
            'formatted_duration' => $this->getFormattedDuration(), // duração formatada
            'category'           => $this->category,
            'active'             => $this->active,
        ];
    }

    // HELLPERS PRIVADOS

    private static function parseDate(?string $value): ?DateTime { // tenta criar DateTime a partir de string, retorna null se falhar ou se valor for vazio
        if (empty($value)) {
            return null;
        }

        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $value)
            ?: DateTime::createFromFormat('Y-m-d', $value); 

        return $dt ?: null;
    }
}
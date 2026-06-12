<?php
namespace App\Exceptions;

use App\Exceptions\BusinessException;

class ValidationException extends BusinessException
{
    private array $errors;
 
    public function __construct(array $errors)
    {
        $this->errors = $errors;
        $message = "Erro de validação: " . implode('; ', $errors);
        parent::__construct($message, 422);
    }
 
    public function getErrors(): array
    {
        return $this->errors;
    }
 
    public function toArray(): array
    {
        return [
            'error' => true,
            'type' => static::class,
            'message' => 'Erro de validação',
            'errors' => $this->errors,
        ];
    }
}
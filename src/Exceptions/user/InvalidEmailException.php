<?php
namespace App\Exceptions;

class InvalidEmailException extends BusinessException {
    
    public function __construct(string $email) {
        parent::__construct("E-mail inválido: {$email}", 400);
    }
}
?>
<?php
namespace App\Exceptions\user;
use App\Exceptions\BusinessException;

class InvalidEmailException extends BusinessException {
    
    public function __construct(string $email) {
        parent::__construct("E-mail inválido: {$email}", 400);
    }
}
?>
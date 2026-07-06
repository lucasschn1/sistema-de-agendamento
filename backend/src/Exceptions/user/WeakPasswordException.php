<?php
namespace App\Exceptions\user;
use App\Exceptions\BusinessException;

class WeakPasswordException extends BusinessException {
    public function __construct (string $message = "Senha deve ter no mínimo 6 caracteres") {
        parent::__construct($message, 400);
    }
}
?>
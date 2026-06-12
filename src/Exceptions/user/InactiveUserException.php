<?php
namespace App\Exceptions\user;

use App\Exceptions\BusinessException;

class InactiveUserException extends BusinessException {
    
    public function __construct(string $userType = "Usuário") {
        parent::__construct("{$userType} está inativo", 400);
    }
}
?>
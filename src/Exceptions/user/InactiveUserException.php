<?php
class InactiveUserException extends BusinessException {
    
    public function __construct(string $userType = "Usuário") {
        parent::__construct("{$userType} está inativo", 400);
    }
}
?>
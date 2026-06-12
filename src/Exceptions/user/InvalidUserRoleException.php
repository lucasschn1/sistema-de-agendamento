<?php
namespace App\Exceptions\user;

use App\Exceptions\BusinessException;

class InvalidUserRoleException extends BusinessException {
    
    public function __construct(string $expectedRole, string $actualRole) {
        parent::__construct(
            "Usuário deve ter role '{$expectedRole}', mas possui '{$actualRole}'",
            400
        );
    }
}
?>
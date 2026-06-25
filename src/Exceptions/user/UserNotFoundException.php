<?php
namespace App\Exceptions;

use App\Exceptions\BusinessException;

class UserNotFoundException extends BusinessException {
    protected int $httpStatus = 404;

    public function __construct(int $userId) {
        parent::__construct("Usuário #{$userId} não encontradap ");
    }
}
?>
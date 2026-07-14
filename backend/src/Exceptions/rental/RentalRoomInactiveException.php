<?php
namespace App\Exceptions\rental;

use App\Exceptions\BusinessException;

class RentalRoomInactiveException extends BusinessException {

    public function __construct() {
        parent::__construct("Esta sala está inativa e não pode receber novas reservas", 400);
    }
}

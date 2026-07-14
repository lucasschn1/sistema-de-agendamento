<?php
namespace App\Exceptions\rental;

use App\Exceptions\BusinessException;

class RentalRoomInUseException extends BusinessException {

    public function __construct() {
        parent::__construct(
            "Não é possível inativar: existem reservas ou recorrências ativas nesta sala",
            400
        );
    }
}

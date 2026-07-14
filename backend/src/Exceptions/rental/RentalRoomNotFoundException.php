<?php
namespace App\Exceptions\rental;

use App\Exceptions\BusinessException;

class RentalRoomNotFoundException extends BusinessException {

    public function __construct(int $roomId) {
        parent::__construct("Sala de sublocação #{$roomId} não encontrada", 404);
    }
}

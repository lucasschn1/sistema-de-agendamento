<?php
namespace App\Exceptions\rental;

use App\Exceptions\BusinessException;

class RentalRecurrenceNotFoundException extends BusinessException {

    public function __construct(int $recurrenceId) {
        parent::__construct("Recorrência de sublocação #{$recurrenceId} não encontrada", 404);
    }
}

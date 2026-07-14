<?php
namespace App\Exceptions\rental;

use App\Exceptions\BusinessException;

class RentalBookingNotFoundException extends BusinessException {

    public function __construct(int $bookingId) {
        parent::__construct("Reserva de sublocação #{$bookingId} não encontrada", 404);
    }
}

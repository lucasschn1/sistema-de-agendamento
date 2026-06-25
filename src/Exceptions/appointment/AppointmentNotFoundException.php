<?php

namespace App\Exceptions;

use App\Exceptions\BusinessException;

class AppointmentNotFoundException extends BusinessException {
    protected int $httpStatusCode = 404;

    public function __construct(int $appointmentId) {
        parent::__construct("Agendamento #{$appointmentId} não encontrado", 404);
    }
}
?>
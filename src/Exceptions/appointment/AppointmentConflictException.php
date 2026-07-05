<?php
namespace App\Exceptions\appointment;

use App\Exceptions\BusinessException;

class AppointmentConflictException extends BusinessException {
    protected int $httpStatusCode = 409; // conflit

    public function __construct(string $message = "Conflito de horário: profissional já 
    possui agendamento nesse período") {
        parent::__construct($message, 409);
    }
}
?>
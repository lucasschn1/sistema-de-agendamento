<?php 
class PastAppointmentException extends BusinessException {
    public function __construct(string $action  = "cancelar") {
        parent::__construct("Não é possível {$action} um agendamento que já passou", 400);
    }
}
?>
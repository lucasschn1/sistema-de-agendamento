<?php
class UserHasFutureAppointmentsException extends BusinessException {
    public function __construct() {
            parent::__construct(
            "Não é possível desativar o usuário: existem agendamentos futuros vinculados. " .
            "Cancele-os antes de desativar o cadastro.",
            400
        );
    }
}
?>
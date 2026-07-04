<?php
namespace App\Exceptions;

use App\Exceptions\BusinessException;

class NoShowTimeException extends BusinessException {
    public function __construct() {
        parent::__construct("Só é possível marcar como falta após o horário do agendamento ter passado", 400);
    }
}

?>
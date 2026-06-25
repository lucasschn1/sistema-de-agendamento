<?php
namespace App\Exceptions;
use App\Exceptions\BusinessException;

class InactiveProcedureException extends BusinessException {
    public function __construct() {
        parent::__construct("Procedimento/Serviço está inativo e não
        pode ser usado para agendamento", 400);
    }
}
<?php 
namespace App\Exceptions;

use App\Exceptions\BusinessException;

class ProcedureNotFoundException extends BusinessException {

    public function __construct(int $procedureId) {
        parent::__construct("Procedimento/Serviço #{$procedureId} não encontrado", 404);
    }
}
?>
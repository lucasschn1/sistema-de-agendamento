<?php
class ProcedureInUseException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            "Não é possível inativar: existem recorrências ativas usando este procedimento",
            400
        );
    }
}

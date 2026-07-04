<?php
namespace App\Exceptions;

use App\Exceptions\BusinessException;
 
class AlreadyPaidException extends BusinessException
{
    public function __construct()
    {
        parent::__construct("Este agendamento já foi pago", 400);
    }
}
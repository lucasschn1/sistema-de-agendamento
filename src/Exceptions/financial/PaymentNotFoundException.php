<?php
namespace App\Exceptions\financial;

use App\Exceptions\BusinessException;

class PaymentNotFoundException extends BusinessException
{
    protected int $httpStatusCode = 404;
 
    public function __construct(int $appointmentId)
    {
        parent::__construct("Pagamento do agendamento #{$appointmentId} não encontrado", 404);
    }
}
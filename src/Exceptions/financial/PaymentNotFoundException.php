<?php
namespace App\Exceptions;

class PaymentNotFoundException extends BusinessException
{
    protected int $httpStatusCode = 404;
 
    public function __construct(int $appointmentId)
    {
        parent::__construct("Pagamento do agendamento #{$appointmentId} não encontrado", 404);
    }
}
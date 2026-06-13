<?php
namespace App\Exceptions\financial;

use App\Exceptions\BusinessException;

class InvalidPaymentStatusException extends BusinessException
{
    public function __construct(string $message = "Não é possível registrar pagamento: status do agendamento não permite")
    {
        parent::__construct($message, 400);
    }
}
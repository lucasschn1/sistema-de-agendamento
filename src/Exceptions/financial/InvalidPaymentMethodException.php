<?php
namespace App\Exceptions\financial;

use App\Exceptions\BusinessException;

class InvalidPaymentMethodException extends BusinessException
{
    public function __construct(string $method, array $allowedMethods)
    {
        $allowed = implode(', ', $allowedMethods);
        parent::__construct(
            "Método de pagamento '{$method}' inválido. Métodos aceitos: {$allowed}",
            400
        );
    }
}

<?php
namespace App\Exceptions\rental;

use App\Exceptions\BusinessException;

/**
 * Lançada quando alguém tenta criar uma recorrência fora de um bloco fechado
 * (ex: período 'avulso') — a trava de negócio, validada no Service ANTES
 * de bater no banco (o trigger é a rede de segurança, não a primeira defesa)
 */
class InvalidRentalRecurrenceException extends BusinessException {

    public function __construct(string $period) {
        parent::__construct(
            "Reservas do período '{$period}' não podem ser recorrentes — só blocos completos (manhã, tarde ou noite)",
            400
        );
    }
}

<?php
namespace App\Exceptions\appointment;

use App\Exceptions\BusinessException;

class RecurrenceLimitExceededException extends BusinessException
{
    public function __construct(int $maxYears = 2)
    {
        parent::__construct("Recorrência não pode ultrapassar {$maxYears} anos no futuro", 400);
    }
}
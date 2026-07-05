<?php
namespace App\Exceptions\procedure;
use App\Exceptions\BusinessException;

class InvalidDurationException extends BusinessException {

    public function __construct() {
        parent::__construct("Duração deve ser maior que zero", 400);
    }
}
?>
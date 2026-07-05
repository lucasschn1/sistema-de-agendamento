<?php
namespace App\Exceptions\procedure;
use App\Exceptions\BusinessException;

class InvalidPriceException extends BusinessException {

    public function __construct() {
        parent::__construct("Preço deve ser maior ou igual a zero", 400);
    }
}
?>
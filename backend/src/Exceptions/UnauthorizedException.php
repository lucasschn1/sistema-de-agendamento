<?php
namespace App\Exceptions;

use App\Exceptions\BusinessException;

class UnauthorizedException extends BusinessException
{
    protected int $httpStatusCode = 403;
 
    public function __construct(string $action = "realizar esta ação")
    {
        parent::__construct("Você não tem permissão para {$action}", 403);
    }
}
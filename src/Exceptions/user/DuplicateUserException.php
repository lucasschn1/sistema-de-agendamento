<?php
class DuplicateUserException extends BusinessException {
    protected int $httpStatus = 409;

    public function __construct(string $field) {
        parent::__construct("Já existe um usuário cadastrado com esse {$field}", 409);
    }
}
?>
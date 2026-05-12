<?php
Abstract class BusinessException extends Exception {
    /**
     * Exceção base para todas as exceções de negócios
     * Captura genérica
     */
    protected int $httpStatusCode = 400;

    public function getHttpStatusCode(): int {
        return $this->httpStatusCode;
    }

    /**
     * Para serializar em reposta JSON da API
     */
    public function toArray(): array {
        return [
            'error' => true,
            'type' => static::class,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ];
    }
}
?>
<?php

// =========================================================
// BOOTSTRAP
// Carrega autoload, .env, timezone e configuração de erros
// =========================================================

require_once __DIR__ . '/../src/Config/bootstrap.php';

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

// =========================================================
// CORS — responde preflight OPTIONS antes de qualquer coisa
// =========================================================

Response::handlePreflight();

// =========================================================
// CAPTURA GLOBAL DE EXCEÇÕES
// Garante que erros não tratados retornem JSON, nunca HTML
// =========================================================

set_exception_handler(function (\Throwable $e) {
    error_log(sprintf(
        "[%s] %s in %s:%d\n%s",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));

    Response::fromException($e)->send();
});

// =========================================================
// DEPENDÊNCIAS E ROTAS
// =========================================================

try {
    $container = require __DIR__ . '/../src/Config/dependencies.php';
} catch (\RuntimeException $e) {
    error_log("Falha crítica ao inicializar dependências: " . $e->getMessage());
    Response::serverError("Serviço temporariamente indisponível")->send();
}

$router = new Router();
require __DIR__ . '/../src/Config/Routes.php';

// =========================================================
// DESPACHA A REQUISIÇÃO
// =========================================================

$request = Request::capture();
$router->dispatch($request, $container);
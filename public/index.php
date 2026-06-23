<?php
/**
 * index.php - Front Controller
 * 
 * Único ponto de entrada da aplicação.
 * Toda requisição HTTP passa por aqui antes de chegar nos Controllers.
 * 
 * FLUXO:
 *   1. Carrega autoload e variáveis de ambiente
 *   2. Responde preflight CORS (OPTIONS)
 *   3. Instancia dependências
 *   4. Registra rotas
 *   5. Captura a requisição e despacha para o Controller correto
 *   6. Captura qualquer exceção não tratada e retorna JSON de erro
 */

declare(strict_types=1);
 
// =========================================================
// 1. AUTOLOAD E VARIÁVEIS DE AMBIENTE
// =========================================================
 
require_once __DIR__ . '/../vendor/autoload.php';
 
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
 
// Carrega o .env usando vlucas/phpdotenv (já incluso via Composer)
// composer require vlucas/phpdotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
 
// Valida variáveis obrigatórias
$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'APP_ENV', 'JWT_SECRET'])->notEmpty();
 
// =========================================================
// 2. CONFIGURAÇÃO DE ERROS
// =========================================================
 
$isDebug = ($_ENV['APP_ENV'] ?? 'production') === 'development';
 
if ($isDebug) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}
 
// =========================================================
// 3. PREFLIGHT CORS
// =========================================================
 
// Responde OPTIONS antes de qualquer processamento e encerra
Response::handlePreflight();
 
// =========================================================
// 4. CAPTURA GLOBAL DE EXCEÇÕES NÃO TRATADAS
// =========================================================
 
// Qualquer exceção não capturada nos Controllers cai aqui
// e é convertida em JSON — nunca expõe HTML de erro do PHP
set_exception_handler(function (\Throwable $e) use ($isDebug) {
    error_log(sprintf(
        "[%s] %s in %s:%d\nStack trace:\n%s",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));
 
    Response::fromException($e)->send();
});
 
// =========================================================
// 5. DEPENDÊNCIAS E ROTAS
// =========================================================
 
try {
    $container = require __DIR__ . '/../config/dependencies.php';
} catch (\RuntimeException $e) {
    // Falha crítica de infraestrutura (ex: banco inacessível, .env faltando)
    error_log("Falha ao inicializar dependências: " . $e->getMessage());
    Response::serverError("Serviço temporariamente indisponível")->send();
}
 
$router = new Router();
require __DIR__ . '/../config/routes.php';
 
// =========================================================
// 6. CAPTURA E DESPACHO DA REQUISIÇÃO
// =========================================================
 
$request = Request::capture();
$router->dispatch($request, $container);
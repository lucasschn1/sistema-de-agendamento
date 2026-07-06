<?php

// =========================================================
// AUTOLOAD
// =========================================================

require_once __DIR__ . '/../../vendor/autoload.php';

// =========================================================
// VARIÁVEIS DE AMBIENTE
// =========================================================

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Valida variáveis obrigatórias — falha cedo com mensagem clara
$dotenv->required([
    'APP_ENV',
    'DB_HOST',
    'DB_NAME',
    'DB_USER',
    'JWT_SECRET',
])->notEmpty();

// =========================================================
// TIMEZONE
// =========================================================

// Essencial para agendamentos — sem isso PHP usa UTC ou o timezone do servidor
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo');

// =========================================================
// CONFIGURAÇÃO DE ERROS
// =========================================================

$isDebug = ($_ENV['APP_ENV'] ?? 'production') === 'development';

if ($isDebug) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}
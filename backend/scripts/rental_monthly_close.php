<?php

/**
 * rental_monthly_close.php — Job mensal do módulo de sublocação
 *
 * Roda 3 tarefas, todas idempotentes (seguro rodar de novo no mesmo mês):
 *   1. Fecha as faturas de avulso do MÊS QUE ACABOU DE TERMINAR (pós-uso)
 *   2. Gera a fatura antecipada do MÊS QUE ESTÁ COMEÇANDO pras recorrências ativas
 *   3. Abastece mais semanas de reserva pras recorrências sem data de fim
 *
 * AGENDAMENTO SUGERIDO: todo dia 1 às 03:00
 *   Linux/produção (crontab):  0 3 1 * *  php /caminho/backend/scripts/rental_monthly_close.php
 *   Windows/dev (Task Scheduler): Trigger mensal, dia 1, 03:00 → Action: php.exe rental_monthly_close.php
 *
 * Uso manual (ex: fechar um mês específico pra teste):
 *   php rental_monthly_close.php 2026-09
 */

require_once __DIR__ . '/../src/Config/bootstrap.php';

use App\Services\RentalBillingService;
use App\Services\RentalRecurrenceService;

$container = require __DIR__ . '/../src/Config/dependencies.php';

$billingService    = $container[RentalBillingService::class];
$recurrenceService = $container[RentalRecurrenceService::class];

// Permite passar um mês específico via argumento (ex: "2026-09") — só pra rodar manualmente.
// Sem argumento, usa a data real de hoje (uso normal via cron).
$referenceArg = $argv[1] ?? null;
$today        = $referenceArg ? new DateTime("{$referenceArg}-01") : new DateTime();
$lastMonth    = (clone $today)->modify('last day of last month');

function logLine(string $message): void {
    echo '[' . date('Y-m-d H:i:s') . "] {$message}\n";
}

logLine("Iniciando job mensal de sublocação (referência: {$today->format('Y-m-d')})");

// 1. Fecha avulsos do mês que terminou
try {
    $closeResult = $billingService->closeMonth($lastMonth);
    logLine(sprintf(
        "Fechamento de avulsos [%s]: %d fatura(s) criada(s), %d atualizada(s), total R$ %s",
        $closeResult['reference_month'],
        $closeResult['invoices_created'],
        $closeResult['invoices_updated'],
        number_format($closeResult['total_amount'], 2, ',', '.')
    ));
} catch (\Throwable $e) {
    logLine("ERRO no fechamento de avulsos: " . $e->getMessage());
}

// 2. Gera fatura antecipada do mês que está começando
try {
    $invoiceResult = $recurrenceService->generateMonthlyInvoices($today);
    logLine(sprintf(
        "Faturas antecipadas do mês: %d criada(s), %d já existiam",
        $invoiceResult['invoices_created'],
        $invoiceResult['invoices_skipped_existing']
    ));
} catch (\Throwable $e) {
    logLine("ERRO ao gerar faturas antecipadas: " . $e->getMessage());
}

// 3. Abastece reservas futuras de recorrências sem data de fim
try {
    $topUpResult = $recurrenceService->topUpOpenEndedBookings($today);
    logLine(sprintf(
        "Reabastecimento de reservas: %d recorrência(s) reabastecida(s), %d reserva(s) criada(s)",
        $topUpResult['recurrences_topped_up'],
        $topUpResult['bookings_created']
    ));
} catch (\Throwable $e) {
    logLine("ERRO no reabastecimento de reservas: " . $e->getMessage());
}

logLine("Job mensal de sublocação concluído");

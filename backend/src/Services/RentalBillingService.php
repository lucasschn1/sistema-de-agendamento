<?php
namespace App\Services;

use App\Repositories\RentalBookingRepository;
use App\Repositories\RentalInvoiceRepository;
use DateTime;
use PDO;
use Throwable;

/**
 * RentalBillingService - fechamento de faturas do módulo de sublocação
 *
 * Responsabilidade única: agregar as reservas avulsas não faturadas de um mês
 * em UMA fatura por locatário (idempotente — rodar de novo no mesmo mês não duplica)
 */
class RentalBillingService {
    private RentalBookingRepository $bookingRepository;
    private RentalInvoiceRepository $invoiceRepository;
    private PDO $pdo;

    public function __construct(
        RentalBookingRepository $bookingRepository,
        RentalInvoiceRepository $invoiceRepository,
        PDO $pdo
    ) {
        $this->bookingRepository = $bookingRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->pdo = $pdo;
    }

    /**
     * Fecha as faturas de avulso de um mês de referência
     *
     * @param DateTime $referenceMonth Qualquer dia do mês a fechar
     * @return array{
     *   reference_month: string,
     *   invoices_created: int,
     *   invoices_skipped_existing: int,
     *   total_amount: float
     * }
     */
    public function closeMonth(DateTime $referenceMonth): array {
        $monthStart = (clone $referenceMonth)->modify('first day of this month')->setTime(0, 0, 0);
        $monthEnd   = (clone $referenceMonth)->modify('last day of this month')->setTime(23, 59, 59);

        $unbilled = $this->bookingRepository->findUnbilledAvulsoByMonth($monthStart, $monthEnd);

        // agrupa por tenant
        $byTenant = [];
        foreach ($unbilled as $booking) {
            $byTenant[$booking->getTenantUserId()][] = $booking;
        }

        $invoicesCreated = 0;
        $invoicesUpdated = 0;
        $totalAmount = 0.0;

        foreach ($byTenant as $tenantUserId => $bookings) {
            $amount = array_sum(array_map(fn($b) => $b->getPrice(), $bookings));
            $bookingIds = array_map(fn($b) => $b->getId(), $bookings);

            $this->pdo->beginTransaction();
            try {
                $existingInvoice = $this->invoiceRepository->findAvulsoMonthlyInvoice($tenantUserId, $monthStart);

                if ($existingInvoice) {
                    // Reserva tardia: mês já tinha sido fechado, acrescenta na fatura existente
                    // em vez de deixar essas sessões perdidas sem nunca serem faturadas
                    $this->invoiceRepository->appendAmount($existingInvoice->getId(), $amount);
                    $this->bookingRepository->markAsBilled($bookingIds, $existingInvoice->getId());
                    $invoicesUpdated++;
                } else {
                    $dueDate = (clone $monthEnd)->modify('+10 days'); // vence dia 10 do mês seguinte

                    $invoiceId = $this->invoiceRepository->create(
                        $tenantUserId,
                        'avulso_monthly',
                        $monthStart,
                        $amount,
                        $dueDate
                    );

                    $this->bookingRepository->markAsBilled($bookingIds, $invoiceId);
                    $invoicesCreated++;
                }

                $this->pdo->commit();
                $totalAmount += $amount;

            } catch (Throwable $e) {
                $this->pdo->rollBack();
                error_log("Erro ao fechar fatura de avulso do tenant #{$tenantUserId}: " . $e->getMessage());
                throw $e;
            }
        }

        return [
            'reference_month'  => $monthStart->format('Y-m'),
            'invoices_created' => $invoicesCreated,
            'invoices_updated' => $invoicesUpdated,
            'total_amount'     => round($totalAmount, 2),
        ];
    }
}

<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\RentalInvoiceRepository;

/**
 * RentalInvoiceController - Consulta e baixa de faturas do módulo de sublocação
 *
 * Rotas exclusivas admin:
 *   GET   /api/rentals/invoices             → index()
 *   PATCH /api/rentals/invoices/{id}/pay    → pay()
 */
class RentalInvoiceController {
    private RentalInvoiceRepository $invoiceRepository;

    public function __construct(RentalInvoiceRepository $invoiceRepository) {
        $this->invoiceRepository = $invoiceRepository;
    }

    /**
     * GET /api/rentals/invoices
     * Atualiza o status 'overdue' das faturas vencidas antes de listar
     */
    public function index(Request $request): Response {
        try {
            $this->invoiceRepository->refreshOverdueStatus();

            $invoices = $this->invoiceRepository->getAll();
            $data = array_map(fn($i) => $i->toPublicArray(), $invoices);

            return Response::json($data);

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * PATCH /api/rentals/invoices/{id}/pay
     * Body: { "payment_method": "PIX" }
     */
    public function pay(Request $request): Response {
        try {
            $id            = (int) $request->param('id');
            $paymentMethod = $request->input('payment_method', '');

            if (empty($paymentMethod)) {
                return Response::validationError(['payment_method' => 'Método de pagamento é obrigatório']);
            }

            $invoice = $this->invoiceRepository->findById($id, false);

            if (!$invoice) {
                return Response::notFound("Fatura de sublocação #{$id} não encontrada");
            }

            $this->invoiceRepository->markAsPaid($id, $paymentMethod);

            return Response::json(null, 200, 'Fatura marcada como paga');

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }
}

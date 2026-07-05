<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\FinancialService;
use App\Exceptions\ValidationException;
use App\Exceptions\appointment\AppointmentNotFoundException;
use App\Exceptions\financial\AlreadyPaidException;
use App\Exceptions\financial\InvalidPaymentMethodException;
use App\Exceptions\financial\InvalidPaymentStatusException;

/**
 * FinancialController - Gerencia o financeiro da clínica
 * 
 * Todas as rotas são exclusivas admin:
 *   POST  /api/financial/payment              → registerPayment()
 *   PATCH /api/financial/payment/{id}/undo    → undoPayment()
 *   GET   /api/financial/pending              → pending()
 *   GET   /api/financial/summary              → summary()
 *   GET   /api/financial/summary/month        → summaryByMonth()
 *   GET   /api/financial/summary/current      → currentMonth()
 *   GET   /api/financial/paid                 → paid()
 *   GET   /api/financial/methods              → paymentMethods()
 */
class FinancialController
{
    private FinancialService $financialService;

    public function __construct(FinancialService $financialService)
    {
        $this->financialService = $financialService;
    }


    // =========================================================
    // PAGAMENTOS
    // =========================================================

    /**
     * POST /api/financial/payment
     * Registra pagamento de um agendamento
     * 
     * Body esperado:
     * {
     *   "appointment_id": 42,
     *   "method": "PIX",
     *   "date": "2026-06-15"  (opcional — padrão hoje)
     * }
     */
    public function registerPayment(Request $request): Response
    {
        try {
            $appointmentId = $request->input('appointment_id');
            $method        = $request->input('method', '');
            $date          = $request->input('date');

            if (!$appointmentId) {
                return Response::validationError([
                    'appointment_id' => 'ID do agendamento é obrigatório'
                ]);
            }

            $this->financialService->registerPayment(
                (int) $appointmentId,
                $method,
                $date
            );

            return Response::json(null, 200, 'Pagamento registrado com sucesso');

        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());

        } catch (AppointmentNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (AlreadyPaidException $e) {
            return Response::error($e->getMessage(), 400, 'AlreadyPaidException');

        } catch (InvalidPaymentMethodException $e) {
            return Response::error($e->getMessage(), 400, 'InvalidPaymentMethodException');

        } catch (InvalidPaymentStatusException $e) {
            return Response::error($e->getMessage(), 400, 'InvalidPaymentStatusException');

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * PATCH /api/financial/payment/{id}/undo
     * Desfaz o pagamento de um agendamento (estorno/correção)
     * 
     * Body esperado:
     * {
     *   "reason": "Pagamento registrado no método errado"
     * }
     */
    public function undoPayment(Request $request): Response
    {
        try {
            $id     = (int) $request->param('id');
            $reason = $request->input('reason', '');

            $this->financialService->undoPayment($id, $reason);

            return Response::json(null, 200, 'Pagamento estornado com sucesso');

        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());

        } catch (AppointmentNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (InvalidPaymentStatusException $e) {
            return Response::error($e->getMessage(), 400, 'InvalidPaymentStatusException');

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }


    // =========================================================
    // RELATÓRIOS
    // =========================================================

    /**
     * GET /api/financial/pending
     * Lista agendamentos realizados com pagamento pendente
     */
    public function pending(Request $request): Response
    {
        try {
            $appointments = $this->financialService->getPendingPayments();
            $data         = array_map(fn($apt) => $apt->toPublicArray(), $appointments);

            return Response::json($data);

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * GET /api/financial/summary
     * Resumo financeiro de um período
     * 
     * Query params (obrigatórios):
     *   ?start=2026-01-01
     *   ?end=2026-06-30
     */
    public function summary(Request $request): Response
    {
        try {
            $start = $request->query('start');
            $end   = $request->query('end');

            if (!$start || !$end) {
                return Response::validationError([
                    'start' => 'Data de início é obrigatória',
                    'end'   => 'Data de fim é obrigatória',
                ]);
            }

            $summary = $this->financialService->getSummaryByPeriod(
                new \DateTime($start),
                new \DateTime($end)
            );

            return Response::json($summary);

        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * GET /api/financial/summary/month
     * Resumo financeiro de um mês específico
     * 
     * Query params:
     *   ?year=2026
     *   ?month=6
     */
    public function summaryByMonth(Request $request): Response
    {
        try {
            $year  = (int) $request->query('year', date('Y'));
            $month = (int) $request->query('month', date('n'));

            $summary = $this->financialService->getSummaryByMonth($year, $month);

            return Response::json($summary);

        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * GET /api/financial/summary/current
     * Resumo financeiro do mês atual
     * Atalho para o dashboard principal
     */
    public function currentMonth(Request $request): Response
    {
        try {
            $summary = $this->financialService->getCurrentMonthSummary();
            return Response::json($summary);

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * GET /api/financial/paid
     * Extrato de agendamentos pagos em um período
     * 
     * Query params:
     *   ?start=2026-06-01  (obrigatório)
     *   ?end=2026-06-30    (obrigatório)
     *   ?method=PIX        (opcional — filtra por método)
     */
    public function paid(Request $request): Response
    {
        try {
            $start  = $request->query('start');
            $end    = $request->query('end');
            $method = $request->query('method');

            if (!$start || !$end) {
                return Response::validationError([
                    'start' => 'Data de início é obrigatória',
                    'end'   => 'Data de fim é obrigatória',
                ]);
            }

            $appointments = $this->financialService->getPaidAppointments(
                new \DateTime($start),
                new \DateTime($end),
                $method ?: null
            );

            $data = array_map(fn($apt) => $apt->toPublicArray(), $appointments);
            return Response::json($data);

        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());

        } catch (InvalidPaymentMethodException $e) {
            return Response::error($e->getMessage(), 400, 'InvalidPaymentMethodException');

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * GET /api/financial/methods
     * Lista os métodos de pagamento aceitos pela clínica
     * Usado pelo frontend para popular selects e filtros
     */
    public function paymentMethods(Request $request): Response
    {
        return Response::json(
            $this->financialService->getAllowedPaymentMethods()
        );
    }
}
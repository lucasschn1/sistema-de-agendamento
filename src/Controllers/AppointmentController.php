<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AppointmentService;
use App\Exceptions\ValidationException;
use App\Exceptions\AppointmentNotFoundException;
use App\Exceptions\AppointmentConflictException;
use App\Exceptions\UserNotFoundException;
use App\Exceptions\ProcedureNotFoundException;
use App\Exceptions\InactiveUserException;
use App\Exceptions\InactiveProcedureException;
use App\Exceptions\PastAppointmentException;
use App\Exceptions\NoShowTimeException;


/**
 * AppointmentController - Gerencia agendamentos da clínica
 * 
 * Rotas autenticadas (admin + professional):
 *   GET   /api/appointments                          → index()
 *   GET   /api/appointments/{id}                     → show()
 *   POST  /api/appointments                          → store()
 *   PUT   /api/appointments/{id}                     → update()
 *   PATCH /api/appointments/{id}/confirm             → confirm()
 *   PATCH /api/appointments/{id}/complete            → complete()
 *   PATCH /api/appointments/{id}/cancel              → cancel()
 *   PATCH /api/appointments/{id}/no-show             → noShow()
 *   PATCH /api/appointments/{id}/reschedule          → reschedule()
 *   POST  /api/appointments/recurrence               → storeRecurrence()
 *   PATCH /api/appointments/recurrence/{groupId}/cancel → cancelRecurrence()
 *   GET   /api/appointments/recurrence/{groupId}     → showRecurrence()
 *   GET   /api/availability                          → availability()
 * 
 * Rotas exclusivas admin:
 *   DELETE /api/appointments/{id}                    → destroy()
 *   PATCH  /api/appointments/{id}/restore            → restore()
 */
Class AppointmentController {
    private AppointmentService $appointmentService;

    public function __construct(AppointmentService $appointmentService)  {
        $this->appointmentService = $appointmentService;
    }

    // =========================================================
    // LISTAGEM E BUSCA
    // =========================================================

    /**
     * GET /api/appointments
     * Lista agendamentos com filtros opcionais
     * 
     * Admin vê todos; professional vê apenas os seus
     * 
     * Query params:
     *   ?professional_id=1
     *   ?patient_id=3
     *   ?status=scheduled|confirmed|completed|cancelled|no_show
     *   ?start=2026-06-01
     *   ?end=2026-06-30
     */
    public function index(Request $request): Response {
        try {
            $user = $request->user();
            $start = $request->query('start');
            $end = $request->query('end');
            $status = $request->query('staus');

            // profissional só ve os seus agendamentos
            if ($user->isProfessional()) {
                $appointments = $this->appointmentService
                    ->getAppointmentsByProfessional($user->getId());

            // Admin pode filtrar por período ou profissional
            } elseif ($start && $end) {
                $appointments = $this->appointmentService->getAppointmentsByDateRange(
                    new \DateTime($start),
                    new \DateTime($end)
                );
            } else {
                $appointments = $this->appointmentService->getUpcomingAppointments();
            }

            // Filtro por status (opcional)
            if ($status) {
                $appointments = array_values(array_filter(
                    $appointments,
                    fn($apt) => $apt->getStatus() === $status
                ));
            }

            $data = array_map(fn($apt) => $apt->toPublicArray(), $appointments);
            return Response::json($data);

        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * GET /api/appointments/{id}
     * Retorna um agendamento específico com relacionamentos
     */
    public function show(Request $request): Response {
        try {
            $id = (int) $request->param('id');
            $appointment = $this->appointmentService->getAppointmentById($id);

            return Response::json($appointment->toPublicArray());

        } catch (AppointmentNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * GET /api/availability
     * Verifica disponibilidade de horário para o calendário
     * 
     * Query params (todos obrigatórios)
     *  ?professional_id=1
     *  ?date=2026-06-15
     *  ?duration=50
     *  ?exclude_id=10 (opcional - para reagendamentos)
     */
    public function availability(Request $request): Response {
        try {
            $professionalId = (int) $request->query('professional_id', 0);
            $date = $request->query('date');
            $duration = (int) $request->query('duration', 50);
            $excludeId = $request->query('exclude_id')
                ? (int) $request->query('exclude_id')
                : null;

            if (!$professionalId || !$date) {
                return Response::validationError([
                    'professional_id' => 'Obrigatório',
                    'date' => 'Obrigatório',
                ]);
            }

            $startTime = new \DateTime($date);
            $available = $this->appointmentService->isTimeSlotAvailable(
                $professionalId,
                $startTime,
                $duration,
                $excludeId
            );

            return Response::json(['available' => $available]);

        } catch (UserNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (\Throwable $e){
            return Response::serverError();
        }
    }

    // =========================================================
    // CRIAÇÃO
    // =========================================================

     /**
     * POST /api/appointments
     * Cria um agendamento único
     * 
     * Body esperado:
     * {
     *   "patient_id": 3,
     *   "professional_id": 1,
     *   "service_id": 1,
     *   "start_time": "2026-06-15 14:00:00",
     *   "notes": "opcional"
     * }
     */
    public function store(Request $request): Response {
        try {
            $id = $this->appointmentService->createAppointment($request->body());
            $appointment = $this->appointmentService->getAppointmentById($id);

            return Response::created($appointment->toPublicArray());

        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());

        } catch (AppointmentConflictException $e) {
            return Response::conflict($e->getMessage());

        } catch (UserNotFoundException | ProcedureNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (InactiveUserException | InactiveProcedureException $e) {
            return Response::notFound($e->getMessage());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * POST /api/appointments/recurrence
     * Cria um grupo de recorrência e gera todas as sessões
     * 
     * Body esperado:
     * {
     *   "patient_id": 3,
     *   "professional_id": 1,
     *   "service_id": 1,
     *   "type": "semanal",
     *   "day_of_week": 1,
     *   "start_hour": "10:00:00",
     *   "start_date": "2026-06-16",
     *   "end_date": "2026-12-31",
     *   "notes": "opcional"
     * }
     */
    public function storeRecurrence(Request $request): Response {
        try {
            $result = $this->appointmentService->createRecurrence($request->body());
 
            return Response::created($result, 'Recorrência criada com sucesso');
 
        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());
 
        } catch (AppointmentConflictException $e) {
            return Response::conflict($e->getMessage());
 
        } catch (UserNotFoundException | ProcedureNotFoundException $e) {
            return Response::notFound($e->getMessage());
 
        } catch (InactiveUserException | InactiveProcedureException $e) {
            return Response::error($e->getMessage(), 400);
 
        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }
}
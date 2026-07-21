<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AppointmentService;
use App\Services\EmailService;
use App\Exceptions\ValidationException;
use App\Exceptions\appointment\AppointmentNotFoundException;
use App\Exceptions\appointment\AppointmentConflictException;
use App\Exceptions\user\UserNotFoundException;
use App\Exceptions\procedure\ProcedureNotFoundException;
use App\Exceptions\user\InactiveUserException;
use App\Exceptions\procedure\InactiveProcedureException;
use App\Exceptions\appointment\NoShowTimeException;


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
 *   PATCH /api/appointments/{id}/no-show             → noShow()
 *   PATCH /api/appointments/{id}/reschedule          → reschedule()
 *   POST  /api/appointments/recurrence               → storeRecurrence()
 *   GET   /api/appointments/recurrence/{groupId}     → showRecurrence()
 *   GET   /api/availability                          → availability()
 *
 * Rotas exclusivas admin:
 *   DELETE /api/appointments/{id}?scope=this|future|all → destroy()
 *   PATCH  /api/appointments/{id}/restore                → restore()
 */
Class AppointmentController {
    private AppointmentService $appointmentService;
    private EmailService $emailService;

    public function __construct(AppointmentService $appointmentService, EmailService $emailService)  {
        $this->appointmentService = $appointmentService;
        $this->emailService = $emailService;
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
     * GET /api/appointments/{id}/history
     * Retorna o histórico de mudanças de status de um agendamento (auditoria)
     */
    public function history(Request $request): Response {
        try {
            $id = (int) $request->param('id');
            $history = $this->appointmentService->getAppointmentHistory($id);

            return Response::json($history);

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
     *   "price": 150.00,
     *   "notes": "opcional"
     * }
     */
    public function store(Request $request): Response {
        try {
            $id = $this->appointmentService->createAppointment($request->body());
            $appointment = $this->appointmentService->getAppointmentById($id);

            $this->emailService->sendAppointmentCreated($appointment);

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
     *   "price": 150.00,
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

    // =========================================================
    // ATUALIZAÇÃO
    // =========================================================

    /**
     * PUT /api/appointments/{id}
     * Atualiza dados básicos do agendamento (notas, preço)
     * Para mudar horário use PATCH /reschedule
     * 
     * Body esperado:
     * {
     *   "notes": "opcional",
     *   "price": 160.00  (opcional)
     * }
     */
    public function update(Request $request): Response {
        try {
            // Lê o ID do parâmetro de rota /appointments/{id}
            $id          = (int) $request->param('id');
            $appointment = $this->appointmentService->getAppointmentById($id, false);
 
            // Monta array com dados atuais sobrescrevendo apenas os campos enviados
            $updatedData          = $appointment->toArray();
            $updatedData['notes'] = $request->input('notes', $appointment->getNotes());
            $updatedData['price'] = $request->input('price') != null
                ? (float) $request->input('price')
                : $appointment->getPrice();
 
            // Cria o objeto atualizado e persiste via Service
            $updatedAppointment = new \App\Models\Appointment($updatedData);
            $this->appointmentService->updateAppointment($updatedAppointment);
 
            // Busca o registro atualizado com relacionamentos para retornar
            $appointment = $this->appointmentService->getAppointmentById($id);
            return Response::json($appointment->toPublicArray(), 200, 'Agendamento atualizado');
 
        } catch (AppointmentNotFoundException $e) {
            return Response::notFound($e->getMessage());
 
        } catch (AppointmentConflictException $e) {
            return Response::conflict($e->getMessage());
 
        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());
 
        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * PATCH /api/appointments/{id}/confirm
     */
    public function confirm(Request $request): Response {
        try {
            $id = (int) $request->param('id');
            $this->appointmentService->confirmAppointment($id, $request->user()->getId());

            $appointment = $this->appointmentService->getAppointmentById($id);
            $this->emailService->sendAppointmentConfirmed($appointment);

            return Response::json($appointment->toPublicArray(), 200, 'Agendamento confirmado');
 
        } catch (AppointmentNotFoundException $e) {
            return Response::notFound($e->getMessage());
 
        } catch (\DomainException $e) {
            return Response::error($e->getMessage(), 400);
 
        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * PATCH /api/appointments/{id}/complete
     */
    public function complete(Request $request): Response
    {
        try {
            $id = (int) $request->param('id');
            $this->appointmentService->completeAppointment($id, $request->user()->getId());
 
            $appointment = $this->appointmentService->getAppointmentById($id);
            return Response::json($appointment->toPublicArray(), 200, 'Sessão marcada como realizada');
 
        } catch (AppointmentNotFoundException $e) {
            return Response::notFound($e->getMessage());
 
        } catch (\DomainException $e) {
            return Response::error($e->getMessage(), 400);
 
        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * PATCH /api/appointments/{id}/no-show
     * 
     * Body esperado (opcional):
     * {
     *   "reason": "Paciente não atendeu ao telefone"
     * }
     */
    public function noShow(Request $request): Response {
        try {
            $id = (int) $request->param('id');
            $reason = $request->input('reason');
 
            $this->appointmentService->markAsNoShow($id, $reason, $request->user()->getId());
 
            $appointment = $this->appointmentService->getAppointmentById($id);
            return Response::json($appointment->toPublicArray(), 200, 'Marcado como falta');
 
        } catch (AppointmentNotFoundException $e) {
            return Response::notFound($e->getMessage());
 
        } catch (NoShowTimeException $e) {
            return Response::error($e->getMessage(), 400, 'NoShowTimeException');
 
        } catch (\DomainException $e) {
            return Response::error($e->getMessage(), 400);
 
        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * PATCH /api/appointments/{id}/reschedule
     * 
     * Body esperado:
     * {
     *   "start_time": "2026-06-20 15:00:00"
     * }
     */
    public function reschedule(Request $request): Response {
        try {
            $id = (int) $request->param('id');
            $startTime = $request->input('start_time');
            $isAdmin = $request->user()->isAdmin();
 
            if (empty($startTime)) {
                return Response::validationError(['start_time' => 'Novo horário é obrigatório']);
            }
 
            $this->appointmentService->rescheduleAppointment(
                $id,
                new \DateTime($startTime),
                $isAdmin
            );
 
            $appointment = $this->appointmentService->getAppointmentById($id);
            return Response::json($appointment->toPublicArray(), 200, 'Agendamento reagendado');
 
        } catch (AppointmentNotFoundException $e) {
            return Response::notFound($e->getMessage());
 
        } catch (AppointmentConflictException $e) {
            return Response::conflict($e->getMessage());
 
        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());
 
        } catch (\DomainException $e) {
            return Response::error($e->getMessage(), 400);
 
        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    // =========================================================
    // RECORRÊNCIAS
    // =========================================================
 
    /**
     * GET /api/appointments/recurrence/{groupId}
     * Lista todas as sessões de um grupo de recorrência
     */
    public function showRecurrence(Request $request): Response {
        try {
            $groupId = (int) $request->param('groupId');
            $appointments = $this->appointmentService->getRecurrenceSessions($groupId);
 
            $data = array_map(fn($apt) => $apt->toPublicArray(), $appointments);
            return Response::json($data);
 
        } catch (AppointmentNotFoundException $e) {
            return Response::notFound($e->getMessage());
 
        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }
 
    // =========================================================
    // ADMIN — DELEÇÃO E RESTAURAÇÃO
    // =========================================================

    /**
     * DELETE /api/appointments/{id}?scope=this|future|all
     * Exclui (soft delete) um agendamento — admin only.
     *
     * Único fluxo de encerramento de agendamentos (substitui o cancelamento).
     * Para sessões recorrentes, `scope` define o alcance da exclusão:
     *   this   (padrão) → apenas esta sessão
     *   future           → esta sessão e as futuras da mesma recorrência
     *   all              → todas as sessões da recorrência
     */
    public function destroy(Request $request): Response
    {
        try {
            $id = (int) $request->param('id');
            $scope = $request->query('scope', 'this');

            $deletedCount = $this->appointmentService->deleteAppointment($id, $scope);

            return Response::json(['deleted_count' => $deletedCount], 200, 'Agendamento excluído');

        } catch (AppointmentNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }
 
    /**
     * PATCH /api/appointments/{id}/restore
     * Restaura agendamento soft-deleted (admin only)
     */
    public function restore(Request $request): Response {
        try {
            $id = (int) $request->param('id');
            $this->appointmentService->restoreAppointment($id);
 
            $appointment = $this->appointmentService->getAppointmentById($id);
            return Response::json($appointment->toPublicArray(), 200, 'Agendamento restaurado');
 
        } catch (AppointmentNotFoundException $e) {
            return Response::notFound($e->getMessage());
 
        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }
}    
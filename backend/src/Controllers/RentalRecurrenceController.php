<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\RentalRecurrenceService;
use App\Exceptions\ValidationException;
use App\Exceptions\rental\RentalRecurrenceNotFoundException;
use App\Exceptions\rental\InvalidRentalRecurrenceException;
use App\Exceptions\rental\RentalRoomNotFoundException;
use App\Exceptions\rental\RentalRoomInactiveException;
use App\Exceptions\user\UserNotFoundException;
use App\Exceptions\user\InactiveUserException;
use App\Exceptions\user\InvalidUserRoleException;

/**
 * RentalRecurrenceController - Gerencia as sublocações fixas/recorrentes
 *
 * Rotas exclusivas admin:
 *   GET   /api/rentals/recurrences              → index()
 *   GET   /api/rentals/recurrences/{id}          → show()
 *   POST  /api/rentals/recurrences              → store()
 *   PATCH /api/rentals/recurrences/{id}/release  → release()
 */
class RentalRecurrenceController {
    private RentalRecurrenceService $recurrenceService;

    public function __construct(RentalRecurrenceService $recurrenceService) {
        $this->recurrenceService = $recurrenceService;
    }

    /**
     * GET /api/rentals/recurrences
     */
    public function index(Request $request): Response {
        try {
            $recurrences = $this->recurrenceService->getAllActiveRecurrences();
            $data = array_map(fn($r) => $r->toPublicArray(), $recurrences);

            return Response::json($data);

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * GET /api/rentals/recurrences/{id}
     */
    public function show(Request $request): Response {
        try {
            $id = (int) $request->param('id');
            $recurrence = $this->recurrenceService->getRecurrenceById($id);

            return Response::json($recurrence->toPublicArray());

        } catch (RentalRecurrenceNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * POST /api/rentals/recurrences
     * Body: {
     *   "rental_room_id": 1,
     *   "tenant_user_id": 3,
     *   "period": "manha" | "tarde" | "noite",
     *   "day_of_week": 1,
     *   "start_date": "2026-08-01",
     *   "end_date": "2027-08-01" (opcional),
     *   "price": 800.00
     * }
     */
    public function store(Request $request): Response {
        try {
            $result = $this->recurrenceService->createRecurrence($request->body());

            return Response::created($result, 'Recorrência criada com sucesso');

        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());

        } catch (InvalidRentalRecurrenceException $e) {
            return Response::error($e->getMessage(), 400, 'InvalidRentalRecurrenceException');

        } catch (RentalRoomNotFoundException|UserNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (RentalRoomInactiveException|InactiveUserException|InvalidUserRoleException $e) {
            return Response::error($e->getMessage(), 400, (new \ReflectionClass($e))->getShortName());

        } catch (\DomainException $e) {
            return Response::conflict($e->getMessage());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * PATCH /api/rentals/recurrences/{id}/release
     * Libera manualmente uma recorrência (decisão do admin — ex: inadimplência)
     *
     * Body: { "reason": "opcional" }
     */
    public function release(Request $request): Response {
        try {
            $id     = (int) $request->param('id');
            $reason = $request->input('reason');

            $result = $this->recurrenceService->releaseRecurrence($id, $reason);

            return Response::json($result, 200, 'Recorrência liberada');

        } catch (RentalRecurrenceNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }
}

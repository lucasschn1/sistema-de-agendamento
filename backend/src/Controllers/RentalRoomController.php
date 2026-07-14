<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\RentalRoomService;
use App\Exceptions\ValidationException;
use App\Exceptions\rental\RentalRoomNotFoundException;
use App\Exceptions\rental\RentalRoomInUseException;

/**
 * RentalRoomController - Gerencia as salas do módulo de sublocação
 *
 * Rotas exclusivas admin:
 *   GET    /api/rentals/rooms                 → index()
 *   GET    /api/rentals/rooms/{id}             → show()
 *   POST   /api/rentals/rooms                 → store()
 *   PUT    /api/rentals/rooms/{id}             → update()
 *   PATCH  /api/rentals/rooms/{id}/activate    → activate()
 *   PATCH  /api/rentals/rooms/{id}/deactivate  → deactivate()
 *   DELETE /api/rentals/rooms/{id}             → destroy()
 */
class RentalRoomController {
    private RentalRoomService $rentalRoomService;

    public function __construct(RentalRoomService $rentalRoomService) {
        $this->rentalRoomService = $rentalRoomService;
    }

    /**
     * GET /api/rentals/rooms
     * Query params: ?active=true|false
     */
    public function index(Request $request): Response {
        try {
            $activeOnly = $request->query('active', 'true') === 'true';

            // "Mostrar inativos" precisa enxergar as salas desativadas (soft-deleted)
            // pra dar pro admin a opção de reativar
            $rooms = $activeOnly
                ? $this->rentalRoomService->getAllActiveRooms()
                : $this->rentalRoomService->getAllRooms(true);

            $data = array_map(fn($r) => $r->toPublicArray(), $rooms);
            return Response::json($data);

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * GET /api/rentals/rooms/{id}
     */
    public function show(Request $request): Response {
        try {
            $id   = (int) $request->param('id');
            $room = $this->rentalRoomService->getRoomById($id);

            return Response::json($room->toPublicArray());

        } catch (RentalRoomNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * POST /api/rentals/rooms
     * Body: { "name": "Sala 1" }
     */
    public function store(Request $request): Response {
        try {
            $id   = $this->rentalRoomService->createRoom($request->body());
            $room = $this->rentalRoomService->getRoomById($id);

            return Response::created($room->toPublicArray());

        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());

        } catch (\DomainException $e) {
            return Response::conflict($e->getMessage());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * PUT /api/rentals/rooms/{id}
     */
    public function update(Request $request): Response {
        try {
            $id = (int) $request->param('id');
            $this->rentalRoomService->updateRoom($id, $request->body());

            $room = $this->rentalRoomService->getRoomById($id);
            return Response::json($room->toPublicArray(), 200, 'Sala atualizada');

        } catch (RentalRoomNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());

        } catch (\DomainException $e) {
            return Response::conflict($e->getMessage());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * PATCH /api/rentals/rooms/{id}/activate
     */
    public function activate(Request $request): Response {
        try {
            $id = (int) $request->param('id');
            $this->rentalRoomService->activateRoom($id);

            $room = $this->rentalRoomService->getRoomById($id);
            return Response::json($room->toPublicArray(), 200, 'Sala ativada');

        } catch (RentalRoomNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * PATCH /api/rentals/rooms/{id}/deactivate
     * Bloqueado se houver reservas futuras ou recorrências ativas na sala
     */
    public function deactivate(Request $request): Response {
        try {
            $id = (int) $request->param('id');
            $this->rentalRoomService->deactivateRoom($id);

            return Response::json(null, 200, 'Sala desativada');

        } catch (RentalRoomNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (RentalRoomInUseException $e) {
            return Response::error($e->getMessage(), 400, 'RentalRoomInUseException');

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * DELETE /api/rentals/rooms/{id}
     * Soft delete (mesma regra de deactivate)
     */
    public function destroy(Request $request): Response {
        try {
            $id = (int) $request->param('id');
            $this->rentalRoomService->deactivateRoom($id);

            return Response::noContent();

        } catch (RentalRoomNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (RentalRoomInUseException $e) {
            return Response::error($e->getMessage(), 400, 'RentalRoomInUseException');

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }
}

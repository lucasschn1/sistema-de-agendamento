<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\RentalBookingService;
use App\Exceptions\ValidationException;
use App\Exceptions\rental\RentalBookingNotFoundException;
use App\Exceptions\rental\RentalRoomNotFoundException;
use App\Exceptions\rental\RentalRoomInactiveException;
use App\Exceptions\user\UserNotFoundException;
use App\Exceptions\user\InactiveUserException;
use App\Exceptions\user\InvalidUserRoleException;

/**
 * RentalBookingController - Gerencia as reservas do módulo de sublocação
 *
 * Nesta fase: só reservas avulsas (sem recorrência)
 *
 * Rotas exclusivas admin:
 *   GET   /api/rentals/bookings              → index()
 *   GET   /api/rentals/bookings/{id}          → show()
 *   POST  /api/rentals/bookings              → store()
 *   PATCH /api/rentals/bookings/{id}/cancel   → cancel()
 */
class RentalBookingController {
    private RentalBookingService $bookingService;

    public function __construct(RentalBookingService $bookingService) {
        $this->bookingService = $bookingService;
    }

    /**
     * GET /api/rentals/bookings
     * Query params: ?start=2026-08-01&end=2026-08-31 (obrigatórios)
     */
    public function index(Request $request): Response {
        try {
            $start = $request->query('start');
            $end   = $request->query('end');

            if (!$start || !$end) {
                return Response::validationError([
                    'start' => 'Data de início é obrigatória',
                    'end'   => 'Data de fim é obrigatória',
                ]);
            }

            $bookings = $this->bookingService->getBookingsByDateRange(
                new \DateTime($start),
                new \DateTime($end)
            );

            $data = array_map(fn($b) => $b->toPublicArray(), $bookings);
            return Response::json($data);

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * GET /api/rentals/bookings/{id}
     */
    public function show(Request $request): Response {
        try {
            $id      = (int) $request->param('id');
            $booking = $this->bookingService->getBookingById($id);

            return Response::json($booking->toPublicArray());

        } catch (RentalBookingNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * POST /api/rentals/bookings
     * Body: {
     *   "rental_room_id": 1,
     *   "tenant_user_id": 3,
     *   "booking_date": "2026-08-01",
     *   "period": "manha" | "tarde" | "noite" | "avulso",
     *   "hour": 18,   // obrigatório se period = avulso (hora cheia, ex: 18 = 18h-19h)
     *   "price": 400.00
     * }
     */
    public function store(Request $request): Response {
        try {
            $id = $this->bookingService->createAvulsoBooking($request->body());
            $booking = $this->bookingService->getBookingById($id);

            return Response::created($booking->toPublicArray());

        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());

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
     * PATCH /api/rentals/bookings/{id}/cancel
     * Body: { "reason": "opcional" }
     */
    public function cancel(Request $request): Response {
        try {
            $id     = (int) $request->param('id');
            $reason = $request->input('reason');

            $this->bookingService->cancelBooking($id, $reason);

            return Response::json(null, 200, 'Reserva cancelada');

        } catch (RentalBookingNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (\DomainException $e) {
            return Response::conflict($e->getMessage());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }
}

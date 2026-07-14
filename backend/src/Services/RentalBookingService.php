<?php
namespace App\Services;

use App\Models\RentalBooking;
use App\Repositories\RentalBookingRepository;
use App\Repositories\RentalRoomRepository;
use App\Repositories\UserRepository;
use App\Support\RentalPeriod;
use App\Exceptions\rental\RentalBookingNotFoundException;
use App\Exceptions\rental\RentalRoomNotFoundException;
use App\Exceptions\rental\RentalRoomInactiveException;
use App\Exceptions\user\UserNotFoundException;
use App\Exceptions\user\InactiveUserException;
use App\Exceptions\user\InvalidUserRoleException;
use App\Exceptions\ValidationException;
use DateTime;
use InvalidArgumentException;

/**
 * RentalBookingService - reservas de sublocação de sala
 *
 * Nesta fase: só reservas avulsas (sem recorrência — isso é a Fase 3)
 */
class RentalBookingService {
    private RentalBookingRepository $bookingRepository;
    private RentalRoomRepository $roomRepository;
    private UserRepository $userRepository;

    public function __construct(
        RentalBookingRepository $bookingRepository,
        RentalRoomRepository $roomRepository,
        UserRepository $userRepository
    ) {
        $this->bookingRepository = $bookingRepository;
        $this->roomRepository = $roomRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * Cria uma reserva avulsa (não recorrente)
     *
     * @param array $data [
     *   'rental_room_id' => int (required),
     *   'tenant_user_id' => int (required, deve ser professional ativo),
     *   'booking_date'   => string Y-m-d (required),
     *   'period'         => string manha|tarde|noite|avulso (required),
     *   'hour'           => int (required se period = avulso — hora cheia, ex: 18),
     *   'price'          => float (required),
     * ]
     * @throws ValidationException
     * @throws RentalRoomNotFoundException
     * @throws RentalRoomInactiveException
     * @throws UserNotFoundException
     * @throws InactiveUserException
     * @throws InvalidUserRoleException
     * @return int ID da reserva criada
     */
    public function createAvulsoBooking(array $data): int {
        $this->validateRequiredFields($data, ['rental_room_id', 'tenant_user_id', 'booking_date', 'period', 'price']);

        $roomId   = (int) $data['rental_room_id'];
        $tenantId = (int) $data['tenant_user_id'];
        $period   = $data['period'];
        $price    = (float) $data['price'];

        try {
            RentalPeriod::assertValid($period);
        } catch (InvalidArgumentException $e) {
            throw new ValidationException(['period' => $e->getMessage()]);
        }

        $avulsoHour = null;
        if ($period === 'avulso') {
            if (!isset($data['hour']) || $data['hour'] === '') {
                throw new ValidationException(['hour' => "O campo 'hour' é obrigatório para reservas avulsas"]);
            }

            $avulsoHour = (int) $data['hour'];

            try {
                RentalPeriod::assertValidAvulsoHour($avulsoHour);
            } catch (InvalidArgumentException $e) {
                throw new ValidationException(['hour' => $e->getMessage()]);
            }
        }

        if ($price < 0) {
            throw new ValidationException(['price' => 'O preço não pode ser negativo']);
        }

        $this->validateRoom($roomId);
        $this->validateTenant($tenantId);

        $bookingDate = $this->parseDate($data['booking_date']);
        [$startTime, $endTime] = RentalPeriod::toDateTimeRange($bookingDate, $period, $avulsoHour);

        return $this->bookingRepository->create(
            $roomId,
            $tenantId,
            $bookingDate,
            $period,
            $startTime,
            $endTime,
            $price,
            isRecurring: false,
            rentalRecurrenceId: null
        );
    }

    /**
     * Cancela uma reserva
     *
     * @throws RentalBookingNotFoundException
     */
    public function cancelBooking(int $bookingId, ?string $reason = null): bool {
        if (!$this->bookingRepository->findById($bookingId, false)) {
            throw new RentalBookingNotFoundException($bookingId);
        }

        return $this->bookingRepository->cancel($bookingId, $reason);
    }

    /**
     * @throws RentalBookingNotFoundException
     */
    public function getBookingById(int $bookingId): RentalBooking {
        $booking = $this->bookingRepository->findById($bookingId);

        if (!$booking) {
            throw new RentalBookingNotFoundException($bookingId);
        }

        return $booking;
    }

    /**
     * @return RentalBooking[]
     */
    public function getBookingsByDateRange(DateTime $start, DateTime $end): array {
        return $this->bookingRepository->findByDateRange($start, $end);
    }

    // =========================================================
    // VALIDAÇÕES PRIVADAS
    // =========================================================

    private function validateRoom(int $roomId): void {
        $room = $this->roomRepository->findById($roomId);

        if (!$room) {
            throw new RentalRoomNotFoundException($roomId);
        }

        if (!$room->isActive()) {
            throw new RentalRoomInactiveException();
        }
    }

    private function validateTenant(int $tenantId): void {
        $tenant = $this->userRepository->findById($tenantId);

        if (!$tenant) {
            throw new UserNotFoundException($tenantId);
        }

        if (!$tenant->isActive()) {
            throw new InactiveUserException('Profissional');
        }

        if ($tenant->getRole() !== 'professional') {
            throw new InvalidUserRoleException('professional', $tenant->getRole());
        }
    }

    private function parseDate(string $value): DateTime {
        $date = DateTime::createFromFormat('Y-m-d', $value);

        if (!$date) {
            throw new ValidationException(['booking_date' => 'Data inválida, use o formato AAAA-MM-DD']);
        }

        return $date;
    }

    private function validateRequiredFields(array $data, array $requiredFields): void {
        $errors = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $errors[$field] = "O campo '{$field}' é obrigatório";
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}

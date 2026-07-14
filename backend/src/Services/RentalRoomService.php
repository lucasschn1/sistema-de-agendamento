<?php
namespace App\Services;

use App\Models\RentalRoom;
use App\Repositories\RentalRoomRepository;
use App\Exceptions\rental\RentalRoomNotFoundException;
use App\Exceptions\rental\RentalRoomInUseException;
use App\Exceptions\ValidationException;
use DomainException;
use InvalidArgumentException;

/**
 * RentalRoomService - camada de serviço para gerenciamento das salas de sublocação
 *
 * Responsabilidades:
 * - validação de regras de negócio antes de persistir
 * - impedir inativação de salas em uso
 *
 * NÃO faz:
 * - acesso direto ao banco de dados (delega para RentalRoomRepository)
 */
class RentalRoomService {
    private RentalRoomRepository $rentalRoomRepository;

    public function __construct(RentalRoomRepository $rentalRoomRepository) {
        $this->rentalRoomRepository = $rentalRoomRepository;
    }

    /**
     * Cria uma nova sala de sublocação
     *
     * @param array $data ['name' => string (required)]
     * @throws ValidationException
     * @return int ID da sala criada
     */
    public function createRoom(array $data): int {
        $this->validateRequiredFields($data, ['name']);

        $room = new RentalRoom(['name' => trim($data['name']), 'active' => true]);

        return $this->rentalRoomRepository->create($room);
    }

    /**
     * Atualiza dados da sala
     *
     * @throws RentalRoomNotFoundException
     * @throws ValidationException
     */
    public function updateRoom(int $roomId, array $data): bool {
        $room = $this->rentalRoomRepository->findById($roomId);

        if (!$room) {
            throw new RentalRoomNotFoundException($roomId);
        }

        if (isset($data['name'])) {
            $this->validateRequiredFields($data, ['name']);
            $room->setName(trim($data['name']));
        }

        return $this->rentalRoomRepository->update($room);
    }

    /**
     * Ativa uma sala — trata os dois casos possíveis:
     * uma sala desativada (soft-deleted, precisa "restore") ou
     * uma sala já não-deletada que só estava com active=0
     *
     * @throws RentalRoomNotFoundException
     */
    public function activateRoom(int $roomId): bool {
        $room = $this->rentalRoomRepository->findById($roomId, true);

        if (!$room) {
            throw new RentalRoomNotFoundException($roomId);
        }

        return $room->isDeleted()
            ? $this->rentalRoomRepository->restore($roomId)
            : $this->rentalRoomRepository->activate($roomId);
    }

    /**
     * Desativa uma sala (soft delete)
     *
     * REGRA DE NEGÓCIO: não permite inativar sala com reservas futuras
     * ou recorrências ativas — precisa liberar/cancelar antes
     *
     * @throws RentalRoomNotFoundException
     * @throws RentalRoomInUseException
     */
    public function deactivateRoom(int $roomId): bool {
        if (!$this->rentalRoomRepository->findById($roomId)) {
            throw new RentalRoomNotFoundException($roomId);
        }

        if ($this->rentalRoomRepository->hasActiveBookingsOrRecurrences($roomId)) {
            throw new RentalRoomInUseException();
        }

        return $this->rentalRoomRepository->delete($roomId);
    }

    /**
     * Restaura uma sala desativada
     *
     * @throws RentalRoomNotFoundException
     */
    public function reactivateRoom(int $roomId): bool {
        try {
            return $this->rentalRoomRepository->restore($roomId);
        } catch (InvalidArgumentException $e) {
            throw new RentalRoomNotFoundException($roomId);
        }
    }

    /**
     * @throws RentalRoomNotFoundException
     */
    public function getRoomById(int $roomId, bool $includeDeleted = false): RentalRoom {
        $room = $this->rentalRoomRepository->findById($roomId, $includeDeleted);

        if (!$room) {
            throw new RentalRoomNotFoundException($roomId);
        }

        return $room;
    }

    /**
     * @return RentalRoom[]
     */
    public function getAllActiveRooms(): array {
        return $this->rentalRoomRepository->getAllActive();
    }

    /**
     * @return RentalRoom[]
     */
    public function getAllRooms(bool $includeDeleted = false): array {
        return $this->rentalRoomRepository->getAll($includeDeleted);
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

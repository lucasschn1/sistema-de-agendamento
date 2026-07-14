<?php
namespace App\Services;

use App\Models\RentalRecurrence;
use App\Repositories\RentalRecurrenceRepository;
use App\Repositories\RentalBookingRepository;
use App\Repositories\RentalInvoiceRepository;
use App\Repositories\RentalRoomRepository;
use App\Repositories\UserRepository;
use App\Support\RentalPeriod;
use App\Exceptions\rental\RentalRecurrenceNotFoundException;
use App\Exceptions\rental\InvalidRentalRecurrenceException;
use App\Exceptions\rental\RentalRoomNotFoundException;
use App\Exceptions\rental\RentalRoomInactiveException;
use App\Exceptions\user\UserNotFoundException;
use App\Exceptions\user\InactiveUserException;
use App\Exceptions\user\InvalidUserRoleException;
use App\Exceptions\ValidationException;
use DateTime;
use PDO;
use Throwable;

/**
 * RentalRecurrenceService - sublocação fixa/recorrente (só blocos de 4h)
 *
 * Regra de negócio central: só período manha/tarde/noite pode virar recorrência.
 * Isso já é travado por trigger no banco — aqui é a validação de primeira linha,
 * que dá uma mensagem de erro legível antes de bater no banco.
 */
class RentalRecurrenceService {
    private const MAX_OCCURRENCES = 104; // ~2 anos de recorrência semanal, mesma trava usada em Agendamentos
    private const BATCH_SIZE = 12; // recorrências sem end_date geram/reabastecem em lotes de ~12 semanas

    private RentalRecurrenceRepository $recurrenceRepository;
    private RentalBookingRepository $bookingRepository;
    private RentalInvoiceRepository $invoiceRepository;
    private RentalRoomRepository $roomRepository;
    private UserRepository $userRepository;
    private PDO $pdo;

    public function __construct(
        RentalRecurrenceRepository $recurrenceRepository,
        RentalBookingRepository $bookingRepository,
        RentalInvoiceRepository $invoiceRepository,
        RentalRoomRepository $roomRepository,
        UserRepository $userRepository,
        PDO $pdo
    ) {
        $this->recurrenceRepository = $recurrenceRepository;
        $this->bookingRepository = $bookingRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->roomRepository = $roomRepository;
        $this->userRepository = $userRepository;
        $this->pdo = $pdo;
    }

    /**
     * Cria uma recorrência fixa e gera as reservas + a primeira fatura antecipada
     *
     * @param array $data [
     *   'rental_room_id' => int,
     *   'tenant_user_id' => int,
     *   'period'         => 'manha'|'tarde'|'noite' (nunca avulso),
     *   'day_of_week'    => int 0-6,
     *   'start_date'     => string Y-m-d,
     *   'end_date'       => string Y-m-d|null,
     *   'price'          => float (mensalidade),
     * ]
     * @throws ValidationException
     * @throws InvalidRentalRecurrenceException
     * @throws RentalRoomNotFoundException|RentalRoomInactiveException
     * @throws UserNotFoundException|InactiveUserException|InvalidUserRoleException
     * @return array{recurrence_id: int, bookings_created: int, invoice_id: int}
     */
    public function createRecurrence(array $data): array {
        $this->validateRequiredFields($data, [
            'rental_room_id', 'tenant_user_id', 'period', 'day_of_week', 'start_date', 'price',
        ]);

        $roomId       = (int) $data['rental_room_id'];
        $tenantId     = (int) $data['tenant_user_id'];
        $period       = $data['period'];
        $dayOfWeek    = (int) $data['day_of_week'];
        $price        = (float) $data['price'];

        if (!RentalPeriod::isEligibleForRecurrence($period)) {
            throw new InvalidRentalRecurrenceException($period);
        }

        if ($dayOfWeek < 0 || $dayOfWeek > 6) {
            throw new ValidationException(['day_of_week' => 'Deve ser um número entre 0 (domingo) e 6 (sábado)']);
        }

        if ($price < 0) {
            throw new ValidationException(['price' => 'O preço não pode ser negativo']);
        }

        $this->validateRoom($roomId);
        $this->validateTenant($tenantId);

        $startDate = $this->parseDate($data['start_date'], 'start_date');
        $endDate   = !empty($data['end_date']) ? $this->parseDate($data['end_date'], 'end_date') : null;

        $this->pdo->beginTransaction();
        try {
            $recurrenceId = $this->recurrenceRepository->create(
                $tenantId, $roomId, $period, $dayOfWeek, $startDate, $endDate, $price
            );

            $bookingsCreated = $this->generateBookings($recurrenceId, $roomId, $tenantId, $period, $dayOfWeek, $startDate, $endDate);

            $invoiceId = $this->invoiceRepository->create(
                $tenantId,
                'period_advance',
                $startDate,
                $price,
                $startDate, // vence na data de início — precisa estar paga pra garantir o bloqueio
                $recurrenceId
            );

            $this->pdo->commit();

            return [
                'recurrence_id'    => $recurrenceId,
                'bookings_created' => $bookingsCreated,
                'invoice_id'       => $invoiceId,
            ];

        } catch (Throwable $e) {
            $this->pdo->rollBack();

            if (str_contains($e->getMessage(), 'Conflito de horário')) {
                throw new \DomainException('Conflito de horário: sala já reservada em uma ou mais datas desta recorrência');
            }

            throw $e;
        }
    }

    /**
     * Libera manualmente uma recorrência (ex: inadimplência) — decisão do admin,
     * nunca automática. Encerra a recorrência e cancela as reservas futuras.
     *
     * @throws RentalRecurrenceNotFoundException
     * @return array{cancelled_bookings: int}
     */
    public function releaseRecurrence(int $recurrenceId, ?string $reason = null): array {
        $recurrence = $this->recurrenceRepository->findById($recurrenceId, false);

        if (!$recurrence) {
            throw new RentalRecurrenceNotFoundException($recurrenceId);
        }

        $today = new DateTime();

        $this->recurrenceRepository->deactivate($recurrenceId, $today);
        $cancelled = $this->bookingRepository->cancelFutureByRecurrence($recurrenceId, $today, $reason);

        return ['cancelled_bookings' => $cancelled];
    }

    /**
     * @throws RentalRecurrenceNotFoundException
     */
    public function getRecurrenceById(int $recurrenceId): RentalRecurrence {
        $recurrence = $this->recurrenceRepository->findById($recurrenceId);

        if (!$recurrence) {
            throw new RentalRecurrenceNotFoundException($recurrenceId);
        }

        return $recurrence;
    }

    /**
     * @return RentalRecurrence[]
     */
    public function getAllActiveRecurrences(): array {
        return $this->recurrenceRepository->getAllActive();
    }

    // =========================================================
    // GERAÇÃO DE RESERVAS
    // =========================================================

    private function generateBookings(
        int $recurrenceId,
        int $roomId,
        int $tenantId,
        string $period,
        int $dayOfWeek,
        DateTime $startDate,
        ?DateTime $endDate
    ): int {
        // encontra a primeira ocorrência a partir de start_date com o dia da semana correto
        $currentDate = clone $startDate;
        while ((int) $currentDate->format('w') !== $dayOfWeek) {
            $currentDate->modify('+1 day');
        }

        // sem end_date: gera só um lote por vez (self::BATCH_SIZE) — o cron mensal
        // (topUpOpenEndedBookings) vai completando o resto conforme o tempo passa
        $maxThisBatch = $endDate === null ? self::BATCH_SIZE : self::MAX_OCCURRENCES;

        return $this->generateBookingsFrom(
            $recurrenceId, $roomId, $tenantId, $period, $currentDate, $endDate, $maxThisBatch
        );
    }

    /**
     * Gera reservas semanais a partir de uma data já alinhada ao dia da semana certo,
     * até o end_date (se houver) ou até o limite de ocorrências deste lote
     */
    private function generateBookingsFrom(
        int $recurrenceId,
        int $roomId,
        int $tenantId,
        string $period,
        DateTime $fromDate,
        ?DateTime $endDate,
        int $maxThisBatch
    ): int {
        $currentDate = clone $fromDate;
        $created = 0;

        while (($endDate === null || $currentDate <= $endDate) && $created < $maxThisBatch) {
            [$start, $end] = RentalPeriod::toDateTimeRange($currentDate, $period);

            $this->bookingRepository->create(
                $roomId,
                $tenantId,
                clone $currentDate,
                $period,
                $start,
                $end,
                price: 0.0, // cobrança do fixo é por mês na recorrência/fatura, não por sessão
                isRecurring: true,
                rentalRecurrenceId: $recurrenceId
            );

            $created++;
            $currentDate->modify('+7 days');
        }

        return $created;
    }

    /**
     * "Abastece" mais reservas futuras de recorrências SEM data de fim, quando o
     * buffer de sessões já geradas está acabando. Chamado pelo cron mensal —
     * sem isso, uma recorrência sem end_date pararia de gerar sessões depois do
     * primeiro lote inicial.
     *
     * @param DateTime $today Data de referência (hoje, na prática)
     * @param int $bufferDays Se a última reserva gerada está a menos que isso de hoje, reabastece
     * @return array{recurrences_topped_up: int, bookings_created: int}
     */
    public function topUpOpenEndedBookings(DateTime $today, int $bufferDays = 56): array {
        $recurrences = $this->recurrenceRepository->getAllActive();

        $recurrencesToppedUp = 0;
        $bookingsCreated = 0;

        foreach ($recurrences as $recurrence) {
            if ($recurrence->getEndDate() !== null) {
                continue; // só recorrências abertas (sem fim) precisam de reabastecimento
            }

            $maxDate = $this->bookingRepository->findMaxBookingDateForRecurrence($recurrence->getId());

            if ($maxDate === null) {
                continue; // não deveria acontecer (toda recorrência gera pelo menos 1 sessão na criação)
            }

            $daysUntilMax = (int) $today->diff($maxDate)->format('%r%a');

            if ($daysUntilMax >= $bufferDays) {
                continue; // ainda tem buffer suficiente, não precisa reabastecer agora
            }

            $nextDate = (clone $maxDate)->modify('+7 days');

            $created = $this->generateBookingsFrom(
                $recurrence->getId(),
                $recurrence->getRentalRoomId(),
                $recurrence->getTenantUserId(),
                $recurrence->getPeriod(),
                $nextDate,
                null,
                self::BATCH_SIZE
            );

            if ($created > 0) {
                $recurrencesToppedUp++;
                $bookingsCreated += $created;
            }
        }

        return [
            'recurrences_topped_up' => $recurrencesToppedUp,
            'bookings_created'      => $bookingsCreated,
        ];
    }

    /**
     * Gera a fatura antecipada do mês de referência para todas as recorrências
     * ativas que já estavam em vigor naquele mês. Idempotente — chamado pelo cron.
     *
     * @return array{invoices_created: int, invoices_skipped_existing: int}
     */
    public function generateMonthlyInvoices(DateTime $referenceMonth): array {
        $monthStart = (clone $referenceMonth)->modify('first day of this month')->setTime(0, 0, 0);
        $monthEnd   = (clone $referenceMonth)->modify('last day of this month')->setTime(23, 59, 59);

        $recurrences = $this->recurrenceRepository->getAllActive();

        $created = 0;
        $skipped = 0;

        foreach ($recurrences as $recurrence) {
            if ($recurrence->getStartDate() > $monthEnd) {
                continue; // recorrência começa depois desse mês
            }

            if ($recurrence->getEndDate() !== null && $recurrence->getEndDate() < $monthStart) {
                continue; // recorrência já tinha terminado antes desse mês
            }

            if ($this->invoiceRepository->findPeriodAdvanceInvoice($recurrence->getId(), $monthStart)) {
                $skipped++;
                continue;
            }

            $this->invoiceRepository->create(
                $recurrence->getTenantUserId(),
                'period_advance',
                $monthStart,
                $recurrence->getPrice(),
                $monthStart, // vence no início do mês — precisa estar paga pra manter o bloqueio
                $recurrence->getId()
            );

            $created++;
        }

        return [
            'invoices_created'          => $created,
            'invoices_skipped_existing' => $skipped,
        ];
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

    private function parseDate(string $value, string $field): DateTime {
        $date = DateTime::createFromFormat('Y-m-d', $value);

        if (!$date) {
            throw new ValidationException([$field => 'Data inválida, use o formato AAAA-MM-DD']);
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

<?php

namespace App\Services;

use App\Models\Appointment;

use App\Repositories\AppointmentRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\UserRepository;

use App\Exceptions\ValidationException;

use App\Exceptions\appointment\AppointmentConflictException;
use App\Exceptions\appointment\AppointmentNotFoundException;
use App\Exceptions\appointment\PastAppointmentException;
use App\Exceptions\appointment\NoShowTimeException;
use App\Exceptions\appointment\RecurrenceLimitExceededException;

use App\Exceptions\user\UserNotFoundException;
use App\Exceptions\user\InactiveUserException;
use App\Exceptions\user\InvalidUserRoleException;

use App\Exceptions\procedure\ProcedureNotFoundException;
use App\Exceptions\procedure\InactiveProcedureException;
use InvalidArgumentException;
use DomainException;

use DateTime;


/**
 * AppointmentService - Camada de Serviço para Gerenciamento de Agendamentos
 * 
 * Responsabilidades:
 * - Validar paciente, profissional e procedimento antes de agendar
 * - Aplicar regras de negócio (cancelamento, no-show, recorrência)
 * - Traduzir exceções do banco (SIGNAL SQLSTATE 45000) para exceções de negócio
 * - Não duplicar lógicas que o banco já garante via Triggers e Stored Procedures
 * 
 * NÃO faz:
 * - Verificação de conflito de horário (o banco faz via Trigger)
 * - Acesso direto ao banco (delega para AppointmentRepository)
 */
class AppointmentService { 
    private AppointmentRepository $appointmentRepository;
    private UserRepository $userRepository;
    private ServiceRepository $procedureRepository;

    /**
     * Safety limit para recorrências: máximo de 2 anos no futuro
     */
    private const MAX_RECURRENCE_YEARS = 2;

    /**
     * Tipos de recorrência aceitos
     */
    private const VALID_RECURRENCE_TYPES = ['semanal', 'quinzenal'];

    /**
     * Dias da semana válidos (0=Domingo, 1=Segunda ... 6=Sábado)
     */
    private const VALID_DAYS_OF_WEEK = [0, 1, 2, 3, 4, 5, 6];


    public function __construct(
        AppointmentRepository $appointmentRepository,
        UserRepository $userRepository,
        ServiceRepository $procedureRepository
    ) {
        $this->appointmentRepository = $appointmentRepository;
        $this->userRepository = $userRepository;
        $this->procedureRepository = $procedureRepository;
    }


    // =========================================================
    // CRIAÇÃO DE AGENDAMENTOS
    // =========================================================

    /**
     * Cria um agendamento único
     * 
     * @param array $data [
     *   'patient_id'      => int (required),
     *   'professional_id' => int (required),
     *   'service_id'      => int (required),
     *   'start_time'      => string|DateTime (required) ex: '2026-06-15 14:00:00',
     *   'notes'           => string (optional)
     * ]
     * @throws ValidationException        Se campos obrigatórios faltarem
     * @throws UserNotFoundException      Se paciente ou profissional não existirem
     * @throws InactiveUserException      Se paciente ou profissional estiverem inativos
     * @throws InvalidUserRoleException   Se o usuário não tiver a role correta
     * @throws ProcedureNotFoundException Se o procedimento não existir
     * @throws InactiveProcedureException Se o procedimento estiver inativo
     * @throws AppointmentConflictException Se houver conflito de horário
     * @return int ID do agendamento criado
     */
    public function createAppointment(array $data): int
    {
        // 1. Valida campos obrigatórios
        $this->validateRequiredFields($data, [
            'patient_id',
            'professional_id',
            'service_id',
            'start_time',
        ]);

        // 2. Valida e converte start_time
        $startTime = $this->parseDateTime($data['start_time']);

        // 3. Valida que o agendamento não é no passado
        $this->validateFutureDateTime($startTime, 'agendar');

        // 4. Valida paciente (existe, ativo e role correta)
        $this->validatePatient((int) $data['patient_id']);

        // 5. Valida profissional (existe, ativo e role correta)
        $this->validateProfessional((int) $data['professional_id']);

        // 6. Valida procedimento (existe e ativo)
        $this->validateProcedure((int) $data['service_id']);

        // 7. Chama Repository (que chama sp_create_appointment)
        // NÃO verificamos conflito de horário aqui — o Trigger do banco faz isso
        try {
            return $this->appointmentRepository->createUnique(
                patientId:      (int) $data['patient_id'],
                professionalId: (int) $data['professional_id'],
                serviceId:      (int) $data['service_id'],
                startTime:      $startTime,
                notes:          $data['notes'] ?? null
            );

        } catch (DomainException $e) {
            // Captura o erro 45000 do Trigger de conflito de horário
            if (str_contains($e->getMessage(), 'Horário indisponível')) {
                throw new AppointmentConflictException();
            }
            throw $e;
        }
    }

    /**
     * Cria uma recorrência de agendamentos
     * 
     * @param array $data [
     *   'patient_id'      => int (required),
     *   'professional_id' => int (required),
     *   'service_id'      => int (required),
     *   'type'            => string (required) 'semanal' ou 'quinzenal',
     *   'day_of_week'     => int (required) 0=Domingo ... 6=Sábado,
     *   'start_hour'      => string (required) 'HH:MM:SS',
     *   'start_date'      => string|DateTime (required),
     *   'end_date'        => string|DateTime (optional) NULL = sem fim definido,
     *   'notes'           => string (optional)
     * ]
     * @throws ValidationException
     * @throws UserNotFoundException
     * @throws InactiveUserException
     * @throws InvalidUserRoleException
     * @throws ProcedureNotFoundException
     * @throws InactiveProcedureException
     * @throws RecurrenceLimitExceededException Se end_date ultrapassar 2 anos
     * @throws AppointmentConflictException     Se houver conflito em alguma das datas
     * @return array ['recurrence_group_id' => int, 'sessoes_criadas' => int]
     */
    public function createRecurrence(array $data): array
    {
        // 1. Valida campos obrigatórios
        $this->validateRequiredFields($data, [
            'patient_id',
            'professional_id',
            'service_id',
            'type',
            'day_of_week',
            'start_hour',
            'start_date',
        ]);

        // 2. Valida tipo de recorrência
        if (!in_array($data['type'], self::VALID_RECURRENCE_TYPES)) {
            $valid = implode(', ', self::VALID_RECURRENCE_TYPES);
            throw new ValidationException(['type' => "Tipo de recorrência inválido. Aceitos: {$valid}"]);
        }

        // 3. Valida dia da semana
        if (!in_array((int) $data['day_of_week'], self::VALID_DAYS_OF_WEEK)) {
            throw new ValidationException(['day_of_week' => 'Dia da semana inválido. Use 0 (Domingo) a 6 (Sábado)']);
        }

        // 4. Valida e converte datas
        $startDate = $this->parseDate($data['start_date']);
        $endDate   = isset($data['end_date']) ? $this->parseDate($data['end_date']) : null;

        // 5. Valida que start_date não é no passado
        $this->validateFutureDate($startDate, 'criar recorrência');

        // 6. Safety limit: end_date não pode ultrapassar 2 anos no futuro
        if ($endDate !== null) {
            $this->validateRecurrenceLimit($endDate);
        }

        // 7. Valida start_date < end_date se ambas fornecidas
        if ($endDate !== null && $startDate >= $endDate) {
            throw new ValidationException(['end_date' => 'Data de fim deve ser posterior à data de início']);
        }

        // 8. Valida formato do horário (HH:MM:SS)
        $this->validateTimeFormat($data['start_hour']);

        // 9. Valida paciente, profissional e procedimento
        $this->validatePatient((int) $data['patient_id']);
        $this->validateProfessional((int) $data['professional_id']);
        $this->validateProcedure((int) $data['service_id']);

        // 10. Chama Repository (que chama sp_create_recurrence)
        // Conflito de horário é verificado pelo Trigger em cada INSERT da procedure
        try {
            return $this->appointmentRepository->createRecurrence(
                patientId:      (int) $data['patient_id'],
                professionalId: (int) $data['professional_id'],
                serviceId:      (int) $data['service_id'],
                type:           $data['type'],
                dayOfWeek:      (int) $data['day_of_week'],
                startHour:      $data['start_hour'],
                startDate:      $startDate,
                endDate:        $endDate,
                notes:          $data['notes'] ?? null
            );

        } catch (DomainException $e) {
            if (str_contains($e->getMessage(), 'Horário indisponível')) {
                throw new AppointmentConflictException(
                    "Conflito detectado em uma ou mais datas da recorrência. " .
                    "Verifique a disponibilidade do profissional no período informado."
                );
            }
            throw $e;
        }
    }


    // =========================================================
    // MUDANÇAS DE STATUS
    // =========================================================

    /**
     * Confirma um agendamento
     * 
     * @param int $appointmentId
     * @throws AppointmentNotFoundException
     * @return bool
     */
    public function confirmAppointment(int $appointmentId): bool
    {
        $appointment = $this->findOrFail($appointmentId);

        if (!$appointment->isScheduled()) {
            throw new DomainException(
                "Não é possível confirmar: status atual é '{$appointment->getStatus()}'"
            );
        }

        return $this->appointmentRepository->confirm($appointmentId);
    }

    /**
     * Marca agendamento como realizado
     * 
     * @param int $appointmentId
     * @throws AppointmentNotFoundException
     * @return bool
     */
    public function completeAppointment(int $appointmentId): bool
    {
        $appointment = $this->findOrFail($appointmentId);

        if (!in_array($appointment->getStatus(), ['scheduled', 'confirmed'])) {
            throw new DomainException(
                "Não é possível concluir: status atual é '{$appointment->getStatus()}'"
            );
        }

        return $this->appointmentRepository->complete($appointmentId);
    }

    /**
     * Cancela um agendamento
     * 
     * @param int $appointmentId
     * @param string $reason Motivo do cancelamento
     * @param bool $isAdmin Admins podem cancelar agendamentos passados
     * @throws AppointmentNotFoundException
     * @throws PastAppointmentException Se o agendamento já passou e não é admin
     * @throws DomainException Se status não permite cancelamento
     * @return bool
     */
    public function cancelAppointment(int $appointmentId, string $reason, bool $isAdmin = false): bool
    {
        $appointment = $this->findOrFail($appointmentId);

        // Regra de negócio: não cancelar agendamentos passados (a menos que admin)
        if ($appointment->isPast() && !$isAdmin) {
            throw new PastAppointmentException('cancelar');
        }

        if (!$appointment->canBeCancelled()) {
            throw new DomainException(
                "Não é possível cancelar: status atual é '{$appointment->getStatus()}'"
            );
        }

        return $this->appointmentRepository->cancel($appointmentId, $reason);
    }

    /**
     * Cancela todos os agendamentos futuros de uma recorrência
     * 
     * @param int $recurrenceGroupId
     * @param string $reason Motivo do cancelamento
     * @param DateTime|null $fromDate Data a partir da qual cancelar (null = hoje)
     * @param bool $isAdmin Admins podem cancelar a partir de datas passadas
     * @throws AppointmentNotFoundException Se grupo não existir ou não tiver sessões
     * @throws PastAppointmentException Se fromDate for passada e não for admin
     * @return int Número de sessões canceladas
     */
    public function cancelRecurrence(
        int $recurrenceGroupId,
        string $reason,
        ?DateTime $fromDate = null,
        bool $isAdmin = false
    ): int {
        $fromDate = $fromDate ?? new DateTime();

        // Regra de negócio: não cancelar a partir de datas passadas (a menos que admin)
        if ($fromDate < new DateTime() && !$isAdmin) {
            throw new PastAppointmentException('cancelar sessões passadas');
        }

        // Verifica se o grupo existe e tem sessões
        $appointments = $this->appointmentRepository->findByRecurrenceGroup($recurrenceGroupId, false);

        if (empty($appointments)) {
            throw new AppointmentNotFoundException($recurrenceGroupId);
        }

        return $this->appointmentRepository->cancelRecurrence($recurrenceGroupId, $fromDate, $reason);
    }

    /**
     * Marca agendamento como no-show (paciente não compareceu)
     * 
     * REGRA DE NEGÓCIO:
     * Só pode marcar como falta APÓS o horário do agendamento ter passado
     * 
     * @param int $appointmentId
     * @param string|null $reason Motivo (opcional)
     * @throws AppointmentNotFoundException
     * @throws NoShowTimeException Se o horário do agendamento ainda não passou
     * @throws DomainException Se status não permite no-show
     * @return bool
     */
    public function markAsNoShow(int $appointmentId, ?string $reason = null): bool
    {
        $appointment = $this->findOrFail($appointmentId);

        // Regra de negócio: só marcar como no-show se o horário já passou
        if ($appointment->isFuture()) {
            throw new NoShowTimeException();
        }

        if (!in_array($appointment->getStatus(), ['scheduled', 'confirmed'])) {
            throw new DomainException(
                "Não é possível marcar como no-show: status atual é '{$appointment->getStatus()}'"
            );
        }

        return $this->appointmentRepository->markAsNoShow($appointmentId, $reason);
    }


    // =========================================================
    // REAGENDAMENTO
    // =========================================================
 
    /**
     * Atualiza campos simples de um agendamento (notes, price)
     * 
     * NÃO altera horário — para isso use rescheduleAppointment()
     * NÃO altera status — para isso use confirm(), complete(), cancel()
     * 
     * @param Appointment $appointment Objeto com dados atualizados
     * @throws AppointmentNotFoundException Se agendamento não existir
     * @return bool
     */
    public function updateAppointment(Appointment $appointment): bool
    {
        if (!$appointment->getId() || !$this->appointmentRepository->findById($appointment->getId(), false)) {
            throw new AppointmentNotFoundException($appointment->getId() ?? 0);
        }
 
        return $this->appointmentRepository->update($appointment);
    }

    /**
     * Reagenda um agendamento (muda data/hora)
     * 
     * @param int $appointmentId
     * @param DateTime $newStartTime Nova data/hora
     * @param bool $isAdmin Admins podem reagendar sem restrições de prazo
     * @throws AppointmentNotFoundException
     * @throws PastAppointmentException Se novo horário for no passado
     * @throws AppointmentConflictException Se houver conflito no novo horário
     * @return bool
     */
    public function rescheduleAppointment(int $appointmentId, DateTime $newStartTime, bool $isAdmin = false): bool
    {
        $appointment = $this->findOrFail($appointmentId);

        // Verifica se o agendamento pode ser alterado
        if (!$appointment->isPending()) {
            throw new DomainException(
                "Não é possível reagendar: status atual é '{$appointment->getStatus()}'"
            );
        }

        // Valida que o novo horário não é no passado
        if (!$isAdmin) {
            $this->validateFutureDateTime($newStartTime, 'reagendar');
        }

        // Cria array com os dados atuais + novo horário
        $updatedData = $appointment->toArray();
        $updatedData['start_time'] = $newStartTime->format('Y-m-d H:i:s');
        $updatedData['end_time']   = $newStartTime->format('Y-m-d H:i:s'); // Recalculado pelo Trigger

        $updatedAppointment = new Appointment($updatedData);

        // Conflito de horário verificado pelo Trigger no UPDATE
        try {
            return $this->appointmentRepository->update($updatedAppointment);

        } catch (DomainException $e) {
            if (str_contains($e->getMessage(), 'Horário indisponível')) {
                throw new AppointmentConflictException();
            }
            throw $e;
        }
    }


    // =========================================================
    // CONSULTAS
    // =========================================================

    /**
     * Busca agendamento por ID com relacionamentos carregados
     * 
     * @param int $appointmentId
     * @param bool $loadRelations
     * @throws AppointmentNotFoundException
     * @return Appointment
     */
    public function getAppointmentById(int $appointmentId, bool $loadRelations = true): Appointment
    {
        return $this->findOrFail($appointmentId, $loadRelations);
    }

    /**
     * Busca agendamentos de um paciente
     * 
     * @param int $patientId
     * @param bool $loadRelations
     * @throws UserNotFoundException Se paciente não existir
     * @return Appointment[]
     */
    public function getAppointmentsByPatient(int $patientId, bool $loadRelations = true): array
    {
        // Garante que o paciente existe
        if (!$this->userRepository->findById($patientId)) {
            throw new UserNotFoundException($patientId);
        }

        return $this->appointmentRepository->findByPatient($patientId, $loadRelations);
    }

    /**
     * Busca agendamentos de um profissional
     * 
     * @param int $professionalId
     * @param bool $loadRelations
     * @throws UserNotFoundException Se profissional não existir
     * @return Appointment[]
     */
    public function getAppointmentsByProfessional(int $professionalId, bool $loadRelations = true): array
    {
        // Garante que o profissional existe
        if (!$this->userRepository->findById($professionalId)) {
            throw new UserNotFoundException($professionalId);
        }

        return $this->appointmentRepository->findByProfessional($professionalId, $loadRelations);
    }

    /**
     * Busca a agenda de um profissional em um dia específico
     * Usado pelo calendário do frontend
     * 
     * @param int $professionalId
     * @param DateTime $date
     * @param bool $loadRelations
     * @throws UserNotFoundException
     * @return Appointment[]
     */
    public function getDailyAgenda(int $professionalId, DateTime $date, bool $loadRelations = true): array
    {
        if (!$this->userRepository->findById($professionalId)) {
            throw new UserNotFoundException($professionalId);
        }

        return $this->appointmentRepository->findByProfessionalAndDate($professionalId, $date, $loadRelations);
    }

    /**
     * Busca agendamentos por período (usado pelo calendário)
     * 
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param bool $loadRelations
     * @throws ValidationException Se intervalo for maior que 1 ano
     * @return Appointment[]
     */
    public function getAppointmentsByDateRange(
        DateTime $startDate,
        DateTime $endDate,
        bool $loadRelations = true
    ): array {
        // Evita consultas muito grandes
        $diffDays = (int) $startDate->diff($endDate)->days;

        if ($diffDays > 365) {
            throw new ValidationException(['date_range' => 'Intervalo de datas não pode ultrapassar 1 ano']);
        }

        if ($startDate > $endDate) {
            throw new ValidationException(['start_date' => 'Data de início deve ser anterior à data de fim']);
        }

        return $this->appointmentRepository->findByDateRange($startDate, $endDate, $loadRelations);
    }

    /**
     * Lista os próximos agendamentos
     * 
     * @param int $limit
     * @param bool $loadRelations
     * @return Appointment[]
     */
    public function getUpcomingAppointments(int $limit = 50, bool $loadRelations = true): array
    {
        if ($limit <= 0 || $limit > 200) {
            throw new ValidationException(['limit' => 'Limite deve estar entre 1 e 200']);
        }

        return $this->appointmentRepository->getUpcoming($limit, $loadRelations);
    }

    /**
     * Busca sessões de um grupo de recorrência
     * 
     * @param int $recurrenceGroupId
     * @param bool $loadRelations
     * @return Appointment[]
     */
    public function getRecurrenceSessions(int $recurrenceGroupId, bool $loadRelations = true): array
    {
        $appointments = $this->appointmentRepository->findByRecurrenceGroup($recurrenceGroupId, $loadRelations);

        if (empty($appointments)) {
            throw new AppointmentNotFoundException($recurrenceGroupId);
        }

        return $appointments;
    }

    /**
     * Verifica disponibilidade de horário
     * 
     * @param int $professionalId
     * @param DateTime $startTime
     * @param int $durationMinutes
     * @param int|null $excludeAppointmentId Para ignorar o próprio agendamento no reagendamento
     * @throws UserNotFoundException
     * @return bool
     */
    public function isTimeSlotAvailable(
        int $professionalId,
        DateTime $startTime,
        int $durationMinutes,
        ?int $excludeAppointmentId = null
    ): bool {
        if (!$this->userRepository->findById($professionalId)) {
            throw new UserNotFoundException($professionalId);
        }

        return $this->appointmentRepository->isTimeSlotAvailable(
            $professionalId,
            $startTime,
            $durationMinutes,
            $excludeAppointmentId
        );
    }

    /**
     * Retorna contadores de agendamentos por status
     * 
     * @return array ['scheduled' => int, 'confirmed' => int, ...]
     */
    public function getAppointmentStats(): array
    {
        $statuses = ['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'];
        $stats = [];

        foreach ($statuses as $status) {
            $stats[$status] = $this->appointmentRepository->countByStatus($status);
        }

        return $stats;
    }


    // =========================================================
    // SOFT DELETE
    // =========================================================

    /**
     * Remove permanentemente da visão (soft delete)
     * Mantém histórico no banco
     * 
     * @param int $appointmentId
     * @throws AppointmentNotFoundException
     * @return bool
     */
    public function deleteAppointment(int $appointmentId): bool
    {
        $this->findOrFail($appointmentId);

        return $this->appointmentRepository->delete($appointmentId);
    }

    /**
     * Restaura agendamento soft-deleted
     * 
     * @param int $appointmentId
     * @throws AppointmentNotFoundException
     * @return bool
     */
    public function restoreAppointment(int $appointmentId): bool
    {
        try {
            return $this->appointmentRepository->restore($appointmentId);

        } catch (InvalidArgumentException $e) {
            throw new AppointmentNotFoundException($appointmentId);
        }
    }


    // =========================================================
    // VALIDAÇÕES PRIVADAS
    // =========================================================

    /**
     * Busca agendamento ou lança exceção
     * 
     * @param int $appointmentId
     * @param bool $loadRelations
     * @throws AppointmentNotFoundException
     * @return Appointment
     */
    private function findOrFail(int $appointmentId, bool $loadRelations = false): Appointment
    {
        $appointment = $this->appointmentRepository->findById($appointmentId, $loadRelations);

        if (!$appointment) {
            throw new AppointmentNotFoundException($appointmentId);
        }

        return $appointment;
    }

    /**
     * Valida paciente para agendamento
     * 
     * @param int $patientId
     * @throws UserNotFoundException
     * @throws InactiveUserException
     * @throws InvalidUserRoleException
     * @return void
     */
    private function validatePatient(int $patientId): void
    {
        $patient = $this->userRepository->findById($patientId);

        if (!$patient) {
            throw new UserNotFoundException($patientId);
        }

        if (!$patient->isActive()) {
            throw new InactiveUserException('Paciente');
        }

        if (!$patient->isPatient()) {
            throw new InvalidUserRoleException('patient', $patient->getRole());
        }
    }

    /**
     * Valida profissional para agendamento
     * 
     * @param int $professionalId
     * @throws UserNotFoundException
     * @throws InactiveUserException
     * @throws InvalidUserRoleException
     * @return void
     */
    private function validateProfessional(int $professionalId): void
    {
        $professional = $this->userRepository->findById($professionalId);

        if (!$professional) {
            throw new UserNotFoundException($professionalId);
        }

        if (!$professional->isActive()) {
            throw new InactiveUserException('Profissional');
        }

        if (!$professional->isProfessional()) {
            throw new InvalidUserRoleException('professional', $professional->getRole());
        }
    }

    /**
     * Valida procedimento para agendamento
     * 
     * @param int $procedureId
     * @throws ProcedureNotFoundException
     * @throws InactiveProcedureException
     * @return void
     */
    private function validateProcedure(int $procedureId): void
    {
        $procedure = $this->procedureRepository->findById($procedureId);

        if (!$procedure) {
            throw new ProcedureNotFoundException($procedureId);
        }

        if (!$procedure->isActive()) {
            throw new InactiveProcedureException();
        }
    }

    /**
     * Valida que um DateTime é no futuro
     * 
     * @param DateTime $dateTime
     * @param string $action
     * @throws ValidationException
     * @return void
     */
    private function validateFutureDateTime(DateTime $dateTime, string $action = 'agendar'): void
    {
        if ($dateTime <= new DateTime()) {
            throw new ValidationException([
                'start_time' => "Não é possível {$action} para uma data/hora no passado"
            ]);
        }
    }

    /**
     * Valida que uma Date é no futuro ou hoje
     * 
     * @param DateTime $date
     * @param string $action
     * @throws ValidationException
     * @return void
     */
    private function validateFutureDate(DateTime $date, string $action = 'criar'): void
    {
        $today = new DateTime('today');

        if ($date < $today) {
            throw new ValidationException([
                'start_date' => "Não é possível {$action} com data de início no passado"
            ]);
        }
    }

    /**
     * Safety limit: valida que a end_date não ultrapassa o limite máximo
     * 
     * @param DateTime $endDate
     * @throws RecurrenceLimitExceededException
     * @return void
     */
    private function validateRecurrenceLimit(DateTime $endDate): void
    {
        $maxDate = (new DateTime())->modify('+' . self::MAX_RECURRENCE_YEARS . ' years');

        if ($endDate > $maxDate) {
            throw new RecurrenceLimitExceededException(self::MAX_RECURRENCE_YEARS);
        }
    }

    /**
     * Valida formato de horário HH:MM:SS
     * 
     * @param string $time
     * @throws ValidationException
     * @return void
     */
    private function validateTimeFormat(string $time): void
    {
        // Aceita HH:MM ou HH:MM:SS
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
            throw new ValidationException([
                'start_hour' => "Formato de horário inválido. Use HH:MM ou HH:MM:SS"
            ]);
        }

        [$hour, $minute] = explode(':', $time);

        if ((int) $hour < 0 || (int) $hour > 23 || (int) $minute < 0 || (int) $minute > 59) {
            throw new ValidationException([
                'start_hour' => "Horário inválido: hora deve ser entre 00 e 23, minuto entre 00 e 59"
            ]);
        }
    }

    /**
     * Valida campos obrigatórios
     * 
     * @param array $data
     * @param array $requiredFields
     * @throws ValidationException
     * @return void
     */
    private function validateRequiredFields(array $data, array $requiredFields): void
    {
        $missing = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $missing[$field] = "Campo {$field} é obrigatório";
            }
        }

        if (!empty($missing)) {
            throw new ValidationException($missing);
        }
    }

    /**
     * Converte string ou DateTime para objeto DateTime
     * 
     * @param string|DateTime $value
     * @throws ValidationException
     * @return DateTime
     */
    private function parseDateTime(string|DateTime $value): DateTime
    {
        if ($value instanceof DateTime) {
            return $value;
        }

        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $value)
           ?: DateTime::createFromFormat('Y-m-d H:i', $value);

        if (!$dt) {
            throw new ValidationException([
                'start_time' => "Formato de data/hora inválido. Use 'YYYY-MM-DD HH:MM:SS'"
            ]);
        }

        return $dt;
    }

    /**
     * Converte string ou DateTime para objeto DateTime (apenas data)
     * 
     * @param string|DateTime $value
     * @throws ValidationException
     * @return DateTime
     */
    private function parseDate(string|DateTime $value): DateTime
    {
        if ($value instanceof DateTime) {
            return $value;
        }

        $dt = DateTime::createFromFormat('Y-m-d', $value)
           ?: DateTime::createFromFormat('d/m/Y', $value);

        if (!$dt) {
            throw new ValidationException([
                'date' => "Formato de data inválido. Use 'YYYY-MM-DD' ou 'DD/MM/YYYY'"
            ]);
        }

        return $dt;
    }
}
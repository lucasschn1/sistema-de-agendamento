<?php
use App\Models\User;
use App\Models\Service;
use App\Models\Appointment;

use App\Repositories\AppointmentRepository;
use App\Repositories\UserRepository;
use App\Repositories\ServiceRepository;

use App\Services\AppointmentService;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

use App\Exceptions\user\UserNotFoundException;
use App\Exceptions\user\InactiveUserException;
use App\Exceptions\user\InvalidUserRoleException;

use App\Exceptions\procedure\ProcedureNotFoundException;

use App\Exceptions\appointment\AppointmentConflictException;
use App\Exceptions\appointment\AppointmentNotFoundException;
use App\Exceptions\appointment\NoShowTimeException;
use App\Exceptions\appointment\RecurrenceLimitExceededException;

use App\Exceptions\ValidationException;

/**
 * AppointmentServiceTest
 * 
 * Testa a camada de negócio do AppointmentService de forma isolada,
 * usando mocks para as dependências (repositórios).
 * 
 * IMPORTANTE:
 * Estes são testes UNITÁRIOS — nenhum banco de dados é necessário.
 * Cada teste verifica uma regra de negócio específica do Service.
 * 
 * Rodar: vendor/bin/phpunit tests/AppointmentServiceTest.php
 * Com cobertura: vendor/bin/phpunit --coverage-html coverage tests/AppointmentServiceTest.php
 */
class AppointmentServiceTest extends TestCase
{   
   
    // Mocks das dependências
    private AppointmentRepository&MockObject $appointmentRepositoryMock;
    private UserRepository&MockObject $userRepositoryMock;
    private ServiceRepository&MockObject $procedureRepositoryMock;

    // Subject Under Test
    private AppointmentService $service;

    // Dados reutilizáveis entre testes
    private array $validAppointmentData;
    private array $validRecurrenceData;


    /**
     * Executado antes de cada teste
     * Recria os mocks e o Service do zero para isolamento total
     */
    protected function setUp(): void
    {
        // Cria mocks das três dependências
        $this->appointmentRepositoryMock = $this->createMock(AppointmentRepository::class);
        $this->userRepositoryMock        = $this->createMock(UserRepository::class);
        $this->procedureRepositoryMock   = $this->createMock(ServiceRepository::class);

        // Injeta os mocks no Service
        $this->service = new AppointmentService(
            $this->appointmentRepositoryMock,
            $this->userRepositoryMock,
            $this->procedureRepositoryMock
        );

        // Dados válidos padrão para agendamento único
        $this->validAppointmentData = [
            'patient_id'      => 1,
            'professional_id' => 2,
            'service_id'      => 3,
            'start_time'      => (new DateTime('+1 day'))->format('Y-m-d 14:00:00'),
            'notes'           => 'Nota de teste',
        ];

        // Dados válidos padrão para recorrência
        $this->validRecurrenceData = [
            'patient_id'      => 1,
            'professional_id' => 2,
            'service_id'      => 3,
            'type'            => 'semanal',
            'day_of_week'     => 1, // Segunda-feira
            'start_hour'      => '10:00:00',
            'start_date'      => (new DateTime('+7 days'))->format('Y-m-d'),
            'end_date'        => (new DateTime('+3 months'))->format('Y-m-d'),
            'notes'           => 'Recorrência de teste',
        ];
    }


    // =========================================================
    // HELPERS PRIVADOS - FACTORIES DE MOCKS
    // =========================================================

    /**
     * Cria um mock de User paciente ativo
     */
    private function makeActivePatient(int $id = 1): MockObject
    {
        $mock = $this->createMock(User::class);
        $mock->method('getId')->willReturn($id);
        $mock->method('isActive')->willReturn(true);
        $mock->method('isPatient')->willReturn(true);
        $mock->method('isProfessional')->willReturn(false);
        $mock->method('getRole')->willReturn('patient');
        return $mock;
    }

    /**
     * Cria um mock de User profissional ativo
     */
    private function makeActiveProfessional(int $id = 2): MockObject
    {
        $mock = $this->createMock(User::class);
        $mock->method('getId')->willReturn($id);
        $mock->method('isActive')->willReturn(true);
        $mock->method('isProfessional')->willReturn(true);
        $mock->method('isPatient')->willReturn(false);
        $mock->method('getRole')->willReturn('professional');
        return $mock;
    }

    /**
     * Cria um mock de Service (procedimento) ativo
     */
    private function makeActiveProcedure(int $id = 3): MockObject
    {
        $mock = $this->createMock(Service::class);
        $mock->method('getId')->willReturn($id);
        $mock->method('isActive')->willReturn(true);
        return $mock;
    }

    /**
     * Cria um mock de Appointment com status e datas configuráveis
     */
    private function makeAppointment(
        int $id = 10,
        string $status = 'scheduled',
        bool $isFuture = true,
        bool $isPast = false,
        ?int $recurrenceGroupId = null,
        ?DateTime $startTime = null
    ): MockObject {
        $mock = $this->createMock(Appointment::class);
        $mock->method('getId')->willReturn($id);
        $mock->method('getStatus')->willReturn($status);
        $mock->method('isFuture')->willReturn($isFuture);
        $mock->method('isPast')->willReturn($isPast);
        $mock->method('isScheduled')->willReturn($status === 'scheduled');
        $mock->method('isConfirmed')->willReturn($status === 'confirmed');
        $mock->method('isCompleted')->willReturn($status === 'completed');
        $mock->method('isCancelled')->willReturn($status === 'cancelled');
        $mock->method('isNoShow')->willReturn($status === 'no_show');
        $mock->method('isPending')->willReturn(in_array($status, ['scheduled', 'confirmed']));
        $mock->method('isRecurring')->willReturn($recurrenceGroupId !== null);
        $mock->method('getRecurrenceGroupId')->willReturn($recurrenceGroupId);
        $mock->method('getStartTime')->willReturn($startTime ?? new DateTime('+1 day'));
        $mock->method('toArray')->willReturn([
            'id'               => $id,
            'patient_id'       => 1,
            'professional_id'  => 2,
            'service_id'       => 3,
            'start_time'       => (new DateTime('+1 day'))->format('Y-m-d H:i:s'),
            'end_time'         => (new DateTime('+1 day +50 minutes'))->format('Y-m-d H:i:s'),
            'duration_minutes' => 50,
            'price'            => 150.00,
            'paid'             => false,
            'status'           => $status,
            'recurrence_type'  => 'unico',
        ]);
        return $mock;
    }

    /**
     * Configura o repositório de usuários para retornar paciente e profissional válidos
     */
    private function setUpValidUsersAndProcedure(): void
    {
        $this->userRepositoryMock
            ->method('findById')
            ->willReturnMap([
                [1, false, $this->makeActivePatient(1)],
                [2, false, $this->makeActiveProfessional(2)],
            ]);

        $this->procedureRepositoryMock
            ->method('findById')
            ->willReturnMap([
                [3, false, $this->makeActiveProcedure(3)],
            ]);
    }


    // =========================================================
    // TESTES — createAppointment()
    // =========================================================

    public function testCreateAppointmentSuccessfully(): void
    {
        $this->setUpValidUsersAndProcedure();

        $this->appointmentRepositoryMock
            ->expects($this->once())
            ->method('createUnique')
            ->willReturn(42);

        $id = $this->service->createAppointment($this->validAppointmentData);

        $this->assertEquals(42, $id);
    }

    public function testCreateAppointmentThrowsIfMissingRequiredField(): void
    {
        $this->expectException(ValidationException::class);

        // Remove campo obrigatório
        unset($this->validAppointmentData['patient_id']);
        $this->service->createAppointment($this->validAppointmentData);
    }

    public function testCreateAppointmentThrowsIfStartTimeIsInPast(): void
    {
        $this->expectException(ValidationException::class);

        $this->validAppointmentData['start_time'] = (new DateTime('-1 day'))->format('Y-m-d H:i:s');
        $this->service->createAppointment($this->validAppointmentData);
    }

    public function testCreateAppointmentThrowsIfStartTimeIsInvalidFormat(): void
    {
        $this->expectException(ValidationException::class);

        $this->validAppointmentData['start_time'] = 'data-invalida';
        $this->service->createAppointment($this->validAppointmentData);
    }

    public function testCreateAppointmentThrowsIfPatientNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        // Paciente não existe
        $this->userRepositoryMock
            ->method('findById')
            ->willReturn(null);

        $this->service->createAppointment($this->validAppointmentData);
    }

    public function testCreateAppointmentThrowsIfPatientIsInactive(): void
    {
        $this->expectException(InactiveUserException::class);

        $inactivePatient = $this->makeActivePatient(1);
        $inactivePatient->method('isActive')->willReturn(false);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn($inactivePatient);

        $this->service->createAppointment($this->validAppointmentData);
    }

    public function testCreateAppointmentThrowsIfPatientHasWrongRole(): void
    {
        $this->expectException(InvalidUserRoleException::class);

        // Retorna um profissional no lugar do paciente
        $notAPatient = $this->makeActiveProfessional(1);
        $notAPatient->method('isPatient')->willReturn(false);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn($notAPatient);

        $this->service->createAppointment($this->validAppointmentData);
    }

    public function testCreateAppointmentThrowsIfProfessionalNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturnMap([
                [1, false, $this->makeActivePatient(1)],
                [2, false, null], // Profissional não existe
            ]);

        $this->service->createAppointment($this->validAppointmentData);
    }

    public function testCreateAppointmentThrowsIfProfessionalIsInactive(): void
    {
        $this->expectException(InactiveUserException::class);

        $inactiveProfessional = $this->makeActiveProfessional(2);
        $inactiveProfessional->method('isActive')->willReturn(false);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturnMap([
                [1, false, $this->makeActivePatient(1)],
                [2, false, $inactiveProfessional],
            ]);

        $this->service->createAppointment($this->validAppointmentData);
    }

    public function testCreateAppointmentThrowsIfProfessionalHasWrongRole(): void
    {
        $this->expectException(InvalidUserRoleException::class);

        // Retorna um paciente no lugar do profissional
        $notAProfessional = $this->makeActivePatient(2);
        $notAProfessional->method('isProfessional')->willReturn(false);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturnMap([
                [1, false, $this->makeActivePatient(1)],
                [2, false, $notAProfessional],
            ]);

        $this->service->createAppointment($this->validAppointmentData);
    }

    public function testCreateAppointmentThrowsIfProcedureNotFound(): void
    {
        $this->expectException(ProcedureNotFoundException::class);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturnMap([
                [1, false, $this->makeActivePatient(1)],
                [2, false, $this->makeActiveProfessional(2)],
            ]);

        $this->procedureRepositoryMock
            ->method('findById')
            ->willReturn(null);

        $this->service->createAppointment($this->validAppointmentData);
    }

    public function testCreateAppointmentThrowsIfProcedureIsInactive(): void
    {
        $this->expectException(InactiveProcedureException::class);

        $inactiveProcedure = $this->makeActiveProcedure(3);
        $inactiveProcedure->method('isActive')->willReturn(false);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturnMap([
                [1, false, $this->makeActivePatient(1)],
                [2, false, $this->makeActiveProfessional(2)],
            ]);

        $this->procedureRepositoryMock
            ->method('findById')
            ->willReturn($inactiveProcedure);

        $this->service->createAppointment($this->validAppointmentData);
    }

    public function testCreateAppointmentTranslatesConflictExceptionFromRepository(): void
    {
        $this->expectException(AppointmentConflictException::class);

        $this->setUpValidUsersAndProcedure();

        // Repositório lança DomainException com mensagem de conflito
        $this->appointmentRepositoryMock
            ->method('createUnique')
            ->willThrowException(new DomainException('Horário indisponível: já existe agendamento'));

        $this->service->createAppointment($this->validAppointmentData);
    }

    public function testCreateAppointmentAcceptsDateTimeObject(): void
    {
        $this->setUpValidUsersAndProcedure();

        $this->appointmentRepositoryMock
            ->method('createUnique')
            ->willReturn(99);

        // Passa DateTime em vez de string
        $this->validAppointmentData['start_time'] = new DateTime('+2 days');
        $id = $this->service->createAppointment($this->validAppointmentData);

        $this->assertEquals(99, $id);
    }


    // =========================================================
    // TESTES — createRecurrence()
    // =========================================================

    public function testCreateRecurrenceSuccessfully(): void
    {
        $this->setUpValidUsersAndProcedure();

        $expected = ['recurrence_group_id' => 5, 'sessoes_criadas' => 12];

        $this->appointmentRepositoryMock
            ->expects($this->once())
            ->method('createRecurrence')
            ->willReturn($expected);

        $result = $this->service->createRecurrence($this->validRecurrenceData);

        $this->assertEquals(5, $result['recurrence_group_id']);
        $this->assertEquals(12, $result['sessoes_criadas']);
    }

    public function testCreateRecurrenceThrowsIfTypeIsInvalid(): void
    {
        $this->expectException(ValidationException::class);

        $this->validRecurrenceData['type'] = 'mensal'; // Inválido
        $this->service->createRecurrence($this->validRecurrenceData);
    }

    public function testCreateRecurrenceThrowsIfDayOfWeekIsInvalid(): void
    {
        $this->expectException(ValidationException::class);

        $this->validRecurrenceData['day_of_week'] = 7; // Inválido (0-6)
        $this->service->createRecurrence($this->validRecurrenceData);
    }

    public function testCreateRecurrenceThrowsIfStartDateIsInPast(): void
    {
        $this->expectException(ValidationException::class);

        $this->validRecurrenceData['start_date'] = (new DateTime('-1 day'))->format('Y-m-d');
        $this->service->createRecurrence($this->validRecurrenceData);
    }

    public function testCreateRecurrenceThrowsIfEndDateExceedsTwoYears(): void
    {
        $this->expectException(RecurrenceLimitExceededException::class);

        $this->validRecurrenceData['end_date'] = (new DateTime('+3 years'))->format('Y-m-d');
        $this->service->createRecurrence($this->validRecurrenceData);
    }

    public function testCreateRecurrenceThrowsIfEndDateBeforeStartDate(): void
    {
        $this->expectException(ValidationException::class);

        $this->validRecurrenceData['start_date'] = (new DateTime('+2 months'))->format('Y-m-d');
        $this->validRecurrenceData['end_date']   = (new DateTime('+1 month'))->format('Y-m-d');

        $this->service->createRecurrence($this->validRecurrenceData);
    }

    public function testCreateRecurrenceThrowsIfTimeFormatIsInvalid(): void
    {
        $this->expectException(ValidationException::class);

        $this->validRecurrenceData['start_hour'] = '25:00:00'; // Hora inválida
        $this->service->createRecurrence($this->validRecurrenceData);
    }

    public function testCreateRecurrenceThrowsIfTimeFormatIsMalformed(): void
    {
        $this->expectException(ValidationException::class);

        $this->validRecurrenceData['start_hour'] = 'horario-errado';
        $this->service->createRecurrence($this->validRecurrenceData);
    }

    public function testCreateRecurrenceTranslatesConflictException(): void
    {
        $this->expectException(AppointmentConflictException::class);

        $this->setUpValidUsersAndProcedure();

        $this->appointmentRepositoryMock
            ->method('createRecurrence')
            ->willThrowException(new DomainException('Horário indisponível: conflito detectado'));

        $this->service->createRecurrence($this->validRecurrenceData);
    }

    public function testCreateRecurrenceWithNoEndDate(): void
    {
        $this->setUpValidUsersAndProcedure();

        $this->appointmentRepositoryMock
            ->method('createRecurrence')
            ->willReturn(['recurrence_group_id' => 7, 'sessoes_criadas' => 104]);

        // Remove end_date (recorrência sem fim)
        unset($this->validRecurrenceData['end_date']);

        $result = $this->service->createRecurrence($this->validRecurrenceData);
        $this->assertEquals(7, $result['recurrence_group_id']);
    }

    public function testCreateRecurrenceAcceptsQuinzenalType(): void
    {
        $this->setUpValidUsersAndProcedure();

        $this->appointmentRepositoryMock
            ->method('createRecurrence')
            ->willReturn(['recurrence_group_id' => 8, 'sessoes_criadas' => 6]);

        $this->validRecurrenceData['type'] = 'quinzenal';
        $result = $this->service->createRecurrence($this->validRecurrenceData);

        $this->assertEquals(8, $result['recurrence_group_id']);
    }


    // =========================================================
    // TESTES — confirmAppointment()
    // =========================================================

    public function testConfirmAppointmentSuccessfully(): void
    {
        $appointment = $this->makeAppointment(10, 'scheduled');

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->appointmentRepositoryMock
            ->expects($this->once())
            ->method('confirm')
            ->willReturn(true);

        $result = $this->service->confirmAppointment(10);
        $this->assertTrue($result);
    }

    public function testConfirmAppointmentThrowsIfNotFound(): void
    {
        $this->expectException(AppointmentNotFoundException::class);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn(null);

        $this->service->confirmAppointment(99);
    }

    public function testConfirmAppointmentThrowsIfAlreadyConfirmed(): void
    {
        $this->expectException(DomainException::class);

        $appointment = $this->makeAppointment(10, 'confirmed');

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->service->confirmAppointment(10);
    }

    public function testConfirmAppointmentThrowsIfCancelled(): void
    {
        $this->expectException(DomainException::class);

        $appointment = $this->makeAppointment(10, 'cancelled');

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->service->confirmAppointment(10);
    }


    // =========================================================
    // TESTES — completeAppointment()
    // =========================================================

    public function testCompleteAppointmentSuccessfully(): void
    {
        $appointment = $this->makeAppointment(10, 'confirmed');

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->appointmentRepositoryMock
            ->expects($this->once())
            ->method('complete')
            ->willReturn(true);

        $result = $this->service->completeAppointment(10);
        $this->assertTrue($result);
    }

    public function testCompleteAppointmentThrowsIfAlreadyCompleted(): void
    {
        $this->expectException(DomainException::class);

        $appointment = $this->makeAppointment(10, 'completed');

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->service->completeAppointment(10);
    }

    public function testCompleteAppointmentThrowsIfCancelled(): void
    {
        $this->expectException(DomainException::class);

        $appointment = $this->makeAppointment(10, 'cancelled');

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->service->completeAppointment(10);
    }


    // =========================================================
    // TESTES — markAsNoShow()
    // =========================================================

    public function testMarkAsNoShowSuccessfully(): void
    {
        // Agendamento passado + status permitido
        $appointment = $this->makeAppointment(10, 'confirmed', false, true);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->appointmentRepositoryMock
            ->expects($this->once())
            ->method('markAsNoShow')
            ->willReturn(true);

        $result = $this->service->markAsNoShow(10, 'Paciente não atendeu ao telefone');
        $this->assertTrue($result);
    }

    public function testMarkAsNoShowThrowsIfAppointmentIsInFuture(): void
    {
        $this->expectException(NoShowTimeException::class);

        // Agendamento futuro — não pode marcar como no-show ainda
        $appointment = $this->makeAppointment(10, 'scheduled', true, false);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->service->markAsNoShow(10);
    }

    public function testMarkAsNoShowThrowsIfStatusIsCompleted(): void
    {
        $this->expectException(DomainException::class);

        $appointment = $this->makeAppointment(10, 'completed', false, true);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->service->markAsNoShow(10);
    }

    public function testMarkAsNoShowThrowsIfNotFound(): void
    {
        $this->expectException(AppointmentNotFoundException::class);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn(null);

        $this->service->markAsNoShow(99);
    }


    // =========================================================
    // TESTES — rescheduleAppointment()
    // =========================================================

    public function testRescheduleAppointmentSuccessfully(): void
    {
        $appointment = $this->makeAppointment(10, 'scheduled', true, false);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->appointmentRepositoryMock
            ->expects($this->once())
            ->method('update')
            ->willReturn(true);

        $result = $this->service->rescheduleAppointment(10, new DateTime('+2 days'));
        $this->assertTrue($result);
    }

    public function testRescheduleAppointmentThrowsIfNewTimeIsInPast(): void
    {
        $this->expectException(ValidationException::class);

        $appointment = $this->makeAppointment(10, 'scheduled');

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->service->rescheduleAppointment(10, new DateTime('-1 day'));
    }

    public function testRescheduleAppointmentThrowsIfNotPending(): void
    {
        $this->expectException(DomainException::class);

        $appointment = $this->makeAppointment(10, 'completed');
        $appointment->method('isPending')->willReturn(false);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->service->rescheduleAppointment(10, new DateTime('+1 day'));
    }

    public function testRescheduleTranslatesConflictException(): void
    {
        $this->expectException(AppointmentConflictException::class);

        $appointment = $this->makeAppointment(10, 'scheduled');

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->appointmentRepositoryMock
            ->method('update')
            ->willThrowException(new DomainException('Horário indisponível: conflito'));

        $this->service->rescheduleAppointment(10, new DateTime('+1 day'));
    }

    public function testAdminCanRescheduleToAPastTime(): void
    {
        $appointment = $this->makeAppointment(10, 'scheduled');

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->appointmentRepositoryMock
            ->method('update')
            ->willReturn(true);

        // Admin pode reagendar para datas passadas (ex: corrigir horário errado)
        $result = $this->service->rescheduleAppointment(10, new DateTime('-1 day'), true);
        $this->assertTrue($result);
    }


    // =========================================================
    // TESTES — Consultas
    // =========================================================

    public function testGetAppointmentByIdLoadsRelations(): void
    {
        $appointment = $this->makeAppointment(10);

        $this->appointmentRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with(10, true) // Garante que loadRelations=true
            ->willReturn($appointment);

        $result = $this->service->getAppointmentById(10, true);
        $this->assertEquals(10, $result->getId());
    }

    public function testGetAppointmentByIdThrowsIfNotFound(): void
    {
        $this->expectException(AppointmentNotFoundException::class);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn(null);

        $this->service->getAppointmentById(99);
    }

    public function testGetAppointmentsByPatientThrowsIfPatientNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn(null);

        $this->service->getAppointmentsByPatient(99);
    }

    public function testGetAppointmentsByDateRangeThrowsIfIntervalExceedsOneYear(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->getAppointmentsByDateRange(
            new DateTime('2025-01-01'),
            new DateTime('2026-06-01') // Mais de 1 ano de diferença
        );
    }

    public function testGetAppointmentsByDateRangeThrowsIfStartIsAfterEnd(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->getAppointmentsByDateRange(
            new DateTime('+2 months'),
            new DateTime('+1 month')
        );
    }

    public function testGetUpcomingAppointmentsThrowsIfLimitIsOutOfBounds(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->getUpcomingAppointments(201); // Acima do limite de 200
    }

    public function testGetRecurrenceSessionsThrowsIfGroupIsEmpty(): void
    {
        $this->expectException(AppointmentNotFoundException::class);

        $this->appointmentRepositoryMock
            ->method('findByRecurrenceGroup')
            ->willReturn([]);

        $this->service->getRecurrenceSessions(99);
    }


    // =========================================================
    // TESTES — isTimeSlotAvailable()
    // =========================================================

    public function testIsTimeSlotAvailableReturnsTrueWhenFree(): void
    {
        $this->userRepositoryMock
            ->method('findById')
            ->willReturn($this->makeActiveProfessional(2));

        $this->appointmentRepositoryMock
            ->method('isTimeSlotAvailable')
            ->willReturn(true);

        $result = $this->service->isTimeSlotAvailable(2, new DateTime('+1 day'), 50);
        $this->assertTrue($result);
    }

    public function testIsTimeSlotAvailableReturnsFalseWhenOccupied(): void
    {
        $this->userRepositoryMock
            ->method('findById')
            ->willReturn($this->makeActiveProfessional(2));

        $this->appointmentRepositoryMock
            ->method('isTimeSlotAvailable')
            ->willReturn(false);

        $result = $this->service->isTimeSlotAvailable(2, new DateTime('+1 day'), 50);
        $this->assertFalse($result);
    }

    public function testIsTimeSlotAvailableThrowsIfProfessionalNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->userRepositoryMock
            ->method('findById')
            ->willReturn(null);

        $this->service->isTimeSlotAvailable(99, new DateTime('+1 day'), 50);
    }


    // =========================================================
    // TESTES — Soft delete / restore
    // =========================================================

    public function testDeleteAppointmentSuccessfully(): void
    {
        $appointment = $this->makeAppointment(10);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->appointmentRepositoryMock
            ->expects($this->once())
            ->method('delete')
            ->with(10)
            ->willReturn(true);

        $result = $this->service->deleteAppointment(10);
        $this->assertEquals(1, $result);
    }

    public function testDeleteAppointmentThrowsIfNotFound(): void
    {
        $this->expectException(AppointmentNotFoundException::class);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn(null);

        $this->service->deleteAppointment(99);
    }

    public function testDeleteAppointmentThrowsOnInvalidScope(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->deleteAppointment(10, 'invalid-scope');
    }

    public function testDeleteAppointmentIgnoresScopeWhenNotRecurring(): void
    {
        // Sem recurrence_group_id — mesmo pedindo 'all', deve apagar só este
        $appointment = $this->makeAppointment(10, 'scheduled', true, false, null);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->appointmentRepositoryMock
            ->expects($this->once())
            ->method('delete')
            ->with(10)
            ->willReturn(true);

        $this->appointmentRepositoryMock
            ->expects($this->never())
            ->method('deleteRecurrenceGroup');

        $result = $this->service->deleteAppointment(10, 'all');
        $this->assertEquals(1, $result);
    }

    public function testDeleteAppointmentFutureScopeDeletesFromRecurrence(): void
    {
        $startTime = new DateTime('+1 day');
        $appointment = $this->makeAppointment(10, 'scheduled', true, false, 5, $startTime);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->appointmentRepositoryMock
            ->expects($this->once())
            ->method('deleteFromRecurrence')
            ->with(5, $startTime)
            ->willReturn(3);

        $result = $this->service->deleteAppointment(10, 'future');
        $this->assertEquals(3, $result);
    }

    public function testDeleteAppointmentAllScopeDeletesEntireRecurrence(): void
    {
        $appointment = $this->makeAppointment(10, 'scheduled', true, false, 5);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->appointmentRepositoryMock
            ->expects($this->once())
            ->method('deleteRecurrenceGroup')
            ->with(5)
            ->willReturn(6);

        $result = $this->service->deleteAppointment(10, 'all');
        $this->assertEquals(6, $result);
    }

    public function testRestoreAppointmentSuccessfully(): void
    {
        $this->appointmentRepositoryMock
            ->expects($this->once())
            ->method('restore')
            ->with(10)
            ->willReturn(true);

        $result = $this->service->restoreAppointment(10);
        $this->assertTrue($result);
    }

    public function testRestoreAppointmentThrowsIfNotFound(): void
    {
        $this->expectException(AppointmentNotFoundException::class);

        $this->appointmentRepositoryMock
            ->method('restore')
            ->willThrowException(new InvalidArgumentException('Não encontrado'));

        $this->service->restoreAppointment(99);
    }


    // =========================================================
    // TESTES — getAppointmentStats()
    // =========================================================

    public function testGetAppointmentStatsReturnsAllStatuses(): void
    {
        $this->appointmentRepositoryMock
            ->method('countByStatus')
            ->willReturnMap([
                ['scheduled', 5],
                ['confirmed', 3],
                ['completed', 20],
                ['cancelled', 2],
                ['no_show', 1],
            ]);

        $stats = $this->service->getAppointmentStats();

        $this->assertArrayHasKey('scheduled', $stats);
        $this->assertArrayHasKey('confirmed', $stats);
        $this->assertArrayHasKey('completed', $stats);
        $this->assertArrayHasKey('cancelled', $stats);
        $this->assertArrayHasKey('no_show', $stats);
        $this->assertEquals(5, $stats['scheduled']);
        $this->assertEquals(20, $stats['completed']);
    }
}
<?php
namespace Tests\Integration\Repositories;

use PHPUnit\Framework\TestCase;
use App\Repositories\AppointmentRepository;
use App\Repositories\UserRepository;
use App\Repositories\ServiceRepository;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Service;
use App\Database\Database;
use PDO;
use DateTime;
use DomainException;
use Exception;

class AppointmentRepositorytest extends TestCase {
    private PDO $pdo;
    private UserRepository $userRepo;
    private ServiceRepository $serviceRepo;
    private AppointmentRepository $appointmentRepo;

    // ID de registro criados nos testes (para limpeza depois)
    private array $createdUserIds = [];
    private array $createdServiceIds = [];
    private array $createdAppointmentIds = [];

    /**
     * Executado ANTES de cada teste
     * 
     * configura o ambiente limpo
     */
    protected function setUp(): void {
        // conexão com banco de dados teste
        $this->pdo = new PDO(
            'mysql:host=localhost;dbname=clinica_ame_tests;charset=utf8mb4',
            'root',
            '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        // Inicializa repositórios
        $this->userRepo = new UserRepository($this->pdo);
        $this->serviceRepo = new ServiceRepository($this->pdo);
        $this->appointmentRepo = new AppointmentRepository(
            $this->pdo,
            $this->userRepo,
            $this->serviceRepo
        );

        // Limpa os dados dos testes anteriores
        $this->cleanupTestData();
    }

    /**
     * Executado DEPOIS de cada teste
     * Limpa os dados criados
     */
    protected function tearDown(): void
    {
        $this->cleanupTestData();
    }

    // =========================================================
    // TESTES - CRIAÇÃO DE AGENDAMENTOS
    // =========================================================
 
    public function testCreateUniqueAppointment(): void
    {
        // Arrange: prepara dados necessários
        $patient = $this->createTestPatient();
        $professional = $this->createTestProfessional();
        $service = $this->createTestService();
 
        // Act: executa a ação
        $appointmentId = $this->appointmentRepo->createUnique(
            patientId: $patient->getId(),
            professionalId: $professional->getId(),
            serviceId: $service->getId(),
            startTime: new DateTime('2026-06-15 14:00:00'),
            notes: 'Teste de agendamento único'
        );
 
        // Assert: verifica o resultado
        $this->assertIsInt($appointmentId);
        $this->assertGreaterThan(0, $appointmentId);
 
        // Verifica se foi salvo no banco
        $appointment = $this->appointmentRepo->findById($appointmentId, false);
        $this->assertNotNull($appointment);
        $this->assertEquals('scheduled', $appointment->getStatus());
        $this->assertEquals('unico', $appointment->getRecurrenceType());
 
        $this->createdAppointmentIds[] = $appointmentId;
    }


    public function testCreateRecurrence(): void
    {
        $patient = $this->createTestPatient();
        $professional = $this->createTestProfessional();
        $service = $this->createTestService();
 
        // Cria recorrência semanal de 4 sessões
        $result = $this->appointmentRepo->createRecurrence(
            patientId: $patient->getId(),
            professionalId: $professional->getId(),
            serviceId: $service->getId(),
            type: 'semanal',
            dayOfWeek: 1, // Segunda-feira
            startHour: '10:00:00',
            startDate: new DateTime('2026-06-15'), // Próxima segunda
            endDate: new DateTime('2026-07-07 23:59:59'),   // 4 semanas depois
            notes: 'Recorrência de teste'
        );
 
        $this->assertIsArray($result);
        $this->assertArrayHasKey('recurrence_group_id', $result);
        $this->assertArrayHasKey('sessoes_criadas', $result);
        $this->assertGreaterThan(0, $result['recurrence_group_id']);
        $this->assertEquals(4, $result['sessoes_criadas']); // 4 segundas-feiras
 
        // Verifica se as sessões foram criadas
        $appointments = $this->appointmentRepo->findByRecurrenceGroup(
            $result['recurrence_group_id'],
            false
        );
 
        $this->assertCount(4, $appointments);
        
        foreach ($appointments as $apt) {
            $this->createdAppointmentIds[] = $apt->getId();
        }
    }

    public function testCreateAppointmentWithConflict(): void
    {
        $patient = $this->createTestPatient();
        $professional = $this->createTestProfessional();
        $service = $this->createTestService();
 
        $startTime = new DateTime('2026-06-20 15:00:00');
 
        // Cria primeiro agendamento
        $firstId = $this->appointmentRepo->createUnique(
            $patient->getId(),
            $professional->getId(),
            $service->getId(),
            $startTime
        );
        $this->createdAppointmentIds[] = $firstId;
 
        // Tenta criar segundo no mesmo horário (deve falhar)
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Horário indisponível');
 
        $this->appointmentRepo->createUnique(
            $patient->getId(),
            $professional->getId(),
            $service->getId(),
            $startTime
        );
    }

    // =========================================================
    // TESTES - BUSCA DE AGENDAMENTOS
    // =========================================================
 
    public function testFindById(): void
    {
        $patient = $this->createTestPatient();
        $professional = $this->createTestProfessional();
        $service = $this->createTestService();
 
        $appointmentId = $this->appointmentRepo->createUnique(
            $patient->getId(),
            $professional->getId(),
            $service->getId(),
            new DateTime('2026-06-25 10:00:00')
        );
        $this->createdAppointmentIds[] = $appointmentId;
 
        // Busca sem carregar relacionamentos
        $appointment = $this->appointmentRepo->findById($appointmentId, false);
        $this->assertNotNull($appointment);
        $this->assertEquals($appointmentId, $appointment->getId());
        $this->assertNull($appointment->getPatient()); // Não carregou
 
        // Busca carregando relacionamentos
        $appointmentWithRelations = $this->appointmentRepo->findById($appointmentId, true);
        $this->assertNotNull($appointmentWithRelations->getPatient());
        $this->assertNotNull($appointmentWithRelations->getProfessional());
        $this->assertNotNull($appointmentWithRelations->getService());
    }
 
    public function testFindByPatient(): void
    {
        $patient = $this->createTestPatient();
        $professional = $this->createTestProfessional();
        $service = $this->createTestService();
 
        // Cria 3 agendamentos para o mesmo paciente
        for ($i = 0; $i < 3; $i++) {
            $id = $this->appointmentRepo->createUnique(
                $patient->getId(),
                $professional->getId(),
                $service->getId(),
                new DateTime("2026-06-" . (15 + $i) . " 14:00:00")
            );
            $this->createdAppointmentIds[] = $id;
        }
 
        $appointments = $this->appointmentRepo->findByPatient($patient->getId(), false);
        $this->assertCount(3, $appointments);
    }
 
    public function testFindByDateRange(): void
    {
        $patient = $this->createTestPatient();
        $professional = $this->createTestProfessional();
        $service = $this->createTestService();
 
        // Agendamento dentro do período
        $id1 = $this->appointmentRepo->createUnique(
            $patient->getId(),
            $professional->getId(),
            $service->getId(),
            new DateTime('2026-07-10 10:00:00')
        );
 
        // Agendamento fora do período
        $id2 = $this->appointmentRepo->createUnique(
            $patient->getId(),
            $professional->getId(),
            $service->getId(),
            new DateTime('2026-08-10 10:00:00')
        );
 
        $this->createdAppointmentIds[] = $id1;
        $this->createdAppointmentIds[] = $id2;
 
        // Busca apenas julho
        $appointments = $this->appointmentRepo->findByDateRange(
            new DateTime('2026-07-01'),
            new DateTime('2026-07-31'),
            false
        );
 
        $this->assertCount(1, $appointments);
        $this->assertEquals($id1, $appointments[0]->getId());
    }
 
    public function testGetUpcoming(): void
    {
        $patient = $this->createTestPatient();
        $professional = $this->createTestProfessional();
        $service = $this->createTestService();
 
        // Agendamento futuro
        $futureId = $this->appointmentRepo->createUnique(
            $patient->getId(),
            $professional->getId(),
            $service->getId(),
            new DateTime('+7 days 14:00:00')
        );
 
        // Agendamento passado (não deve aparecer)
        $pastId = $this->appointmentRepo->createUnique(
            $patient->getId(),
            $professional->getId(),
            $service->getId(),
            new DateTime('-7 days 14:00:00')
        );
 
        $this->createdAppointmentIds[] = $futureId;
        $this->createdAppointmentIds[] = $pastId;
 
        $upcoming = $this->appointmentRepo->getUpcoming(10, false);
 
        $ids = array_map(fn($apt) => $apt->getId(), $upcoming);
        $this->assertContains($futureId, $ids);
        $this->assertNotContains($pastId, $ids);
    }
 
 
    // =========================================================
    // TESTES - ATUALIZAÇÃO DE STATUS
    // =========================================================
 
    public function testConfirmAppointment(): void
    {
        $patient = $this->createTestPatient();
        $professional = $this->createTestProfessional();
        $service = $this->createTestService();
 
        $appointmentId = $this->appointmentRepo->createUnique(
            $patient->getId(),
            $professional->getId(),
            $service->getId(),
            new DateTime('+1 day 14:00:00')
        );
        $this->createdAppointmentIds[] = $appointmentId;
 
        // Confirma
        $result = $this->appointmentRepo->confirm($appointmentId);
        $this->assertTrue($result);
 
        // Verifica
        $appointment = $this->appointmentRepo->findById($appointmentId, false);
        $this->assertEquals('confirmed', $appointment->getStatus());
    }
 
    public function testCancelAppointment(): void
    {
        $patient = $this->createTestPatient();
        $professional = $this->createTestProfessional();
        $service = $this->createTestService();
 
        $appointmentId = $this->appointmentRepo->createUnique(
            $patient->getId(),
            $professional->getId(),
            $service->getId(),
            new DateTime('+1 day 14:00:00')
        );
        $this->createdAppointmentIds[] = $appointmentId;
 
        // Cancela
        $result = $this->appointmentRepo->cancel($appointmentId, 'Paciente desistiu');
        $this->assertTrue($result);
 
        // Verifica
        $appointment = $this->appointmentRepo->findById($appointmentId, false);
        $this->assertEquals('cancelled', $appointment->getStatus());
        $this->assertEquals('Paciente desistiu', $appointment->getCancellationReason());
    }
 
    public function testMarkAsNoShow(): void
    {
        $patient = $this->createTestPatient();
        $professional = $this->createTestProfessional();
        $service = $this->createTestService();
 
        $appointmentId = $this->appointmentRepo->createUnique(
            $patient->getId(),
            $professional->getId(),
            $service->getId(),
            new DateTime('+1 day 14:00:00')
        );
        $this->createdAppointmentIds[] = $appointmentId;
 
        // Marca como no-show
        $result = $this->appointmentRepo->markAsNoShow($appointmentId);
        $this->assertTrue($result);
 
        // Verifica
        $appointment = $this->appointmentRepo->findById($appointmentId, false);
        $this->assertEquals('no_show', $appointment->getStatus());
    }
 
 
    // =========================================================
    // TESTES - PAGAMENTO
    // =========================================================
 
    public function testRegisterPayment(): void
    {
        $patient = $this->createTestPatient();
        $professional = $this->createTestProfessional();
        $service = $this->createTestService();
 
        $appointmentId = $this->appointmentRepo->createUnique(
            $patient->getId(),
            $professional->getId(),
            $service->getId(),
            new DateTime('-1 day 14:00:00')
        );
        $this->createdAppointmentIds[] = $appointmentId;
 
        // Marca como completado primeiro
        $this->appointmentRepo->complete($appointmentId);
 
        // Registra pagamento
        $result = $this->appointmentRepo->registerPayment(
            $appointmentId,
            'PIX',
            new DateTime()
        );
        $this->assertTrue($result);
 
        // Verifica
        $appointment = $this->appointmentRepo->findById($appointmentId, false);
        $this->assertTrue($appointment->isPaid());
        $this->assertEquals('PIX', $appointment->getPaymentMethod());
    }
 
    public function testGetUnpaid(): void
    {
        $patient = $this->createTestPatient();
        $professional = $this->createTestProfessional();
        $service = $this->createTestService();
 
        // Cria agendamento completado mas não pago
        $appointmentId = $this->appointmentRepo->createUnique(
            $patient->getId(),
            $professional->getId(),
            $service->getId(),
            new DateTime('-1 day 14:00:00')
        );
        $this->createdAppointmentIds[] = $appointmentId;
        $this->appointmentRepo->complete($appointmentId);
 
        $unpaid = $this->appointmentRepo->getUnpaid(false);
 
        $this->assertGreaterThan(0, count($unpaid));
        
        $found = false;
        foreach ($unpaid as $apt) {
            if ($apt->getId() === $appointmentId) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }
 
 
    // =========================================================
    // TESTES - VALIDAÇÕES
    // =========================================================
 
    public function testIsTimeSlotAvailable(): void
    {
        $patient = $this->createTestPatient();
        $professional = $this->createTestProfessional();
        $service = $this->createTestService();
 
        $startTime = new DateTime('2026-08-15 14:00:00');
 
        // Horário livre
        $available = $this->appointmentRepo->isTimeSlotAvailable(
            $professional->getId(),
            $startTime,
            50
        );
        $this->assertTrue($available);
 
        // Cria agendamento
        $appointmentId = $this->appointmentRepo->createUnique(
            $patient->getId(),
            $professional->getId(),
            $service->getId(),
            $startTime
        );
        $this->createdAppointmentIds[] = $appointmentId;
 
        // Agora não está mais disponível
        $notAvailable = $this->appointmentRepo->isTimeSlotAvailable(
            $professional->getId(),
            $startTime,
            50
        );
        $this->assertFalse($notAvailable);
    }
 
 
    // =========================================================
    // TESTES - SOFT DELETE
    // =========================================================
 
    public function testSoftDelete(): void
    {
        $patient = $this->createTestPatient();
        $professional = $this->createTestProfessional();
        $service = $this->createTestService();
 
        $appointmentId = $this->appointmentRepo->createUnique(
            $patient->getId(),
            $professional->getId(),
            $service->getId(),
            new DateTime('+1 day 14:00:00')
        );
 
        // Deleta
        $result = $this->appointmentRepo->delete($appointmentId);
        $this->assertTrue($result);
 
        // Não encontra mais (sem includeDeleted)
        $appointment = $this->appointmentRepo->findById($appointmentId, false, false);
        $this->assertNull($appointment);
 
        // Mas ainda existe no banco (com includeDeleted)
        $deletedAppointment = $this->appointmentRepo->findById($appointmentId, false, true);
        $this->assertNotNull($deletedAppointment);
        $this->assertTrue($deletedAppointment->isDeleted());
    }
 
    public function testRestoreDeleted(): void
    {
        $patient = $this->createTestPatient();
        $professional = $this->createTestProfessional();
        $service = $this->createTestService();
 
        $appointmentId = $this->appointmentRepo->createUnique(
            $patient->getId(),
            $professional->getId(),
            $service->getId(),
            new DateTime('+1 day 14:00:00')
        );
 
        // Deleta
        $this->appointmentRepo->delete($appointmentId);
 
        // Restaura
        $result = $this->appointmentRepo->restore($appointmentId);
        $this->assertTrue($result);
 
        // Agora encontra normalmente
        $appointment = $this->appointmentRepo->findById($appointmentId, false);
        $this->assertNotNull($appointment);
        $this->assertFalse($appointment->isDeleted());
 
        $this->createdAppointmentIds[] = $appointmentId;
    }
 
 
    // =========================================================
    // MÉTODOS AUXILIARES PARA CRIAR DADOS DE TESTE
    // =========================================================
 
    private function createTestPatient(): User
    {
        $user = new User([
            'name' => 'Paciente Teste ' . uniqid(),
            'email' => 'paciente_' . uniqid() . '@teste.com',
            'password' => 'senha123',
            'cpf' => $this->generateRandomCPF(),
            'phone' => '11-99999-' . rand(1000, 9999),
            'role' => 'patient',
            'active' => true,
        ]);
 
        $id = $this->userRepo->createPatient($user);
        $this->createdUserIds[] = $id;
 
        return $this->userRepo->findById($id);
    }
 
    private function createTestProfessional(): User
    {
        $user = new User([
            'name' => 'Profissional Teste ' . uniqid(),
            'email' => 'prof_' . uniqid() . '@teste.com',
            'password' => 'senha123',
            'cpf' => $this->generateRandomCPF(),
            'phone' => '11-98888-' . rand(1000, 9999),
            'role' => 'professional',
            'professional_type' => 'Psicólogo',
            'council_id' => 'CRP 06/' . rand(100000, 999999),
            'specialty' => 'TCC',
            'active' => true,
        ]);
 
        $id = $this->userRepo->createProfessional($user);
        $this->createdUserIds[] = $id;
 
        return $this->userRepo->findById($id);
    }
 
    private function createTestService(): Service
    {
        $service = new Service([
            'name' => 'Serviço Teste ' . uniqid(),
            'description' => 'Descrição do serviço de teste',
            'price' => 150.00,
            'duration_minutes' => 50,
            'category' => 'Individual',
            'active' => true,
        ]);
 
        $id = $this->serviceRepo->create($service);
        $this->createdServiceIds[] = $id;
 
        return $this->serviceRepo->findById($id);
    }
 
    private function generateRandomCPF(): string
    {
        return sprintf(
            '%03d.%03d.%03d-%02d',
            rand(100, 999),
            rand(100, 999),
            rand(100, 999),
            rand(10, 99)
        );
    }
 
    private function cleanupTestData(): void
    {
        try {
        // 1. Desativa temporariamente as travas de chaves estrangeiras
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        // 2. Limpa as tabelas completamente e reseta os contadores de ID (AUTO_INCREMENT)
        $this->pdo->exec("TRUNCATE TABLE appointments");
        $this->pdo->exec("TRUNCATE TABLE users");
        $this->pdo->exec("TRUNCATE TABLE services");

        // 3. Reativa as travas de segurança
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        // 4. Limpa os arrays de controle por precaução
        $this->createdAppointmentIds = [];
        $this->createdServiceIds = [];
        $this->createdUserIds = [];
        
    } catch (Exception $e) {
        error_log("Erro crítico na limpeza de dados: " . $e->getMessage());
    }
        /*
        // Deleta agendamentos
        foreach ($this->createdAppointmentIds as $id) {
            try {
                $this->pdo->exec("DELETE FROM appointments WHERE id = {$id}");
            } catch (Exception $e) {
                // Ignora erros (pode já ter sido deletado)
            }
        }
 
        // Deleta serviços
        foreach ($this->createdServiceIds as $id) {
            try {
                $this->pdo->exec("DELETE FROM services WHERE id = {$id}");
            } catch (Exception $e) {
                // Ignora
            }
        }
 
        // Deleta usuários
        foreach ($this->createdUserIds as $id) {
            try {
                $this->pdo->exec("DELETE FROM users WHERE id = {$id}");
            } catch (Exception $e) {
                // Ignora
            }
        }
 
        // Limpa arrays
        $this->createdAppointmentIds = [];
        $this->createdServiceIds = [];
        $this->createdUserIds = [];
        */
    }
}
?>
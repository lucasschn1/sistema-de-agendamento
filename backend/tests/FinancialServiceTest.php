<?php

namespace Tests;

use App\Services\FinancialService;
use App\Models\Appointment;

use App\Exceptions\financial\InvalidPaymentMethodException;
use App\Exceptions\appointment\AppointmentNotFoundException;
use App\Exceptions\financial\AlreadyPaidException;
use App\Exceptions\financial\InvalidPaymentStatusException;
use App\Exceptions\ValidationException;

use App\Repositories\AppointmentRepository;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * FinancialServiceTest
 * 
 * Testa a camada de negócio do FinancialService de forma isolada,
 * usando mocks para o AppointmentRepository.
 * 
 * Rodar: vendor/bin/phpunit tests/FinancialServiceTest.php
 */
class FinancialServiceTest extends TestCase
{
    /** @var AppointmentRepository&MockObject */
    private MockObject      $appointmentRepositoryMock;
    private FinancialService $service;


    protected function setUp(): void
    {
        $this->appointmentRepositoryMock = $this->createMock(AppointmentRepository::class);
        $this->service = new FinancialService($this->appointmentRepositoryMock);
    }


    // =========================================================
    // HELPERS PRIVADOS
    // =========================================================

    private function makeAppointment(
        int    $id      = 1,
        string $status  = 'completed',
        bool   $paid    = false,
        float  $price   = 150.00,
        string $method  = 'PIX',
        string $startTime = 'now'
    ): MockObject {
        $mock = $this->createMock(Appointment::class);
        $mock->method('getId')->willReturn($id);
        $mock->method('getStatus')->willReturn($status);
        $mock->method('isPaid')->willReturn($paid);
        $mock->method('getPrice')->willReturn($price);
        $mock->method('getPaymentMethod')->willReturn($paid ? $method : null);
        $mock->method('canBePaid')->willReturn(
            in_array($status, ['completed', 'confirmed']) && !$paid
        );
        $mock->method('getStartTime')->willReturn(new \DateTime($startTime));
        return $mock;
    }


    // =========================================================
    // TESTES — registerPayment()
    // =========================================================

    public function testRegisterPaymentSuccessfully(): void
    {
        $appointment = $this->makeAppointment(1, 'completed', false);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->appointmentRepositoryMock
            ->expects($this->once())
            ->method('registerPayment')
            ->willReturn(true);

        $result = $this->service->registerPayment(1, 'PIX');
        $this->assertTrue($result);
    }

    public function testRegisterPaymentThrowsIfMethodIsInvalid(): void
    {
        $this->expectException(InvalidPaymentMethodException::class);

        $this->service->registerPayment(1, 'Bitcoin');
    }

    public function testRegisterPaymentThrowsIfAppointmentNotFound(): void
    {
        $this->expectException(AppointmentNotFoundException::class);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn(null);

        $this->service->registerPayment(99, 'PIX');
    }

    public function testRegisterPaymentThrowsIfAlreadyPaid(): void
    {
        $this->expectException(AlreadyPaidException::class);

        $appointment = $this->makeAppointment(1, 'completed', true); // já pago

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->service->registerPayment(1, 'PIX');
    }

    public function testRegisterPaymentThrowsIfStatusIsScheduled(): void
    {
        $this->expectException(InvalidPaymentStatusException::class);

        $appointment = $this->makeAppointment(1, 'scheduled', false);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->service->registerPayment(1, 'PIX');
    }

    public function testRegisterPaymentThrowsIfStatusIsCancelled(): void
    {
        $this->expectException(InvalidPaymentStatusException::class);

        $appointment = $this->makeAppointment(1, 'cancelled', false);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->service->registerPayment(1, 'Dinheiro');
    }

    public function testRegisterPaymentAcceptsAllValidMethods(): void
    {
        $validMethods = ['PIX', 'Dinheiro', 'Cartão de Crédito', 'Cartão de Débito', 'Transferência'];

        foreach ($validMethods as $method) {
            $appointment = $this->makeAppointment(1, 'completed', false);

            $this->appointmentRepositoryMock
                ->method('findById')
                ->willReturn($appointment);

            $this->appointmentRepositoryMock
                ->method('registerPayment')
                ->willReturn(true);

            $result = $this->service->registerPayment(1, $method);
            $this->assertTrue($result, "Método '{$method}' deveria ser aceito");
        }
    }

    public function testRegisterPaymentAcceptsStringDate(): void
    {
        $appointment = $this->makeAppointment(1, 'confirmed', false);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->appointmentRepositoryMock
            ->method('registerPayment')
            ->willReturn(true);

        $result = $this->service->registerPayment(1, 'PIX', '2026-06-15');
        $this->assertTrue($result);
    }

    public function testRegisterPaymentAcceptsDateTimeObject(): void
    {
        $appointment = $this->makeAppointment(1, 'completed', false);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->appointmentRepositoryMock
            ->method('registerPayment')
            ->willReturn(true);

        $result = $this->service->registerPayment(1, 'PIX', new \DateTime());
        $this->assertTrue($result);
    }

    public function testRegisterPaymentThrowsIfDateFormatIsInvalid(): void
    {
        $this->expectException(ValidationException::class);

        $appointment = $this->makeAppointment(1, 'completed', false);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->service->registerPayment(1, 'PIX', 'data-invalida');
    }

    public function testRegisterPaymentTranslatesBankException(): void
    {
        $this->expectException(InvalidPaymentStatusException::class);

        $appointment = $this->makeAppointment(1, 'completed', false);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->appointmentRepositoryMock
            ->method('registerPayment')
            ->willThrowException(new \DomainException('status do agendamento não permite'));

        $this->service->registerPayment(1, 'PIX');
    }


    // =========================================================
    // TESTES — undoPayment()
    // =========================================================

    public function testUndoPaymentSuccessfully(): void
    {
        $appointment = $this->makeAppointment(1, 'completed', true);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->appointmentRepositoryMock
            ->expects($this->once())
            ->method('undoPayment')
            ->willReturn(true);

        $result = $this->service->undoPayment(1, 'Pagamento registrado no método errado');
        $this->assertTrue($result);
    }

    public function testUndoPaymentThrowsIfReasonIsEmpty(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->undoPayment(1, '');
    }

    public function testUndoPaymentThrowsIfAppointmentNotFound(): void
    {
        $this->expectException(AppointmentNotFoundException::class);

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn(null);

        $this->service->undoPayment(99, 'Motivo');
    }

    public function testUndoPaymentThrowsIfNotPaid(): void
    {
        $this->expectException(InvalidPaymentStatusException::class);

        $appointment = $this->makeAppointment(1, 'completed', false); // não pago

        $this->appointmentRepositoryMock
            ->method('findById')
            ->willReturn($appointment);

        $this->service->undoPayment(1, 'Motivo');
    }


    // =========================================================
    // TESTES — getSummaryByPeriod()
    // =========================================================

    public function testGetSummaryByPeriodReturnsCorrectTotals(): void
    {
        $appointments = [
            $this->makeAppointment(1, 'completed', true,  150.00, 'PIX'),
            $this->makeAppointment(2, 'completed', false, 150.00),
            $this->makeAppointment(3, 'cancelled', false, 200.00),
            $this->makeAppointment(4, 'no_show',   false, 150.00),
        ];

        $this->appointmentRepositoryMock
            ->method('findByDateRange')
            ->willReturn($appointments);

        $summary = $this->service->getSummaryByPeriod(
            new \DateTime('-30 days'),
            new \DateTime('now')
        );

        $this->assertEquals(4,      $summary['total_scheduled']);
        $this->assertEquals(2,      $summary['total_completed']);
        $this->assertEquals(1,      $summary['total_cancelled']);
        $this->assertEquals(1,      $summary['total_no_show']);
        $this->assertEquals(300.00, $summary['gross_revenue']);   // só completados
        $this->assertEquals(150.00, $summary['received']);         // só pagos
        $this->assertEquals(150.00, $summary['pending']);          // completado não pago
        $this->assertEquals(200.00, $summary['cancelled_value']);
    }

    public function testGetSummaryByPeriodGroupsByPaymentMethod(): void
    {
        $appointments = [
            $this->makeAppointment(1, 'completed', true, 150.00, 'PIX'),
            $this->makeAppointment(2, 'completed', true, 200.00, 'PIX'),
            $this->makeAppointment(3, 'completed', true, 100.00, 'Dinheiro'),
        ];

        $this->appointmentRepositoryMock
            ->method('findByDateRange')
            ->willReturn($appointments);

        $summary = $this->service->getSummaryByPeriod(
            new \DateTime('-30 days'),
            new \DateTime('now')
        );

        $this->assertArrayHasKey('PIX',      $summary['by_method']);
        $this->assertArrayHasKey('Dinheiro', $summary['by_method']);
        $this->assertEquals(350.00, $summary['by_method']['PIX']);
        $this->assertEquals(100.00, $summary['by_method']['Dinheiro']);
    }

    public function testGetSummaryByPeriodThrowsIfStartIsAfterEnd(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->getSummaryByPeriod(
            new \DateTime('+1 month'),
            new \DateTime('now')
        );
    }

    public function testGetSummaryByPeriodThrowsIfRangeExceedsOneYear(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->getSummaryByPeriod(
            new \DateTime('-2 years'),
            new \DateTime('now')
        );
    }


    // =========================================================
    // TESTES — getSummaryByMonth()
    // =========================================================

    public function testGetSummaryByMonthSuccessfully(): void
    {
        $this->appointmentRepositoryMock
            ->method('findByDateRange')
            ->willReturn([]);

        $summary = $this->service->getSummaryByMonth(2026, 6);

        $this->assertArrayHasKey('period', $summary);
        $this->assertArrayHasKey('gross_revenue', $summary);
    }

    public function testGetSummaryByMonthThrowsIfMonthIsInvalid(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->getSummaryByMonth(2026, 13); // mês inválido
    }

    public function testGetSummaryByMonthThrowsIfMonthIsZero(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->getSummaryByMonth(2026, 0);
    }

    public function testGetSummaryByMonthThrowsIfYearIsInvalid(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->getSummaryByMonth(1999, 6); // ano inválido
    }


    // =========================================================
    // TESTES — getAllowedPaymentMethods()
    // =========================================================

    public function testGetAllowedPaymentMethodsReturnsArray(): void
    {
        $methods = $this->service->getAllowedPaymentMethods();

        $this->assertIsArray($methods);
        $this->assertContains('PIX', $methods);
        $this->assertContains('Dinheiro', $methods);
        $this->assertContains('Cartão de Crédito', $methods);
        $this->assertContains('Cartão de Débito', $methods);
        $this->assertContains('Transferência', $methods);
    }


    // =========================================================
    // TESTES — getPendingPayments()
    // =========================================================

    public function testGetPendingPaymentsReturnsUnpaidCompleted(): void
    {
        $unpaid = [$this->makeAppointment(1, 'completed', false)];

        $this->appointmentRepositoryMock
            ->method('getUnpaid')
            ->willReturn($unpaid);

        $result = $this->service->getPendingPayments(false);
        $this->assertCount(1, $result);
    }
}
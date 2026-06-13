<?php

use App\Exceptions\appointment\AppointmentNotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\AppointmentRepository;

/**
 * FinancialService - Camada de Serviço para Gerenciamento Financeiro
 * 
 * Responsabilidades:
 * - Registrar e desfazer pagamentos com validação de método e status
 * - Relatórios financeiros 
 * - Resumo de caixa
 * 
 * NÃO faz:
 * - Acesso direto ao banco 
 * - Regras de agendamento 
 */
class FinancialService {
    private AppointmentRepository $appointmentRepository;

    /**
     * Método de pagamentos aceitos pela clínica
     * Centralizados para fácil manuntenção
     */
    private const ALLOWED_PAYMENT_METHOD = [
        'PIX',
        'Dinheiro',
        'Cartão de Crédito',
        'Cartão de Débito',
        'Tranferência',
    ];

    public function __construct(AppointmentRepository $appointmentRepository) {
        $this->appointmentRepository = $appointmentRepository;
    }

    // =========================================================
    // PAGAMENTOS
    // =========================================================

    /**
     * Registra pagamentos de um agendamento
     * 
     * @param int $appointmentId
     * @param string $method Método de pagamento
     * @param string|DateTime|null (null = hoje)
     * @throws AppointmentNotFoundException
     * @throws AlreadyPaidException
     * @throws InvalidPaymentMethodException
     * @throws InvalidPaymentStatusException
     * @return bool
     */
    public function registerPayment(
        int $appointmentId,
        string $method,
        string|DateTime|null $date = null
    ): bool {
        // 1 - valida método de pagamento
        $this->validatePaymentMethod($method);

        // 2 - converte e valida data
        $paymentDate = $this->resolvePaymentDate($date);

        // 3 - busca agendamentos
        $appointment = $this->appointmentRepository->findById($appointmentId, false);

        // 4 - verifica se foi pago
        if ($appoitment->isPaid()) {
            throw new AlreadyPaidException();
        }

        // 5 - verifica se status permite pagamento
        // regra: só paga sessões 'completed' ou 'confirmed'
        if (!$appointment->canBePaid()) {
            throw new InvalidPaymentStatusException(
                "Não é possível registrar pagamento: status '{$appointment->getStatus()}' não permite. " .
                "O agendamento deve estar como 'completed' ou 'confirmed'."
            );
        }

        // 6 - chama repository (que chama sp_register_payment)
        // banco valida novamente via SIGNAL SQLSTATE 4500
        try {
            return $this->appointmentRepository->registerPayment(
                $appointmentId,
                $method,
                $paymentDate
            );

        } catch (DomainException $e) {
            // traduz o erro do banco de dados
            if (str_contains($e->getMessage(), 'status do agendamento não permite')) {
                throw new InvalidPaymentMethodException();
            }
            throw $e;
        }
    }

    /**
     * Desfaz um pagamento de um agendamento
     * 
     * útil para correção de lançamento
     * 
     * @param int $appointmentId
     * @param string $reason (motivo do estorno -> OBRIGATÓRIO)
     * @throws AppointmentNotFoundException
     * @throws InvalidPaymentStatusException
     * @return bool
     * 
     */
    public function undoPayment(int $appointmentId, string $reason) {
        if (empty(trim($reason))) {
            throw new ValidationException(['reason' => 'Motivo do estorno é obrigatório']);
        }

        $appointment = $this->appointmentRepository->findById($appointmentId);

        if (!$appointment) {
            throw new AppointmentNotFoundException($appointmentId);
        }

        if (!$appointment->isPaid()) {
            throw new InvalidPaymentStatusException(
                "Não é possível estornar: agendamento #{$appointmentId} não está pago"
            );
        }

        try {
            $stmt = $this->appointmentRepository->undoPayment($appointment);

            // TODO: registrar log de auditoria com $reason e usuário que executou
            // EX: AuditLogRepository::log('payment_undone', $appointmentId, $reason)

            return $stmt;
 
        } catch (PDOException $e) {
            error_log("Erro ao desfazer oagamento #{$appointment}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Lista os métodos de pagamento aceitos pela clinica
     * 
     * @return string[]
     */
    public function getAllowedPaymentMethods(): array {
        return self::ALLOWED_PAYMENT_METHOD;
    }
}
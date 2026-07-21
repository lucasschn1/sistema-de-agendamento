<?php
namespace App\Services;

use App\Models\Appointment;

use App\Exceptions\appointment\AppointmentNotFoundException;
use App\Exceptions\ValidationException;
use App\Exceptions\financial\AlreadyPaidException;
use App\Exceptions\financial\InvalidPaymentStatusException;
use App\Exceptions\financial\InvalidPaymentMethodException;

use App\Repositories\AppointmentRepository;
use DateTime;
use DomainException;
use PDOException;

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
    private const ALLOWED_PAYMENT_METHODS = [
        'PIX',
        'Dinheiro',
        'Cartão de Crédito',
        'Cartão de Débito',
        'Transferência',
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
     * @param string|DateTime|null  - (null -> HOJE)
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

        // 4 - verifica se existe
        if (!$appointment) {
            throw new AppointmentNotFoundException($appointmentId);
        }

        // 5 - verifica se foi pago
        if ($appointment->isPaid()) {
            throw new AlreadyPaidException();
        }

        // 6 - verifica se status permite pagamento
        // regra: só paga sessões 'completed' ou 'confirmed'
        if (!$appointment->canBePaid()) {
            throw new InvalidPaymentStatusException(
                "Não é possível registrar pagamento: status '{$appointment->getStatus()}' não permite. " .
                "O agendamento deve estar como 'completed' ou 'confirmed'."
            );
        }

        // 7 - chama repository (que chama sp_register_payment)
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
                throw new InvalidPaymentStatusException();
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
            $stmt = $this->appointmentRepository->undoPayment($appointmentId);

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
        return self::ALLOWED_PAYMENT_METHODS;
    }

    // =========================================================
    // RELATÓRIOS FINANCEIROS
    // =========================================================

    /**
     * Lista todos os agendamentos com pagamentos pendentes
     * 
     * @param bool $loadRelations
     * @return Appointment[]
     */
    public function getPendingPayments(bool $loadRelations = true): array {
        return $this->appointmentRepository->getUnpaid($loadRelations);
    }

    /**
     * Resumo financeiro de um período
     *
     * IMPORTANTE — dois critérios de data coexistem de propósito aqui:
     * `gross_revenue`/`pending`/os contadores de status usam `start_time`
     * (quando a SESSÃO acontece), pois representam o que estava agendado
     * naquele período. Já `received`/`by_method` usam `payment_date` (quando
     * o PAGAMENTO foi registrado), reutilizando getPaidAggregates()/
     * getPaidRevenueByMethod() — os mesmos métodos que alimentam o Histórico
     * de Pagamentos — para que "recebido no mês" seja idêntico em todo o
     * módulo. Antes desta correção, `received` também usava `start_time`,
     * o que causava divergência: uma sessão de junho paga em julho contava
     * como "recebida em junho" aqui, mas aparecia em "julho" no Histórico.
     *
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @throws ValidationException Se intervalo for inválido ou maior que 1 ano
     * @return array [
     *   'period'           => string,
     *   'total_scheduled'  => int,
     *   'total_completed'  => int,
     *   'total_cancelled'  => int,
     *   'total_no_show'    => int,
     *   'gross_revenue'    => float  (sessões concluídas no período, cobrado ou não),
     *   'received'         => float  (pagamentos registrados no período — payment_date),
     *   'pending'          => float  (concluídas no período, ainda não pagas),
     *   'cancelled_value'  => float  (valor perdido por cancelamento),
     *   'by_method'        => array  (breakdown por método, pagamentos do período)
     * ]
     */
    public function getSummaryByPeriod(DateTime $startDate, DateTime $endDate): array {
        $this->validateDateRange($startDate, $endDate);

        $appointments = $this->appointmentRepository->findByDateRange($startDate, $endDate, false);

        $summary = [
            'period'          => $startDate->format('d/m/Y') . 'a' . $endDate->format('d/m/Y'),
            'total_scheduled' => 0,
            'total_completed' => 0,
            'total_cancelled' => 0,
            'total_no_show'   => 0,
            'gross_revenue'   => 0.0,
            'received'        => 0.0,
            'pending'         => 0.0,
            'cancelled_value' => 0.0,
            'by_method'       => [],
        ];

        foreach($appointments as $appointment) {
            $summary['total_scheduled']++;

            switch ($appointment->getStatus()) {
                case 'completed' :
                    $summary['total_completed']++;
                    $summary['gross_revenue'] += $appointment->getPrice();

                    if (!$appointment->isPaid()) {
                        $summary['pending'] += $appointment->getPrice();
                    }
                    break;

                case 'cancelled':
                    $summary['total_cancelled']++;
                    $summary['cancelled_value'] += $appointment->getPrice();
                    break;

                case 'no_show':
                    $summary['total_no_show']++;
                    //no-show pode ou não ser cobrado
                    break;
            }
        }

        // "recebido" e "por método" vêm de payment_date, não de start_time —
        // mesma fonte usada pelo Histórico de Pagamentos (getMonthlyHistorySummary)
        $paidAggregates = $this->appointmentRepository->getPaidAggregates($startDate, $endDate);
        $summary['received']  = $paidAggregates['total_revenue'];
        $summary['by_method'] = $this->appointmentRepository->getPaidRevenueByMethod($startDate, $endDate);

        // arredondamos valores monetário
        $summary['gross_revenue']   = round($summary['gross_revenue'], 2);
        $summary['received']        = round($summary['received'], 2);
        $summary['pending']         = round($summary['pending'], 2);
        $summary['cancelled_value'] = round($summary['cancelled_value'], 2);

        return $summary;
    }

    /**
     * Resumo financeiro do mês atual
     * 
     * @return array
     */
    public function getCurrentMonthSummary(): array
    {
        $startDate = new DateTime('first day of this month 00:00:00');
        $endDate   = new DateTime('last day of this month 23:59:59');

        return $this->getSummaryByPeriod($startDate, $endDate);
    }

    /**
     * Resumo financeiro do dia atual
     *
     * @return array
     */
    public function getTodaySummary(): array
    {
        $startDate = new DateTime('today 00:00:00');
        $endDate   = new DateTime('today 23:59:59');

        return $this->getSummaryByPeriod($startDate, $endDate);
    }

    /**
     * Resumo financeiro de um mês específico
     * 
     * @param int $year  Ex: 2026
     * @param int $month Ex: 6
     * @throws ValidationException Se mês ou ano forem inválidos
     * @return array
     */
    public function getSummaryByMonth(int $year, int $month): array {
        if ($month < 1 || $month > 12) {
            throw new ValidationException(['month' => 'Mês deve ser entre 1 e 12']);
        }
 
        if ($year < 2000 || $year > 2100) {
            throw new ValidationException(['year' => 'Ano inválido']);
        }
 
        $startDate = new DateTime("{$year}-{$month}-01 00:00:00");
        $endDate   = (clone $startDate)->modify('last day of this month 23:59:59');
 
        return $this->getSummaryByPeriod($startDate, $endDate);
    }
 
    /**
     * Resumo financeiro por profissional em um período
     * 
     * @param int $professionalId
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @throws ValidationException
     * @return array [
     *   'professional_id' => int,
     *   'period'          => string,
     *   'total_sessions'  => int,
     *   'completed'       => int,
     *   'received'        => float,
     *   'pending'         => float,
     * ]
     */
    public function getSummaryByProfessional(
        int $professionalId,
        DateTime $startDate,
        DateTime $endDate
    ): array {
        $this->validateDateRange($startDate, $endDate);
 
        $appointments = $this->appointmentRepository->findByProfessional($professionalId, false);
 
        // Filtra pelo período manualmente (evita nova query)
        $filtered = array_filter(
            $appointments,
            fn($apt) => $apt->getStartTime() >= $startDate && $apt->getStartTime() <= $endDate
        );
 
        $summary = [
            'professional_id' => $professionalId,
            'period'          => $startDate->format('d/m/Y') . ' a ' . $endDate->format('d/m/Y'),
            'total_sessions'  => count($filtered),
            'completed'       => 0,
            'received'        => 0.0,
            'pending'         => 0.0,
        ];
 
        foreach ($filtered as $appointment) {
            if ($appointment->getStatus() === 'completed') {
                $summary['completed']++;
 
                if ($appointment->isPaid()) {
                    $summary['received'] += $appointment->getPrice();
                } else {
                    $summary['pending'] += $appointment->getPrice();
                }
            }
        }
 
        $summary['received'] = round($summary['received'], 2);
        $summary['pending']  = round($summary['pending'], 2);

        return $summary;
    }

    /**
     * Resumo financeiro agrupado por profissional, em um período
     * Mostra quanto cada profissional recebeu por sessão no período
     *
     * `received` vem de payment_date (getPaidRevenueByProfessional) — a mesma
     * fonte usada pelo Histórico — para que a soma de todos os profissionais
     * bata exatamente com o "recebido" do período em qualquer outro lugar do
     * módulo. `total_sessions`/`pending` continuam por `start_time`, pois
     * descrevem as sessões concluídas NO PERÍODO (agendadas naquele mês),
     * independente de quando o pagamento (se houver) foi registrado.
     *
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @throws ValidationException
     * @return array Lista de [
     *   'professional_id'   => int,
     *   'professional_name' => string,
     *   'total_sessions'    => int,
     *   'received'          => float,
     *   'pending'           => float,
     * ], ordenada pelo maior valor recebido
     */
    public function getSummaryByAllProfessionals(DateTime $startDate, DateTime $endDate): array {
        $this->validateDateRange($startDate, $endDate);

        $appointments = $this->appointmentRepository->findByDateRange($startDate, $endDate, true);
        $receivedByProfessional = $this->appointmentRepository->getPaidRevenueByProfessional($startDate, $endDate);

        $byProfessional = [];

        foreach ($appointments as $appointment) {
            if ($appointment->getStatus() !== 'completed') {
                continue;
            }

            $professional = $appointment->getProfessional();
            $id = $appointment->getProfessionalId();

            if (!isset($byProfessional[$id])) {
                $byProfessional[$id] = [
                    'professional_id'   => $id,
                    'professional_name' => $professional?->getName() ?? 'Desconhecido',
                    'total_sessions'    => 0,
                    'received'          => $receivedByProfessional[$id]['received'] ?? 0.0,
                    'pending'           => 0.0,
                ];
            }

            $byProfessional[$id]['total_sessions']++;

            if (!$appointment->isPaid()) {
                $byProfessional[$id]['pending'] += $appointment->getPrice();
            }
        }

        // profissionais que só receberam pagamento de sessões de OUTROS meses
        // (payment_date no período, mas start_time fora dele) não aparecem no
        // loop acima — inclui esses também, para a soma de "received" nunca
        // ficar menor que o total agregado do período
        foreach ($receivedByProfessional as $id => $data) {
            if (!isset($byProfessional[$id])) {
                $byProfessional[$id] = [
                    'professional_id'   => $id,
                    'professional_name' => $data['professional_name'],
                    'total_sessions'    => 0,
                    'received'          => $data['received'],
                    'pending'           => 0.0,
                ];
            }
        }

        $result = array_values($byProfessional);

        foreach ($result as &$row) {
            $row['received'] = round($row['received'], 2);
            $row['pending']  = round($row['pending'], 2);
        }

        usort($result, fn($a, $b) => $b['received'] <=> $a['received']);

        return $result;
    }

    /**
     * Agendamentos pagos em um período (extrato de caixa)
     * 
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param string|null $method Filtra por método de pagamento (null = todos)
     * @throws ValidationException
     * @return Appointment[]
     */
    public function getPaidAppointments(
        DateTime $startDate,
        DateTime $endDate,
        ?string $method = null,
        bool $loadRelations = true
    ): array {
        $this->validateDateRange($startDate, $endDate);
 
        if ($method !== null) {
            $this->validatePaymentMethod($method);
        }
 
        $appointments = $this->appointmentRepository->findByDateRange($startDate, $endDate, $loadRelations);
 
        return array_values(array_filter(
            $appointments,
            function ($apt) use ($method) {
                if (!$apt->isPaid()) return false;
                if ($method !== null && $apt->getPaymentMethod() !== $method) return false;
                return true;
            }
        ));
    }

    /**
     * Soma histórica de tudo já recebido, desde sempre — não é um resumo por
     * período, é usado apenas pelo KPI "Total recebido"
     */
    public function getAllTimeReceived(): float {
        return $this->appointmentRepository->sumAllPaidRevenue();
    }

    /**
     * Total de pagamentos registrados HOJE (payment_date = hoje), independente
     * de quando a sessão paga aconteceu — como não há data de vencimento, uma
     * sessão de dias atrás pode ser paga hoje e isso deve contar aqui.
     * Usa o mesmo agregador de pagamentos (getPaidAggregates) do Histórico,
     * só que filtrado para um único dia, para não duplicar lógica de soma.
     */
    public function getReceivedToday(): float {
        $today = new DateTime('today');
        return $this->appointmentRepository->getPaidAggregates($today, $today)['total_revenue'];
    }

    /**
     * Resumo de pagamentos registrados em uma competência (mês/ano), com
     * filtros opcionais combinados — fonte única usada tanto pelo KPI de
     * ticket médio do Resumo Financeiro (mês atual, sem filtros) quanto pelo
     * resumo do Histórico de Pagamentos (mês selecionado + filtros ativos),
     * para que os dois nunca calculem o mesmo número de formas diferentes
     *
     * @param array{professional_id?:?int,patient_id?:?int,service_id?:?int,method?:?string,search?:?string} $filters
     * @return array{
     *   year:int, month:int, total_revenue:float, payment_count:int,
     *   session_count:int, average_ticket:float, patient_count:int,
     *   professional_count:int
     * }
     */
    public function getMonthlyHistorySummary(int $year, int $month, array $filters = []): array {
        if ($month < 1 || $month > 12) {
            throw new ValidationException(['month' => 'Mês deve ser entre 1 e 12']);
        }

        if ($year < 2000 || $year > 2100) {
            throw new ValidationException(['year' => 'Ano inválido']);
        }

        $startDate = new DateTime("{$year}-{$month}-01 00:00:00");
        $endDate   = (clone $startDate)->modify('last day of this month 23:59:59');

        $aggregates = $this->appointmentRepository->getPaidAggregates($startDate, $endDate, $filters);

        return [
            'year'               => $year,
            'month'              => $month,
            'total_revenue'      => round($aggregates['total_revenue'], 2),
            'payment_count'      => $aggregates['payment_count'],
            // 1 agendamento = 1 pagamento nesse modelo, então sessões pagas == pagamentos
            'session_count'      => $aggregates['payment_count'],
            'average_ticket'     => round($aggregates['total_revenue'] / max($aggregates['payment_count'], 1), 2),
            'patient_count'      => $aggregates['patient_count'],
            'professional_count' => $aggregates['professional_count'],
        ];
    }

    /**
     * Lista paginada de pagamentos de uma competência, com os mesmos filtros
     * combinados usados por getMonthlyHistorySummary — mantém a listagem do
     * Histórico e o resumo do mês sempre consistentes entre si
     *
     * @param array{professional_id?:?int,patient_id?:?int,service_id?:?int,method?:?string,search?:?string} $filters
     * @return array{data:Appointment[],page:int,per_page:int,total:int,total_pages:int}
     */
    public function getFilteredPaymentHistory(int $year, int $month, array $filters = [], int $page = 1, int $perPage = 20): array {
        if ($month < 1 || $month > 12) {
            throw new ValidationException(['month' => 'Mês deve ser entre 1 e 12']);
        }

        if ($year < 2000 || $year > 2100) {
            throw new ValidationException(['year' => 'Ano inválido']);
        }

        $page    = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $offset  = ($page - 1) * $perPage;

        $startDate = new DateTime("{$year}-{$month}-01 00:00:00");
        $endDate   = (clone $startDate)->modify('last day of this month 23:59:59');

        $data  = $this->appointmentRepository->findPaidFiltered($startDate, $endDate, $filters, $perPage, $offset, true);
        $total = $this->appointmentRepository->countPaidFiltered($startDate, $endDate, $filters);

        return [
            'data'        => $data,
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Últimos pagamentos registrados, paginado (independente de período)
     * Usa paginação real no banco (LIMIT/OFFSET), não carrega tudo em memória
     *
     * @param int $page     Página atual (1-indexed)
     * @param int $perPage  Itens por página
     * @return array [
     *   'data'        => Appointment[],
     *   'page'        => int,
     *   'per_page'    => int,
     *   'total'       => int,
     *   'total_pages' => int,
     * ]
     */
    public function getPaidPaginated(int $page = 1, int $perPage = 10): array {
        $page    = max(1, $page);
        $perPage = max(1, min($perPage, 100)); // proteção contra per_page absurdo

        $offset = ($page - 1) * $perPage;

        $data  = $this->appointmentRepository->findPaidPaginated($perPage, $offset, true);
        $total = $this->appointmentRepository->countPaid();

        return [
            'data'        => $data,
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }


    // =========================================================
    // VALIDAÇÕES PRIVADAS
    // =========================================================
 
    /**
     * Valida método de pagamento
     * 
     * @param string $method
     * @throws InvalidPaymentMethodException
     * @return void
     */
    private function validatePaymentMethod(string $method): void {
        if (!in_array($method, self::ALLOWED_PAYMENT_METHODS, true)) {
            throw new InvalidPaymentMethodException($method, self::ALLOWED_PAYMENT_METHODS);
        }
    }
 
    /**
     * Resolve a data de pagamento a partir de string, DateTime ou null
     * 
     * @param string|DateTime|null $date
     * @throws ValidationException Se formato inválido
     * @return DateTime
     */
    private function resolvePaymentDate(string|DateTime|null $date): DateTime {
        if ($date === null) {
            return new DateTime();
        }
 
        if ($date instanceof DateTime) {
            return $date;
        }
 
        $parsed = DateTime::createFromFormat('Y-m-d', $date)
               ?: DateTime::createFromFormat('d/m/Y', $date);
 
        if (!$parsed) {
            throw new ValidationException([
                'date' => "Formato de data inválido. Use 'YYYY-MM-DD' ou 'DD/MM/YYYY'"
            ]);
        }
 
        return $parsed;
    }
 
    /**
     * Valida intervalo de datas para relatórios
     * 
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @throws ValidationException
     * @return void
     */
    private function validateDateRange(DateTime $startDate, DateTime $endDate): void {
        if ($startDate > $endDate) {
            throw new ValidationException([
                'start_date' => 'Data de início deve ser anterior à data de fim'
            ]);
        }
 
        $diffDays = (int) $startDate->diff($endDate)->days;
 
        if ($diffDays > 365) {
            throw new ValidationException([
                'date_range' => 'Intervalo de datas não pode ultrapassar 1 ano'
            ]);
        }
    }
}

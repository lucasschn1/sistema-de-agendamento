<?php
namespace App\Services;

use App\Models\Service;
use App\Repositories\ServiceRepository;
use App\Exceptions\procedure\ProcedureNotFoundException;
use App\Exceptions\procedure\InvalidDurationException;
use App\Exceptions\procedure\ProcedureInUseException;
use App\Exceptions\ValidationException;
use App\Exceptions\procedure\InactiveProcedureException;
use App\Exceptions\procedure\InvalidPriceException;

use PDO;
use PDOException;
use DomainException;
use InvalidArgumentException;

/**
 * ProcedureService - camada de serviço para gerenciamento de Procedures/Serviços
 * 
 * NOTA SOBRE A NOMENCLATURA:
 * Representa a tabela 'services' do banco de dados, mas usei o nome 'procedure' para
 * não conflitar com o padrão arquitetural de nomenclatura (Service)
 * 
 * Responsabilidades:
 * - Validações de regras negócios para procedimentos
 * - Verificar se procedimento pode ser inativado
 * - Gestão de catálogo de serviços oferecidos pela clinica
 * 
 * NÂO faz:
 * - Acesso ao banco de dados (delega para ServiceRepository)
 */
class ProcedureService {
    private ServiceRepository $procedureRepository;

    // injetar PDO para consultas específicas de validação
    private PDO $pdo;

    public function __construct(ServiceRepository $procedureRepository, PDO $pdo) {
        $this->procedureRepository = $procedureRepository;
    }

    // =========================================================
    // CRIAÇÃO DE PROCEDIMENTOS
    // =========================================================

    /**
     * Cria um novo procedimento
     *
     * NOTA: 'price' está obsoleto — o procedimento é só um cadastro do serviço
     * (nome, descrição, duração). O valor cobrado é definido individualmente
     * em cada agendamento (ver Appointment::price), não mais aqui.
     *
     * @param array $data [
     *   'name' => string (required),
     *   'description' => string (optional),
     *   'price' => float (opcional, obsoleto — não é mais usado em nenhum cálculo),
     *   'duration_minutes' => int (required),
     *   'category' => string (optional) ex: 'Individual', 'Casal', 'Familiar'
     * ]
     * @throws ValidationException
     * @throws InvalidPriceException
     * @throws InvalidDurationException
     * @throws DomainException Se nome já existir
     * @return int ID do procedimento criado
     */
    public function createProcedure(array $data): int {
        // validação de campos obrigatórios (preço não é mais obrigatório aqui)
        $this->validateRequiredFields($data, ['name', 'duration_minutes']);

        // validação de regras de negócios
        if (isset($data['price'])) {
            $this->validatePrice($data['price']);
        }
        $this->validateDuration($data['duration_minutes']);

        // sanitiza dados
        $data = $this->sanitizeProcedureData($data);

        // valida duplicidade de nome (Repository já faza isso, mas pode ser feito aqui também para feedback melhor)
        if ($this->procedureRepository->findByName($data['name'])) {
            throw new DomainException("Já existe um procedimento com esse nome '{$data['name']}'");
        }

        // cria service object
        $service = new Service($data);

        try {
            return $this->procedureRepository->create($service);
        } catch (DomainException $e) {
            // Repository pode lançar DomainException por duplicidade
            throw $e;
        }
    }

    // =========================================================
    // ATUALIZAÇÃO DE PROCEDIMENTOS
    // =========================================================

    /**
     * Atualiza dados do procedimento
     * 
     * @param int $procedureId
     * @param array $data Dados a atualizar
     * @throws ProcedureNotFoundException
     * @throws InvalidPriceException
     * @throws InvalidDurationException
     * @return bool
     */
    public function updateProcedure(int $procedureId, array $data): bool {
        // Busca procedimento existente
        $service = $this->procedureRepository->findById($procedureId);

        if (!$service) {
            throw new ProcedureNotFoundException($procedureId);
        }

        //valida price se fornecido
        if (isset($data['price'])) {
            $this->validatePrice($data['price']);
        }

        // valida duration se fornecido
        if (isset($data['duration_minute'])) {
            $this->validateDuration($data['duration_minutes']);
        }

        $data = $this->sanitizeProcedureData($data);

        // merge com dados existentes
        $updateData = array_merge($service->toArray(), $data);
        $updateData['id'] = $procedureId; // garante que o Id não mude  

        // cria novo Service object com dados atualizados
        $updateService = new Service($updateData);

        try {
            return $this->procedureRepository->update($updateService);
        } catch (DomainException $e) {
            // duplicidade de nome
            throw $e;
        }
    }

    /**
     * Atualiza apenas o preço de um Service
     * 
     * @param int $procedureId
     * @param float $newPrice
     * @throws ProcedureNotFoundException
     * @throws InvalidPriceException
     * @return bool
     */
    public function updatePrice(int $procedureId, float $newPrice): bool {
        // verifica se procedimento existe
        if (!$this->procedureRepository->findById($procedureId)) {
            throw new ProcedureNotFoundException($procedureId);
        }

        // valida preço 
        $this->validatePrice($newPrice);

        return $this->procedureRepository->updatePrice($procedureId, $newPrice);
    }

    // =========================================================
    // ATIVAÇÃO E DESATIVAÇÃO
    // =========================================================

    /**
     * Ativa um procedimento/serviço
     * 
     * @param int $procedureId
     * @throws ProcedureNotFoundException
     * @return bool
     */
    public function activateProcedure(int $procedureId): bool {
        // verifica se procedimento existe
        if (!$this->procedureRepository->findById($procedureId)) {
            throw new ProcedureNotFoundException($procedureId);
        }

        return $this->procedureRepository->activate($procedureId);
    }

    /**
     * Desativa um procedimento (soft delete)
     * 
     * REGRA DE NEGÓCIO:
     * Não premite desativar se existirem recorrências ativas usando esse procedimento
     * Agendamentos únicos já realizados não impedem a desativação
     * 
     * @param int $procedureId
     * @throws ProcedureNotFoundException
     * @throws ProcedureInUseException Se há recorrências ativas
     * @return bool
     */
    public function deactivateProcedure(int $procedureId): bool {
        // verifica se procedimento existe
        $service = $this->procedureRepository->findById($procedureId);

        if (!$service) {
            throw new ProcedureNotFoundException($procedureId);
        }

        // verifica se há recorrências usando esse procedimento
        if ($this->hasActiveRecurrences($procedureId)) {
            throw new ProcedureInUseException();
        }

        // Desativa (soft delete)
        return $this->procedureRepository->delete($procedureId);
    }

    /**
     * Restaura um procedimento desativao
     * 
     * @param int $procedureId
     * @throws ProcedureNotFoundException
     * @return bool
     */
    public function reactivateProcedure($procedureId): bool {
        try {
            return $this->procedureRepository->restore($procedureId);

        } catch(InvalidArgumentException $e){

            throw new ProcedureNotFoundException($procedureId);
        }
    }

    // =========================================================
    // CONSULTAS E BUSCAS
    // =========================================================

    /**
     * Busca procedimento por ID
     * 
     * @param int $procedureId
     * @param bool $includeDeleted
     * @throws ProcedureNotFoundException
     * @return Service
     */
    public function getProcedureById(int $procedureId, bool $includeDeleted = false): Service {
        $service = $this->procedureRepository->findById($procedureId, $includeDeleted);

        if (!$service) {
            throw new ProcedureNotFoundException($procedureId);
        }

        return $service;
    }

    /**
     * Lista de todos os procedimentos ativos
     * 
     * @return Service[]
     */
    public function getAllActiveProcedures(): array {
        return $this->procedureRepository->getAllActive();
    }

    /**
     * Lista todos os procedimentos (incluindo inativos)
     * 
     * @param bool $includeDeleted
     * @return Service[]
     */
    public function getAllProcedures($includeDeleted = false): array {
        return $this->procedureRepository->getAll($includeDeleted);
    }

    /**
     * Busca procedimento por categoria
     * 
     * @param string $category
     * @param bool $activeOnly
     * @return Service[]
     */
    public function getProceduresByCategory(string $category, bool $activeOnly = true): array {
        return $this->procedureRepository->findByCategory($category, $activeOnly);
    }

    /**
     * Busca procedimentos por faixa de preço
     * 
     * @param float $minPrice
     * @param float $maxPrice
     * @param bool $activeOnly
     * @return Service[]
     */
    public function getProcedureByPriceRange(float $minPrice, float $maxPrice, bool $activeOnly = true) {
        // valida preços
        $this->validatePrice($minPrice);
        $this->validatePrice($maxPrice);

        if ($minPrice > $maxPrice) {
            throw new ValidationException(['price' => 'Preço mínimo não pode ser maior que preço máximo']);
        }

        return $this->procedureRepository->findByPriceRange($minPrice, $maxPrice, $activeOnly);
    }

    /**
     * Busca procedimentos por duração
     * @param int $durationMinutes
     * @param bool $activeOnly
     * @return Service[]
     */
    public function getProcedureByDuration(int $durationMinutes, bool $activeOnly = true): array {
        // valida duração
        $this->validateDuration($durationMinutes);

        return $this->procedureRepository->findByDuration($durationMinutes, $activeOnly);
    }

    /**
     * Busca procedimentos por nome ou descrição (busca parcial)
     * @param string $query
     * @param bool $activeOnly
     * @return Service[]
     */
    public function searchProcedures(string $query, bool $activeOnly = true): array {
        if (strlen($query) < 2) {
            throw new ValidationException(['query' => 'Busca deve ter pelo menos 2 caracteres']);
        }

        return $this->procedureRepository->search($query, $activeOnly);
    }

    /**
     * Lista todas as categorias disponíveis
     * @return string[]
     */
    public function getAllCategories(): array {
        return $this->procedureRepository->getAllCategories();
    }

    // =========================================================
    // VALIDAÇÕES E REGRAS DE NEGÓCIO
    // =========================================================

    /**
     * Valida se um procedimento pode ser usado em um agendamento
     * @param int $procedureId
     * @throws ProcedureNotFoundException
     * @throws InactiveProcedureException
     * @return Service
     */
    public function validateProcedureForAppointment(int $procedureId): Service {
        $service = $this->procedureRepository->findById($procedureId);

        if (!$service) {
            throw new ProcedureNotFoundException($procedureId);
        }

        if (!$service->isActive()) {
            throw new InactiveProcedureException();
        }

        return $service;
    }

    /**
     * Verifica se procedimento tem recorrência ativa
     * REGRA DE NEGÓCIO:
     * Recorrências ativas são aquelas que ainda tem sessões futuras agendadadas
     * @param int $procedureId
     * @return bool
     */
    public function hasActiveRecurrences(int $procedureId): bool {
        try {
            // consulta recorrência ativa usando este procedimento
            // que ainda tem agendamento futuro não cancelados

            $sql = "SELECT COUNT(DISTINCT rg.id)
                    FROM recurrence_group rg
                    INNER JOIN appointments a ON a.recurrence_group_id = rg.id
                    WHERE rg.service_id = :service_id
                        AND rg.active = 1
                        AND a.start_time >= NOW()
                        AND a.status NOT IN ('cancelled', 'no_show')
                        AND a.deleted_at IS NULL";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['service_id' => $procedureId]);

            return (int) $stmt->fetchColumn() > 0;

        } catch (PDOException $e) {
            error_log("Erro ao verificar recorrências ativas: " . $e->getMessage());
            // Em caso de recorrência bloqueia a desativação por segurança
            return true;
        }
    }

    // =========================================================
    // MÉTODOS PRIVADOS - VALIDAÇÕES
    // =========================================================

    /**
     * Valida campos obrigatórios
     * @param array $data
     * @param array $requiredFields
     * @throws ValidationException
     * @return void
     */
    private function validateRequiredFields(array $data, array $requiredFields): void {
        $missing = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field])) && trim($data[$field] == '')) {
                $missing[$field] = "Campo {$field} é obrigatório";
            }
        }

        if (!empty($missing)) {
            throw new ValidationException($missing);
        }
    }

    /**
     * Valida preço
     * 
     * REGRA: preço deve ser >= 0 (permite procedimentos gratuitos)
     * 
     * @param float $price
     * @throws InvalidPriceException
     * @return void
     */
    private function validatePrice(float $price): void {
        if ($price < 0) {
            throw new InvalidPriceException();
        }

        // limite máximo de preço (ex: R$ 10.000)
        if ($price > 10000) {
            throw new ValidationException(['price' => 'Preço não pode ultrapassar R$ 10.000,00']);
        }
    }

    /**
     * Valida duração
     * 
     * REGRA: duração deve ser > 0
     * 
     * @param int $durationMinutes
     * @throws InvalidDurationException
     * @return void
     */
    private function validateDuration(int $durationMinutes): void {
        if ($durationMinutes <= 0 ){
            throw new InvalidDurationException();
        }

         // limite máximo de duração (ex: 8 horas = 480 minutos)
        if ($durationMinutes > 480) {
            throw new ValidationException(['duration' => 'Duração não pode ultrapassar 8 horas (480 minutos)']);
        }
    }

    /**
     * Sanitiza dados do procedimento
     * 
     * @param array $data
     * @return array
     */
    private function sanitizeProcedureData(array $data): array {
        // Trim 
        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        if (isset($data['description'])) {
            $data['description'] = trim($data['description']);
        }
 
        if (isset($data['category'])) {
            $data['category'] = trim($data['category']);
        }

        // Garante que price seja float
        if (isset($data['price'])) {
            $data['price'] = (float) $data['price'];
        }
 
        // Garante que duration seja int
        if (isset($data['duration_minutes'])) {
            $data['duration_minutes'] = (int) $data['duration_minutes'];
        }
 
        // Garante que active seja bool (se presente)
        if (isset($data['active'])) {
            $data['active'] = (bool) $data['active'];
        }
 
        return $data;
 
    }

    // =========================================================
    // ESTATÍSTICAS E RELATÓRIOS
    // =========================================================

    /**
     * Retorna estatísticas gerais dos procedimentos
     * 
     * @return array
     */
    public function getProcedureStats(): array {
        return [
            'total_active' => $this->procedureRepository->count(true, false),
            'total_inactive' => $this->procedureRepository->count(false, false) 
                                - $this->procedureRepository->count(true, false),
            'total_categories' => count($this->getAllCategories()),

        ];
    }

    /**
     * Retorna os procedimentos mais utulizados (com mais agendamentos)
     * 
     * @param int $limit
     * @return array Array associativo com dados agregados
     */
    public function getMostUsedProcedures(int $limit = 10): array {
        if ($limit <= 0 || $limit > 100) {
            throw new ValidationException(['limit' => 'Limite deve estar entre 1 e 100']);
        }

        return $this->procedureRepository->getMostUsed($limit);
    }

    /**
     * Calcula ticket médio por categoria
     * 
     * @return array ['categoria' => float (média de preços)]
     */
    public function getAveragePriceByCategory(): array {
        $categories = $this->getAllCategories();

        $average = [];

        foreach ($categories as $category) {
            $procedures = $this->getProceduresByCategory($category, true);

            if (empty($procedures)) {
                continue;
            }

            $total = 0;
            $count = count($procedures);

            foreach ($procedures as $procedure) {
                $total += $procedure->getPrice();
            }

            $average[$category] = $count > 0 ? round($total / $count, 2) : 0;
        }

        return $average;
    }

    /**
     * Retorna procedimentos que nunca foram agendados
     * 
     * @return Service[]
     */

    public function getUnusedProcedures(): array {
        try {
            $sql = "SELECT s.*
                    FROM services s
                    LEFT JOIN appointments a on s.id = a.service_id AND a.deleted_at IS NULL
                    WHERE s.deleted_at is NULL
                    AND s.active = 1
                    AND a.id IS NULL
                    ORDER BY s.created_at DESC";

            $stmt = $this->pdo->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn($data) => new Service($data), $results);

        } catch (PDOException $e) {
            error_log("Erro ao buscar procedimentos não utilizados: " . $e->getMessage());

            return [];
        }
    }
}
?>

  
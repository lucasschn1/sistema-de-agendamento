<?php

use App\Models\Service;
use App\Repositories\ServiceRepository;

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
     * @param array $data [
     *   'name' => string (required),
     *   'description' => string (optional),
     *   'price' => float (required),
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
        // validação de campos obrigatórios
        $this->validateRequiredFields($data, ['name'], ['price'], ['duration_minutes']);

        // validação de regras de negócios
        $this->validatePrice($data['price']);
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
     * @throws InvalidDurantionException
     * @return bool
     */
    public function updateProcedure(int $procedureId, array $data): bool {
        // Busca procedimento existente
        $service = $this->procedureRepository->findById($procedureId);
    }


}
?>
<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ProcedureService;
use App\Exceptions\ValidationException;
use App\Exceptions\ProcedureNotFoundException;
use App\Exceptions\ProcedureInUseException;
use App\Exceptions\InvalidPriceException;
use App\Exceptions\InvalidDurationException;

/**
 * ProcedureController - Gerencia o catálogo de serviços da clínica
 * 
 * Rotas autenticadas (admin + professional — leitura):
 *   GET /api/procedures          → index()
 *   GET /api/procedures/{id}     → show()
 * 
 * Rotas exclusivas admin (escrita):
 *   POST   /api/procedures                    → store()
 *   PUT    /api/procedures/{id}               → update()
 *   PATCH  /api/procedures/{id}/activate      → activate()
 *   PATCH  /api/procedures/{id}/deactivate    → deactivate()
 *   PATCH  /api/procedures/{id}/price         → updatePrice()
 *   DELETE /api/procedures/{id}               → destroy()
 *   GET    /api/procedures/categories         → categories()
 *   GET    /api/procedures/stats              → stats()
 */
class ProcedureController {
    private ProcedureService $procedureService;

    public function __construct(ProcedureService $procedureService) {
        $this->procedureService = $procedureService;
    }


    // =========================================================
    // LISTAGEM E BUSCA (admin + professional)
    // =========================================================

    /**
     * GET /api/procedures
     * Lista procedimentos com filtros opcionais
     * 
     * Query params:
     *   ?category=Individual|Casal|Familiar|Grupo|Avaliação
     *   ?active=true|false
     *   ?search=psicoterapia
     */
    public function index(Request $request): Response {
        try {
            $category   = $request->query('category');
            $activeOnly = $request->query('active', 'true') === 'true';
            $search     = $request->query('search');

            if ($search) {
                $procedures = $this->procedureService->searchProcedures($search, $activeOnly);
            } elseif ($category) {
                $procedures = $this->procedureService->getProceduresByCategory($category, $activeOnly);
            } else {
                $procedures = $activeOnly
                    ? $this->procedureService->getAllActiveProcedures()
                    : $this->procedureService->getAllProcedures();
            }

            $data = array_map(fn($p) => $p->toPublicArray(), $procedures);
            return Response::json($data);

        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * GET /api/procedures/{id}
     * Retorna um procedimento específico
     */
    public function show(Request $request): Response {
        try {
            $id        = (int) $request->param('id');
            $procedure = $this->procedureService->getProcedureById($id);

            return Response::json($procedure->toPublicArray());

        } catch (ProcedureNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * GET /api/procedures/categories
     * Lista todas as categorias disponíveis
     */
    public function categories(Request $request): Response {
        try {
            $categories = $this->procedureService->getAllCategories();
            return Response::json($categories);

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * GET /api/procedures/stats
     * Estatísticas e procedimentos mais utilizados
     */
    public function stats(Request $request): Response {
        try {
            $stats   = $this->procedureService->getProcedureStats();
            $topUsed = $this->procedureService->getMostUsedProcedures(10);
            $avgPrices = $this->procedureService->getAveragePriceByCategory();

            return Response::json([
                'summary'           => $stats,
                'most_used'         => $topUsed,
                'avg_price_by_category' => $avgPrices,
            ]);

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }


    // =========================================================
    // CRIAÇÃO E ATUALIZAÇÃO (admin)
    // =========================================================

    /**
     * POST /api/procedures
     * Cria um novo procedimento
     * 
     * Body esperado:
     * {
     *   "name": "Psicoterapia Individual",
     *   "description": "opcional",
     *   "price": 150.00,
     *   "duration_minutes": 50,
     *   "category": "Individual"
     * }
     */
    public function store(Request $request): Response {
        try {
            $id        = $this->procedureService->createProcedure($request->body());
            $procedure = $this->procedureService->getProcedureById($id);

            return Response::created($procedure->toPublicArray());

        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());

        } catch (InvalidPriceException $e) {
            return Response::error($e->getMessage(), 400, 'InvalidPriceException');

        } catch (InvalidDurationException $e) {
            return Response::error($e->getMessage(), 400, 'InvalidDurationException');

        } catch (\DomainException $e) {
            return Response::conflict($e->getMessage());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * PUT /api/procedures/{id}
     * Atualiza dados do procedimento
     */
    public function update(Request $request): Response {
        try {
            $id = (int) $request->param('id');
            $this->procedureService->updateProcedure($id, $request->body());

            $procedure = $this->procedureService->getProcedureById($id);
            return Response::json($procedure->toPublicArray(), 200, 'Procedimento atualizado');

        } catch (ProcedureNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (ValidationException $e) {
            return Response::validationError($e->getErrors());

        } catch (InvalidPriceException $e) {
            return Response::error($e->getMessage(), 400, 'InvalidPriceException');

        } catch (InvalidDurationException $e) {
            return Response::error($e->getMessage(), 400, 'InvalidDurationException');

        } catch (\DomainException $e) {
            return Response::conflict($e->getMessage());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * PATCH /api/procedures/{id}/price
     * Atualiza apenas o preço
     * 
     * Body esperado:
     * {
     *   "price": 160.00
     * }
     */
    public function updatePrice(Request $request): Response {
        try {
            $id    = (int) $request->param('id');
            $price = $request->input('price');

            if ($price === null) {
                return Response::validationError(['price' => 'Preço é obrigatório']);
            }

            $this->procedureService->updatePrice($id, (float) $price);

            $procedure = $this->procedureService->getProcedureById($id);
            return Response::json($procedure->toPublicArray(), 200, 'Preço atualizado');

        } catch (ProcedureNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (InvalidPriceException $e) {
            return Response::error($e->getMessage(), 400, 'InvalidPriceException');

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }


    // =========================================================
    // ATIVAÇÃO E DESATIVAÇÃO (admin)
    // =========================================================

    /**
     * PATCH /api/procedures/{id}/activate
     */
    public function activate(Request $request): Response {
        try {
            $id = (int) $request->param('id');
            $this->procedureService->activateProcedure($id);

            $procedure = $this->procedureService->getProcedureById($id);
            return Response::json($procedure->toPublicArray(), 200, 'Procedimento ativado');

        } catch (ProcedureNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * PATCH /api/procedures/{id}/deactivate
     * Bloqueado se houver recorrências ativas usando este procedimento
     */
    public function deactivate(Request $request): Response {
        try {
            $id = (int) $request->param('id');
            $this->procedureService->deactivateProcedure($id);

            return Response::json(null, 200, 'Procedimento desativado');

        } catch (ProcedureNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (ProcedureInUseException $e) {
            return Response::error($e->getMessage(), 400, 'ProcedureInUseException');

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }

    /**
     * DELETE /api/procedures/{id}
     * Soft delete do procedimento
     */
    public function destroy(Request $request): Response {
        try {
            $id = (int) $request->param('id');
            $this->procedureService->deactivateProcedure($id);

            return Response::noContent();

        } catch (ProcedureNotFoundException $e) {
            return Response::notFound($e->getMessage());

        } catch (ProcedureInUseException $e) {
            return Response::error($e->getMessage(), 400, 'ProcedureInUseException');

        } catch (\Throwable $e) {
            return Response::serverError();
        }
    }
}
<?php

/**
 * routes.php - Definição de todas as rotas da API
 * 
 * GRUPOS:
 *   Público        → sem autenticação
 *   /api           → AuthMiddleware (qualquer usuário logado)
 *   /api (admin)   → AuthMiddleware + RoleMiddleware:admin
 * 
 * CONVENÇÃO DE MÉTODOS NOS CONTROLLERS:
 *   index()   → lista recursos (GET /recurso)
 *   show()    → exibe um recurso (GET /recurso/{id})
 *   store()   → cria recurso (POST /recurso)
 *   update()  → atualiza recurso (PUT /recurso/{id})
 *   destroy() → remove recurso (DELETE /recurso/{id})
 */

use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\AppointmentController;
use App\Controllers\ProcedureController;
use App\Controllers\FinancialController;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;


// =========================================================
// ROTAS PÚBLICAS — sem autenticação
// =========================================================

$router->post('/auth/login',   [AuthController::class, 'login']);
$router->post('/auth/refresh', [AuthController::class, 'refresh']);


// =========================================================
// ROTAS PROTEGIDAS — qualquer usuário autenticado
// =========================================================

$router->group('/api', [AuthMiddleware::class], function ($router) {

    // ── Perfil do usuário logado ───────────────────────────
    // Disponível para admin e professional
    // Quando paciente puder logar, também usará estas rotas

    $router->get('/me',          [UserController::class, 'me']);       // GET  /api/me
    $router->put('/me',          [UserController::class, 'updateMe']); // PUT  /api/me
    $router->patch('/me/password',[UserController::class, 'updatePassword']); // PATCH /api/me/password


    // ── Agenda — professional + admin ─────────────────────

    // Lista agendamentos (admin vê todos, professional vê os seus — lógica no Controller)
    $router->get('/appointments',                    [AppointmentController::class, 'index']);   // GET  /api/appointments

    // Agendamento único
    $router->get('/appointments/{id}',               [AppointmentController::class, 'show']);    // GET  /api/appointments/{id}
    $router->post('/appointments',                   [AppointmentController::class, 'store']);   // POST /api/appointments
    $router->put('/appointments/{id}',               [AppointmentController::class, 'update']);  // PUT  /api/appointments/{id}

    // Ações de status
    $router->patch('/appointments/{id}/confirm',     [AppointmentController::class, 'confirm']);   // PATCH /api/appointments/{id}/confirm
    $router->patch('/appointments/{id}/complete',    [AppointmentController::class, 'complete']);  // PATCH /api/appointments/{id}/complete
    $router->patch('/appointments/{id}/cancel',      [AppointmentController::class, 'cancel']);    // PATCH /api/appointments/{id}/cancel
    $router->patch('/appointments/{id}/no-show',     [AppointmentController::class, 'noShow']);    // PATCH /api/appointments/{id}/no-show
    $router->patch('/appointments/{id}/reschedule',  [AppointmentController::class, 'reschedule']);// PATCH /api/appointments/{id}/reschedule

    // Recorrências
    $router->post('/appointments/recurrence',                    [AppointmentController::class, 'storeRecurrence']); // POST  /api/appointments/recurrence
    $router->patch('/appointments/recurrence/{groupId}/cancel',  [AppointmentController::class, 'cancelRecurrence']); // PATCH /api/appointments/recurrence/{groupId}/cancel
    $router->get('/appointments/recurrence/{groupId}',           [AppointmentController::class, 'showRecurrence']);  // GET   /api/appointments/recurrence/{groupId}

    // Disponibilidade (para o calendário do React)
    $router->get('/availability',                    [AppointmentController::class, 'availability']); // GET /api/availability?professional_id=1&date=2026-06-15


    // ── Procedimentos — leitura para professional, escrita só admin ──

    $router->get('/procedures',      [ProcedureController::class, 'index']); // GET /api/procedures

    // Rotas GET literais (/categories, /stats) precisam vir antes de /procedures/{id},
    // senão o Router (que casa na ordem de registro) intercepta com id="categories"/"stats"
    $router->get('/procedures/categories', [ProcedureController::class, 'categories'], [RoleMiddleware::class . ':admin']); // GET /api/procedures/categories
    $router->get('/procedures/stats',      [ProcedureController::class, 'stats'],      [RoleMiddleware::class . ':admin']); // GET /api/procedures/stats

    $router->get('/procedures/{id}', [ProcedureController::class, 'show']);  // GET /api/procedures/{id}


    // =========================================================
    // ROTAS EXCLUSIVAS DE ADMIN
    // =========================================================

    $router->group('', [RoleMiddleware::class . ':admin'], function ($router) {

        // ── Usuários ───────────────────────────────────────

        $router->get('/users',                    [UserController::class, 'index']);    // GET    /api/users
        // Rotas GET literais (/search, /stats) precisam vir antes de /users/{id},
        // senão o Router (que casa na ordem de registro) intercepta com id="search"/"stats"
        $router->get('/users/search',             [UserController::class, 'search']);   // GET    /api/users/search?name=joão
        $router->get('/users/stats',              [UserController::class, 'stats']);    // GET    /api/users/stats
        $router->get('/users/{id}',               [UserController::class, 'show']);     // GET    /api/users/{id}
        $router->post('/users/patient',           [UserController::class, 'storePatient']);      // POST   /api/users/patient
        $router->post('/users/professional',      [UserController::class, 'storeProfessional']); // POST   /api/users/professional
        $router->post('/users/admin',             [UserController::class, 'storeAdmin']);        // POST   /api/users/admin
        $router->put('/users/{id}',               [UserController::class, 'update']);   // PUT    /api/users/{id}
        $router->patch('/users/{id}/deactivate',  [UserController::class, 'deactivate']); // PATCH  /api/users/{id}/deactivate
        $router->patch('/users/{id}/restore',     [UserController::class, 'restore']);  // PATCH  /api/users/{id}/restore
        $router->patch('/users/{id}/reset-password', [UserController::class, 'resetPassword']); // PATCH /api/users/{id}/reset-password


        // ── Procedimentos (escrita) ────────────────────────

        $router->post('/procedures',              [ProcedureController::class, 'store']);      // POST   /api/procedures
        $router->put('/procedures/{id}',          [ProcedureController::class, 'update']);     // PUT    /api/procedures/{id}
        $router->patch('/procedures/{id}/activate',   [ProcedureController::class, 'activate']);   // PATCH  /api/procedures/{id}/activate
        $router->patch('/procedures/{id}/deactivate', [ProcedureController::class, 'deactivate']); // PATCH  /api/procedures/{id}/deactivate
        $router->patch('/procedures/{id}/price',  [ProcedureController::class, 'updatePrice']); // PATCH  /api/procedures/{id}/price
        $router->delete('/procedures/{id}',       [ProcedureController::class, 'destroy']);    // DELETE /api/procedures/{id}


        // ── Financeiro ────────────────────────────────────

        $router->post('/financial/payment',           [FinancialController::class, 'registerPayment']); // POST  /api/financial/payment
        $router->patch('/financial/payment/{id}/undo',[FinancialController::class, 'undoPayment']);     // PATCH /api/financial/payment/{id}/undo
        $router->get('/financial/pending',            [FinancialController::class, 'pending']);         // GET   /api/financial/pending
        $router->get('/financial/summary',            [FinancialController::class, 'summary']);         // GET   /api/financial/summary?start=2026-01-01&end=2026-06-30
        $router->get('/financial/summary/month',      [FinancialController::class, 'summaryByMonth']);  // GET   /api/financial/summary/month?year=2026&month=6
        $router->get('/financial/summary/current',    [FinancialController::class, 'currentMonth']);    // GET   /api/financial/summary/current
        $router->get('/financial/paid',               [FinancialController::class, 'paid']);            // GET   /api/financial/paid?start=2026-01-01&end=2026-06-30
        $router->get('/financial/methods',            [FinancialController::class, 'paymentMethods']);  // GET   /api/financial/methods


        // ── Agendamentos — ações exclusivas admin ─────────

        $router->delete('/appointments/{id}',     [AppointmentController::class, 'destroy']); // DELETE /api/appointments/{id}
        $router->patch('/appointments/{id}/restore', [AppointmentController::class, 'restore']); // PATCH /api/appointments/{id}/restore
    });
});


// =========================================================
// FUTURO — quando paciente puder logar
// =========================================================

// $router->group('/api/patient', [AuthMiddleware::class, RoleMiddleware::class . ':patient'], function ($router) {
//     $router->get('/appointments',       [PatientController::class, 'myAppointments']);
//     $router->get('/appointments/{id}',  [PatientController::class, 'showAppointment']);
//     $router->get('/profile',            [PatientController::class, 'profile']);
//     $router->put('/profile',            [PatientController::class, 'updateProfile']);
// });
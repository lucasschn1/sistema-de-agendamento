<?php

/**
 * dependencies.php - Container de Injeção de Dependências
 * 
 * Monta o grafo completo de dependências da aplicação.
 * Cada objeto é criado uma única vez e reutilizado (lazy singleton por closure).
 * 
 * ORDEM DE INSTANCIAÇÃO:
 *   Database → Repositories → Services → Controllers
 * 
 * USO no index.php:
 *   $container = require __DIR__ . '/../config/dependencies.php';
 *   $controller = $container['AppointmentController'];
 */

use App\Config\Database;
use App\Repositories\UserRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\AppointmentRepository;
use App\Services\UserService;
use App\Services\ProcedureService;
use App\Services\AppointmentService;
use App\Services\FinancialService;
use App\Services\EmailService;
use App\service\AuthService;
use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\ProcedureController;
use App\Controllers\AppointmentController;
use App\Controllers\FinancialController;
use App\Middleware\AuthMiddleware;

// =========================================================
// INFRAESTRUTURA
// =========================================================

$pdo = Database::getInstance()->getConnection();


// =========================================================
// REPOSITORIES
// =========================================================

$userRepository = new UserRepository($pdo);

$serviceRepository = new ServiceRepository($pdo);

$appointmentRepository = new AppointmentRepository(
    $pdo,
    $userRepository,
    $serviceRepository
);


// =========================================================
// SERVICES
// =========================================================

$userService = new UserService($userRepository);

$procedureService = new ProcedureService($serviceRepository, $pdo);

$appointmentService = new AppointmentService(
    $appointmentRepository,
    $userRepository,
    $serviceRepository
);

$financialService = new FinancialService($appointmentRepository);

$emailService = new EmailService();

$authService = new AuthService($userRepository); // criado na próxima etapa (JWT)


// =========================================================
// CONTROLLERS
// =========================================================

$authController        = new AuthController($authService);
$userController        = new UserController($userService);
$procedureController   = new ProcedureController($procedureService);
$appointmentController = new AppointmentController($appointmentService, $emailService);
$financialController   = new FinancialController($financialService);


// =========================================================
// RETORNO — array indexado pelo nome da classe
// =========================================================

return [
    // Infraestrutura
    'pdo'                            => $pdo,

    // Repositories
    App\Repositories\UserRepository::class        => $userRepository,
    App\Repositories\ServiceRepository::class     => $serviceRepository,
    App\Repositories\AppointmentRepository::class => $appointmentRepository,

    // Services
    App\Services\UserService::class        => $userService,
    App\Services\ProcedureService::class   => $procedureService,
    App\Services\AppointmentService::class => $appointmentService,
    App\Services\FinancialService::class   => $financialService,
    App\Services\EmailService::class       => $emailService,
    App\service\AuthService::class        => $authService,

    // Middlewares
    App\Middleware\AuthMiddleware::class  => new App\Middleware\AuthMiddleware($authService),
    App\Middleware\RoleMiddleware::class  => new App\Middleware\RoleMiddleware(),

    // Controllers
    App\Controllers\AuthController::class        => $authController,
    App\Controllers\UserController::class        => $userController,
    App\Controllers\ProcedureController::class   => $procedureController,
    App\Controllers\AppointmentController::class => $appointmentController,
    App\Controllers\FinancialController::class   => $financialController,
];
?>
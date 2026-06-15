<?php
namespace App\Config;

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
use ProcedureService;
use App\Services\AppointmentService;
use FinancialService;

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

$authService = new AuthService($userRepository); // criado na próxima etapa (JWT)


// =========================================================
// CONTROLLERS
// =========================================================

$authController        = new AuthController($authService);
$userController        = new UserController($userService);
$procedureController   = new ProcedureController($procedureService);
$appointmentController = new AppointmentController($appointmentService);
$financialController   = new FinancialController($financialService);


// =========================================================
// RETORNO — array indexado pelo nome da classe
// =========================================================

return [
    // Infraestrutura
    'pdo'                  => $pdo,

    // Repositories (disponíveis para uso direto se necessário)
    'UserRepository'       => $userRepository,
    'ServiceRepository'    => $serviceRepository,
    'AppointmentRepository'=> $appointmentRepository,

    // Services
    'UserService'          => $userService,
    'ProcedureService'     => $procedureService,
    'AppointmentService'   => $appointmentService,
    'FinancialService'     => $financialService,
    'AuthService'          => $authService,

    // Controllers
    'AuthController'       => $authController,
    'UserController'       => $userController,
    'ProcedureController'  => $procedureController,
    'AppointmentController'=> $appointmentController,
    'FinancialController'  => $financialController,
];
?>
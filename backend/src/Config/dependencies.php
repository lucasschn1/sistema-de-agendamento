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
use App\Repositories\RentalRoomRepository;
use App\Repositories\RentalBookingRepository;
use App\Repositories\RentalInvoiceRepository;
use App\Repositories\RentalRecurrenceRepository;
use App\Services\UserService;
use App\Services\ProcedureService;
use App\Services\AppointmentService;
use App\Services\FinancialService;
use App\Services\EmailService;
use App\Services\RentalRoomService;
use App\Services\RentalBookingService;
use App\Services\RentalBillingService;
use App\Services\RentalRecurrenceService;
use App\service\AuthService;
use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\ProcedureController;
use App\Controllers\AppointmentController;
use App\Controllers\FinancialController;
use App\Controllers\RentalRoomController;
use App\Controllers\RentalBookingController;
use App\Controllers\RentalRecurrenceController;
use App\Controllers\RentalInvoiceController;
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

$rentalRoomRepository = new RentalRoomRepository($pdo);

$rentalBookingRepository = new RentalBookingRepository($pdo, $userRepository, $rentalRoomRepository);

$rentalInvoiceRepository = new RentalInvoiceRepository($pdo, $userRepository);

$rentalRecurrenceRepository = new RentalRecurrenceRepository($pdo, $userRepository, $rentalRoomRepository);


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

$rentalRoomService = new RentalRoomService($rentalRoomRepository);

$rentalBookingService = new RentalBookingService($rentalBookingRepository, $rentalRoomRepository, $userRepository);

$rentalBillingService = new RentalBillingService($rentalBookingRepository, $rentalInvoiceRepository, $pdo);

$rentalRecurrenceService = new RentalRecurrenceService(
    $rentalRecurrenceRepository,
    $rentalBookingRepository,
    $rentalInvoiceRepository,
    $rentalRoomRepository,
    $userRepository,
    $pdo
);


// =========================================================
// CONTROLLERS
// =========================================================

$authController        = new AuthController($authService);
$userController        = new UserController($userService);
$procedureController   = new ProcedureController($procedureService);
$appointmentController = new AppointmentController($appointmentService, $emailService);
$financialController   = new FinancialController($financialService);
$rentalRoomController  = new RentalRoomController($rentalRoomService);
$rentalBookingController = new RentalBookingController($rentalBookingService);
$rentalRecurrenceController = new RentalRecurrenceController($rentalRecurrenceService);
$rentalInvoiceController = new RentalInvoiceController($rentalInvoiceRepository);


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
    App\Repositories\RentalRoomRepository::class  => $rentalRoomRepository,
    App\Repositories\RentalBookingRepository::class => $rentalBookingRepository,
    App\Repositories\RentalInvoiceRepository::class => $rentalInvoiceRepository,
    App\Repositories\RentalRecurrenceRepository::class => $rentalRecurrenceRepository,

    // Services
    App\Services\UserService::class        => $userService,
    App\Services\ProcedureService::class   => $procedureService,
    App\Services\AppointmentService::class => $appointmentService,
    App\Services\FinancialService::class   => $financialService,
    App\Services\EmailService::class       => $emailService,
    App\Services\RentalRoomService::class  => $rentalRoomService,
    App\Services\RentalBookingService::class => $rentalBookingService,
    App\Services\RentalBillingService::class => $rentalBillingService,
    App\Services\RentalRecurrenceService::class => $rentalRecurrenceService,
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
    App\Controllers\RentalRoomController::class  => $rentalRoomController,
    App\Controllers\RentalBookingController::class => $rentalBookingController,
    App\Controllers\RentalRecurrenceController::class => $rentalRecurrenceController,
    App\Controllers\RentalInvoiceController::class => $rentalInvoiceController,
];
?>
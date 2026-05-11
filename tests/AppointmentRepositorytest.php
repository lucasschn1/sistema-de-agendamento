<?php
namespace Tests\Integration\Repositories;

use PHPUnit\Framework\TestCase;
use App\Repositories\AppointmentRepository;
use App\Repositories\UserRepository;
use App\Repositories\ServiceRepository;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Service;
use App\Database\Database;
use PDO;
use DateTime;

class AppointmentRepositorytest extends TestCase {
    private PDO $pdo;
    private UserRepository $userRepo;
    private ServiceRepository $serviceRepo;
    private AppointmentRepository $appointmentRepo;

    // ID de registro criados nos testes (para limpeza depois)
    private array $createUserIds = [];
    private array $createServiceIds = [];
    private array $createAppointmentIds = [];

    /**
     * Executado ANTES de cada teste
     * 
     * configura o ambiente limpo
     */
    protected function setUp(): void {
        // conexão com banco de dados teste
        $this->pdo = new PDO(
            'mysql:host=localhost;dbname=agenda_clinica_;charset=utf8mb4',
            'root',
            '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
}
?>
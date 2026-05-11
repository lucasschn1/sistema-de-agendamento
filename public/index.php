<?php

require_once __DIR__ . '/../src/Config/bootstrap.php';

use App\Config\Database\Database;

$database = Database::getInstance();

$connection = $database->getConnection();

echo 'Conexão realizada com sucesso!';
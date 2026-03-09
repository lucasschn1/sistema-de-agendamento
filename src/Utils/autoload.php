<?php
// [ ]: implementar autoload
// [ ]: fazer arquivo env
// [ ]: criar banco de dados

/**
 * Autoloader PSR-4
 * 
 * Estrutura do projeto:
 * /src
 *   ├── Config/
 *   ├── Controllers/
 *   ├── Models/
 *   ├── Repositories/
 *   ├── Services/
 *   └── utils/
 * 
 * Namespace: App\Config, App\Controllers, App\Models, etc.
 */

spl_autoload_register(function ($class) {
    // 1. Prefixo do namespace (todas as classes usam App\)
    $prefix = 'App\\';
    
    // 2. Diretório base onde estão as classes
    $baseDir = __DIR__ . '/../';
    
    // 3. Verifica se a classe usa o prefixo App
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // Se a classe não começar com App\, ignora
        return;
    }
    
    // 4. Pega o nome da classe sem o prefixo App\
    // Exemplo: App\Controllers\PatientController -> Controllers\PatientController
    $relativeClass = substr($class, $len);
    
    // 5. Monta o caminho final
    // Exemplo: ../Controllers/PatientController.php
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    // 6. Se o arquivo existir, carrega
    if (file_exists($file)) {
        require_once $file;
    } else {
        // Log para debug (opcional - descomente se precisar debugar)
        // error_log("Autoloader: Classe não encontrada: $class em: $file");
    }
});
<?php
require_once 'bootstrap.php';

echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Test de Conexión a BD</title>";
echo "<style>body{font-family: sans-serif; margin: 20px; background-color: #f8f9fa;} .container{max-width: 800px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);} h1,h2{color:#333;} pre{background:#eee; padding:10px; border-radius:4px; white-space:pre-wrap; word-wrap:break-word;} .error{color: #d9534f; font-weight: bold;} .success{color: #28a745; font-weight: bold;}</style>";
echo "</head><body><div class='container'>";
echo "<h1>Test de Conexión y Configuración</h1>";

// 1. Verificar si el archivo .env existe
echo "<h2>1. Verificación del archivo <code>.env</code></h2>";
if (file_exists(__DIR__ . '/.env')) {
    echo "<p class='success'>✅ Archivo <code>.env</code> encontrado.</p>";
} else {
    echo "<p class='error'>❌ Archivo <code>.env</code> NO encontrado. Debes copiar <code>.env.example</code> a <code>.env</code> y configurarlo.</p>";
    echo "</div></body></html>";
    exit;
}

// 2. Mostrar la configuración de la base de datos (sin la contraseña)
echo "<h2>2. Configuración de Base de Datos cargada</h2>";
$db_config_display = $config['database'] ?? [];
if (isset($db_config_display['pass'])) {
    $db_config_display['pass'] = '********'; // Ocultar contraseña
}
echo "<pre>" . htmlspecialchars(print_r($db_config_display, true)) . "</pre>";

if (empty($config['database']['name']) || empty($config['database']['user'])) {
    echo "<p class='error'>❌ Los valores para 'name' y/o 'user' están vacíos. Asegúrate de que las variables <code>DB_NAME</code> y <code>DB_USER</code> estén definidas en tu archivo <code>.env</code>.</p>";
} else {
    echo "<p class='success'>✅ La configuración parece estar cargada correctamente desde <code>.env</code>.</p>";
}

try {
    echo "<h2>3. Intento de Conexión a la Base de Datos</h2>";
    $pdo = get_database_connection($config, false);
    
    if ($pdo) {
        echo "<p class='success'>✅ ¡Conexión exitosa a la base de datos '{$config['database']['name']}'!</p>";
        
        // Verificar tablas
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<h2>Tablas encontradas (" . count($tables) . "):</h2>";
        echo "<pre>" . htmlspecialchars(print_r($tables, true)) . "</pre>";
        
        // Probar consulta de estadísticas
        echo "<h2>Test de Procedimiento Almacenado (sp_get_stats):</h2>";
        try {
            $stmt_stats = $pdo->query("CALL sp_get_stats()");
            $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
            echo "<p class='success'>✅ El procedimiento <strong>sp_get_stats</strong> se ejecutó correctamente.</p>";
            echo "<pre>" . htmlspecialchars(print_r($stats, true)) . "</pre>";
        } catch (Exception $e) {
            echo "<p class='error'>❌ Falló la ejecución del procedimiento almacenado <code>sp_get_stats</code>. Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p>Asegúrate de haber importado el archivo <code>database/install.sql</code> en tu base de datos.</p>";
        }
        
    } else {
        echo "<p class='error'>❌ No se pudo conectar. La función <code>get_database_connection</code> devolvió un valor nulo. Revisa los logs de PHP para más detalles, pero usualmente esto se debe a una configuración incorrecta en el paso 2.</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ <strong>Excepción Capturada:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Este error usualmente significa que el host, usuario o contraseña son incorrectos, o que la base de datos '{$config['database']['name']}' no existe.</p>";
}

echo "</div></body></html>";
?>
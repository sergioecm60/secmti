<?php
require_once 'bootstrap.php';
require_once 'database.php';

echo "<h1>Test de Conexión a Base de Datos</h1>";

try {
    $pdo = get_database_connection($config, false);
    
    if ($pdo) {
        echo "✅ <strong>Conexión exitosa</strong><br>";
        
        // Verificar tablas
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<h2>Tablas encontradas (" . count($tables) . "):</h2>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
        
        // Probar consulta de estadísticas
        echo "<h2>Test de consulta de estadísticas:</h2>";
        $stmt = $pdo->query("
            SELECT 
                (SELECT COUNT(*) FROM dc_servers WHERE status = 'active') as servers_active,
                (SELECT COUNT(*) FROM dc_locations) as total_locations
        ");
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($stats);
        echo "</pre>";
        
    } else {
        echo "❌ <strong>No se pudo conectar</strong>";
    }
} catch (Exception $e) {
    echo "❌ <strong>Error:</strong> " . htmlspecialchars($e->getMessage());
}
?>
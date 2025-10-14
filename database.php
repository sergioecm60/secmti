<?php
/**
 * database.php - Gestión de Conexiones a Base de Datos
 * 
 * Proporciona una conexión PDO singleton con manejo robusto de errores
 * y configuración de seguridad apropiada.
 */

// Singleton para reutilizar conexión
$_db_connection = null;

/**
 * Establece y retorna una conexión PDO a la base de datos
 * 
 * Implementa patrón singleton para reutilizar la misma conexión
 * en múltiples llamadas, reduciendo overhead.
 *
 * @param array $config Configuración de la aplicación
 * @param bool $is_critical Si true, termina la ejecución en caso de error
 * @param bool $force_new Si true, fuerza una nueva conexión ignorando singleton
 * @return PDO|null Objeto PDO o null si falla y no es crítico
 * 
 * @throws PDOException Si la conexión falla y $is_critical es true
 */
function get_database_connection(
    array $config, 
    bool $is_critical = true,
    bool $force_new = false
): ?PDO {
    global $_db_connection;
    
    // Retornar conexión existente si está disponible
    if (!$force_new && $_db_connection instanceof PDO) {
        return $_db_connection;
    }
    
    // Validar configuración
    if (!isset($config['database']) || !is_array($config['database'])) {
        $error = 'Configuración de base de datos no encontrada o inválida';
        error_log("DB Connection Error: {$error}");
        
        if ($is_critical) {
            http_response_code(503);
            die('Error de configuración del servidor. Por favor, contacte al administrador.');
        }
        return null;
    }
    
    $db_config = $config['database'];
    
    // Validar parámetros requeridos
    $required_keys = ['host', 'name', 'user'];
    foreach ($required_keys as $key) {
        if (empty($db_config[$key])) {
            $error = "Parámetro de BD requerido '{$key}' faltante";
            error_log("DB Connection Error: {$error}");
            
            if ($is_critical) {
                http_response_code(503);
                die('Error de configuración del servidor.');
            }
            return null;
        }
    }
    
    try {
        // Construir DSN
        $port = $db_config['port'] ?? 3306;
        $charset = $db_config['charset'] ?? 'utf8mb4';
        
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db_config['host'],
            $port,
            $db_config['name'],
            $charset
        );
        
        // Configurar opciones PDO
        $options = [
            // Modo de error: Excepciones
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            
            // Fetch mode por defecto: array asociativo
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            
            // Deshabilitar emulación de prepared statements (más seguro)
            PDO::ATTR_EMULATE_PREPARES => false,
            
            // Forzar uso de prepared statements nativos
            PDO::ATTR_STRINGIFY_FETCHES => false,
            
            // Timeout de conexión
            PDO::ATTR_TIMEOUT => $db_config['options']['timeout'] ?? 5,
            
            // Conexiones persistentes (opcional, usar con precaución)
            PDO::ATTR_PERSISTENT => $db_config['options']['persistent'] ?? false,
            
            // Configurar MySQL para usar zona horaria UTC
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$db_config['collation']}, time_zone = '+00:00'",
        ];
        
        // Crear conexión PDO
        $pdo = new PDO(
            $dsn,
            $db_config['user'],
            $db_config['pass'] ?? '',
            $options
        );
        
        // Guardar en singleton
        $_db_connection = $pdo;
        
        // Log de conexión exitosa (solo en desarrollo)
        if (defined('IS_DEVELOPMENT') && IS_DEVELOPMENT) {
            error_log("DB Connection established successfully to {$db_config['name']}");
        }
        
        return $pdo;
        
    } catch (PDOException $e) {
        // Log del error (sin exponer detalles al usuario)
        $error_msg = "DB Connection Failed: " . $e->getMessage();
        error_log($error_msg);
        
        // Log adicional en archivo específico si está configurado
        if (isset($config['logging']['paths']['database'])) {
            $log_file = $config['logging']['paths']['database'];
            $timestamp = date('Y-m-d H:i:s');
            @error_log("[{$timestamp}] {$error_msg}\n", 3, $log_file);
        }
        
        if ($is_critical) {
            // Mensaje genérico para usuarios
            http_response_code(503);
            
            if (defined('IS_DEVELOPMENT') && IS_DEVELOPMENT) {
                // En desarrollo, mostrar más detalles
                die("Error de conexión a BD: " . htmlspecialchars($e->getMessage()));
            } else {
                // En producción, mensaje genérico
                die("Error del servidor. El sistema no puede conectar a la base de datos. Por favor, intente más tarde o contacte al administrador.");
            }
        }
        
        return null;
    }
}

/**
 * Cierra la conexión de base de datos
 * Útil para forzar reconexión o liberar recursos
 */
function close_database_connection(): void {
    global $_db_connection;
    $_db_connection = null;
}

/**
 * Verifica si la conexión a BD está activa
 * 
 * @return bool True si hay conexión activa
 */
function has_database_connection(): bool {
    global $_db_connection;
    return $_db_connection instanceof PDO;
}

/**
 * Ejecuta una consulta de verificación de salud de BD
 * 
 * @param PDO $pdo Conexión PDO
 * @return bool True si la BD responde correctamente
 */
function check_database_health(PDO $pdo): bool {
    try {
        $stmt = $pdo->query('SELECT 1');
        return $stmt !== false;
    } catch (PDOException $e) {
        error_log("DB Health Check Failed: " . $e->getMessage());
        return false;
    }
}
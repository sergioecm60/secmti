<?php
// database.php - Maneja la conexión a la base de datos.

/**
 * Establece una conexión a la base de datos usando la configuración proporcionada.
 *
 * @param array $config La configuración de la aplicación.
 * @param bool $is_critical Si es true, el script morirá en caso de error. Si es false, devolverá null.
 * @return PDO|null El objeto PDO en caso de éxito, o null si la conexión falla y $is_critical es false.
 */
function get_database_connection(array $config, bool $is_critical = true): ?PDO
{
    $db_config = $config['database'] ?? [];
    
    try {
        $dsn = "mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        return new PDO($dsn, $db_config['user'], $db_config['pass'] ?? '', $options);
    } catch (PDOException $e) {
        error_log("Error de conexión a la BD: " . $e->getMessage());
        if ($is_critical) {
            die("Error crítico: No se pudo establecer la conexión con la base de datos. Revise los logs del servidor.");
        }
        return null;
    }
}
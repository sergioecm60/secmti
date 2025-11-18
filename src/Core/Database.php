<?php

namespace SecMTI\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdoInstance = null;
    private static array $config = [];

    /**
     * Inicializa la configuración de la base de datos.
     *
     * @param array $config La configuración de la base de datos.
     */
    public static function setConfig(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Obtiene la instancia de PDO.
     *
     * @param bool $critical Si la conexión es crítica y debe detener la ejecución en caso de fallo.
     * @return PDO|null La instancia de PDO o null si no es crítica y falla.
     */
    public static function getConnection(bool $critical = true): ?PDO
    {
        if (self::$pdoInstance === null) {
            if (empty(self::$config)) {
                error_log('CRITICAL: La configuración de la base de datos no ha sido establecida.');
                if ($critical) {
                    http_response_code(500);
                    die('Error interno del servidor: Configuración de BD faltante.');
                }
                return null;
            }

            $db_config = self::$config['database'];
            $dsn = "mysql:host={$db_config['host']};dbname={$db_config['name']};charset={$db_config['charset']}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$pdoInstance = new PDO($dsn, $db_config['user'], $db_config['pass'], $options);
            } catch (PDOException $e) {
                error_log('CRITICAL: Error de conexión a la base de datos: ' . $e->getMessage());
                if ($critical) {
                    http_response_code(500);
                    die('Error interno del servidor: No se pudo conectar a la base de datos.');
                }
                return null;
            }
        }
        return self::$pdoInstance;
    }
}

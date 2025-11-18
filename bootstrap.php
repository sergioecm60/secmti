<?php
/**
 * bootstrap.php - Archivo de inicialización central.
 *
 * Este archivo se encarga de:
 * 1. Configurar el reporte de errores según el entorno.
 * 2. Cargar y validar la configuración principal.
 * 3. Iniciar y gestionar sesiones seguras con timeout y protección contra hijacking.
 * 4. Establecer cabeceras de seguridad HTTP.
 * 5. Definir constantes globales de seguridad.
 * 6. Implementar protecciones básicas contra ataques comunes.
 */
// ============================================================================
// 1. CONFIGURACIÓN DE ENTORNO Y ERRORES
// ============================================================================

// Cargar el autoloader de Composer ANTES que cualquier otra cosa.
// Esto hace que las clases de dependencias (como Dotenv) estén disponibles globalmente.
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use SecMTI\Core\SessionManager;
use SecMTI\Core\SecurityHeaders;
use SecMTI\Core\CsrfToken;
use SecMTI\Core\RateLimiter;
use SecMTI\Core\ClientIp;
use SecMTI\Core\SecurityLogger;
use SecMTI\Core\Registry;

// Cargar variables de entorno desde .env si existe.
if (class_exists('Dotenv\Dotenv') && file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load(); // Carga en getenv(), $_ENV y $_SERVER
}

/**
 * Detectar entorno basado ÚNICAMENTE en variable de entorno
 * Nunca confiar en HTTP_HOST para decisiones de seguridad
 */
$app_env = $_ENV['APP_ENV'] ?? 'production';
define('IS_DEVELOPMENT', $app_env === 'development');
define('IS_PRODUCTION', $app_env === 'production');

if (IS_DEVELOPMENT) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL); // Seguir reportando a logs
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/logs/php_errors.log');
}

// ============================================================================
// 2. CONFIGURACIÓN DE ZONA HORARIA
// ============================================================================
$app_timezone = $_ENV['APP_TIMEZONE'] ?? 'UTC';
date_default_timezone_set($app_timezone);

// ============================================================================
// 2. CARGA Y VALIDACIÓN DE CONFIGURACIÓN
// ============================================================================

// Cargar la configuración desde config.php, que ahora leerá desde .env
$config_loader_file = __DIR__ . '/config.php';
if (!file_exists($config_loader_file)) {
    error_log('CRITICAL: El archivo config.php que carga las variables de entorno no existe.');
    http_response_code(503);
    die('Error de configuración del servidor.');
}
$config = require $config_loader_file;

// Validar que la configuración se cargó correctamente
if (!is_array($config) || empty($config)) {
    error_log('CRITICAL: config.php no retornó un array de configuración válido. Verifica el archivo .env y config.php.');
    http_response_code(503);
    die('Error de configuración del servidor. Revisa el archivo .env.');
}

// Incluir el gestor de base de datos al principio, para que esté disponible para todos los scripts.


use SecMTI\Core\Database;

// Configurar y obtener la conexión a la base de datos
Database::setConfig($config);
Registry::set('pdo', Database::getConnection());

// Validar claves críticas
$required_keys = ['session', 'database', 'security'];
foreach ($required_keys as $key) {
    if (!isset($config[$key]) || !is_array($config[$key])) {
        error_log("CRITICAL: Clave de configuración '$key' faltante o inválida");
        http_response_code(503);
        die('Error de configuración del servidor.');
    }
}


// ============================================================================
// 8. CONSTANTES GLOBALES (MOVIMOS ESTO ARRIBA PARA DISPONIBILIDAD TEMPRANA)
// ============================================================================

// IP del cliente (validada y segura)
$clientIp = new ClientIp($config);
define('IP_ADDRESS', $clientIp->get());
Registry::set('clientIp', $clientIp);

// Inicializar y validar sesión usando SessionManager
$sessionManager = new SessionManager($config, IP_ADDRESS);
$sessionManager->init();
$sessionManager->validateSession();
Registry::set('sessionManager', $sessionManager);

// Enviar cabeceras de seguridad usando SecurityHeaders
$is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
$securityHeaders = new SecurityHeaders($config, $is_https, IS_PRODUCTION);
$securityHeaders->sendHeaders();
Registry::set('securityHeaders', $securityHeaders);

// Inicializar CSRF Token
$csrfToken = new CsrfToken();
$csrfToken->generate(); // Ensure a token is always generated
Registry::set('csrfToken', $csrfToken);

// Inicializar Rate Limiter
$rateLimiter = new RateLimiter(IP_ADDRESS);
Registry::set('rateLimiter', $rateLimiter);

// Inicializar Security Logger
$securityLogger = new SecurityLogger(__DIR__ . '/logs/security.log', IP_ADDRESS);
Registry::set('securityLogger', $securityLogger);








// ============================================================================
// INICIALIZACIÓN COMPLETADA
// ============================================================================

// Log de inicialización exitosa en desarrollo
if (IS_DEVELOPMENT) {
    error_log('Bootstrap completado exitosamente');
}

// ============================================================================
// 10. HELPERS DE CIFRADO (Agregado en Mejora #1)
// ============================================================================
if (!defined('APP_ENCRYPTION_KEY')) {
    define('APP_ENCRYPTION_KEY', $_ENV['APP_ENCRYPTION_KEY'] ?? ''); // Ahora solo se toma del .env
}








// ============================================================================
// 12. HELPERS DE MODALES (Agregado en Mejora #3)
// ============================================================================

/**
 * Incluye el template de modales
 */
if (!function_exists('render_modal')) {
    require_once __DIR__ . '/templates/modal_template.php';
}
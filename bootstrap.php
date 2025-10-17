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
// 4. FUNCIONES DE SEGURIDAD
// ============================================================================

/**
 * Obtiene la IP real del cliente de forma segura
 * Solo confía en proxies configurados explícitamente
 * 
 * @param array $config Configuración de la aplicación
 * @return string IP validada o 'unknown'
 */
function get_client_ip(array $config): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Solo confiar en X-Forwarded-For si el request viene de un proxy confiable
    $trusted_proxies = $config['security']['trusted_proxies'] ?? [];
    
    if (!empty($trusted_proxies) && in_array($ip, $trusted_proxies, true)) {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if (!empty($forwarded)) {
            $ips = array_map('trim', explode(',', $forwarded));
            // Tomar la PRIMERA IP pública de la cadena (la IP del cliente real)
            foreach ($ips as $forwarded_ip) {
                $validated = filter_var(
                    $forwarded_ip, 
                    FILTER_VALIDATE_IP, 
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                );
                if ($validated !== false) {
                    $ip = $validated;
                    break;
                }
            }
        }
    }
    
    return filter_var($ip, FILTER_VALIDATE_IP) ?: 'unknown';
}

/**
 * Genera un fingerprint de la sesión para detectar hijacking
 * 
 * @return string Hash del fingerprint
 */
function generate_session_fingerprint(): string {
    $components = [
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
        $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
    ];
    return hash('sha256', implode('|', $components));
}

/**
 * Valida que la sesión no haya sido secuestrada
 * 
 * @return bool True si la sesión es válida
 */
function validate_session_fingerprint(): bool {
    if (!isset($_SESSION['fingerprint'])) {
        return true; // Primera validación, aceptar
    }
    
    $current_fingerprint = generate_session_fingerprint();
    return hash_equals($_SESSION['fingerprint'], $current_fingerprint);
}

/**
 * Regenera la sesión de forma segura
 * Previene ataques de session fixation
 * 
 * @param bool $delete_old_session Si debe eliminar la sesión anterior
 */
function regenerate_session(bool $delete_old_session = true): void {
    // Guardar datos importantes antes de regenerar
    $data_to_preserve = [];
    $preserve_keys = ['user_id', 'user_role', 'username', 'user_email'];
    
    foreach ($preserve_keys as $key) {
        if (isset($_SESSION[$key])) {
            $data_to_preserve[$key] = $_SESSION[$key];
        }
    }
    
    // Regenerar ID de sesión
    session_regenerate_id($delete_old_session);
    
    // Restaurar datos preservados
    foreach ($data_to_preserve as $key => $value) {
        $_SESSION[$key] = $value;
    }
    
    // Actualizar timestamp y fingerprint
    $_SESSION['last_activity'] = time();
    $_SESSION['fingerprint'] = generate_session_fingerprint();
    $_SESSION['created_at'] = $_SESSION['created_at'] ?? time();
    
    // Regenerar token CSRF
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Verifica y valida el token CSRF
 * 
 * @param string $token Token a validar
 * @return bool True si el token es válido
 */
function verify_csrf_token(string $token): bool {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Rate limiting básico basado en sesión/IP
 * 
 * @param string $action Nombre de la acción a limitar
 * @param int $max_attempts Intentos máximos permitidos
 * @param int $window_seconds Ventana de tiempo en segundos
 * @return bool True si está dentro del límite
 */
function check_rate_limit(string $action, int $max_attempts = 5, int $window_seconds = 300): bool {
    $key = "rate_limit_{$action}";
    $now = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['attempts' => [], 'blocked_until' => 0];
    }
    
    // Si está bloqueado, verificar si ya pasó el tiempo
    if ($_SESSION[$key]['blocked_until'] > $now) {
        return false;
    }
    
    // Limpiar intentos antiguos
    $_SESSION[$key]['attempts'] = array_filter(
        $_SESSION[$key]['attempts'],
        fn($timestamp) => ($now - $timestamp) < $window_seconds
    );
    
    // Verificar límite
    if (count($_SESSION[$key]['attempts']) >= $max_attempts) {
        $_SESSION[$key]['blocked_until'] = $now + $window_seconds;
        error_log("Rate limit exceeded for action '$action' from IP: " . IP_ADDRESS);
        return false;
    }
    
    // Registrar intento
    $_SESSION[$key]['attempts'][] = $now;
    return true;
}

/**
 * Destruye la sesión de forma segura
 */
function destroy_session(): void {
    $_SESSION = [];
    
    // Eliminar cookie de sesión
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    session_destroy();
}

// ============================================================================
// 5. CONFIGURACIÓN DE SESIÓN
// ============================================================================

// Configurar nombre de sesión
$session_name = $config['session']['name'] ?? 'PORTAL_SESSID';
session_name($session_name);

// Extraer dominio para cookies
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
if (strpos($host, ':') !== false) {
    $host = explode(':', $host)[0];
}

// En desarrollo (localhost/IPs), dominio vacío. En producción, el host.
$is_local = in_array($host, ['localhost', '127.0.0.1']) || 
            filter_var($host, FILTER_VALIDATE_IP) !== false;
$domain = $is_local ? '' : $host;

// Configurar parámetros de cookie de sesión
$cookie_lifetime = $config['session']['cookie_lifetime'] ?? 0;
$is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

$cookie_params = [
    'lifetime' => $cookie_lifetime,
    'path' => '/',
    'domain' => $domain,
    'secure' => $is_https, // Solo HTTPS en producción
    'httponly' => true, // Prevenir acceso desde JavaScript
    'samesite' => 'Strict' // Strict es más seguro, Lax si hay problemas con redirects
];

session_set_cookie_params($cookie_params);

// Iniciar sesión
session_start();

// ============================================================================
// 6. VALIDACIONES DE SEGURIDAD DE SESIÓN
// ============================================================================

$current_page = basename($_SERVER['PHP_SELF']);
$public_pages = ['login.php', 'recover_password.php', 'reset_password.php'];
$is_public_page = in_array($current_page, $public_pages);

// Solo aplicar validaciones de sesión si el usuario está logueado
if (isset($_SESSION['user_id'])) {
    
    // 6.1 Validar fingerprint para detectar session hijacking
    if (!validate_session_fingerprint()) {
        error_log('SECURITY: Possible session hijacking detected for user_id: ' . $_SESSION['user_id'] . ' from IP: ' . get_client_ip($config));
        destroy_session();
        header('Location: login.php?status=security_error');
        exit;
    }
    
    // 6.2 Validar timeout de sesión
    $timeout = $config['session']['timeout_seconds'] ?? 1800; // 30 min por defecto
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        error_log('Session timeout for user_id: ' . $_SESSION['user_id']);
        destroy_session();
        header('Location: login.php?status=session_expired');
        exit;
    }
    
    // 6.3 Validar tiempo máximo absoluto de sesión (prevenir sesiones eternas)
    $max_lifetime = $config['session']['max_lifetime_seconds'] ?? 28800; // 8 horas
    if (isset($_SESSION['created_at']) && (time() - $_SESSION['created_at']) > $max_lifetime) {
        error_log('Max session lifetime reached for user_id: ' . $_SESSION['user_id']);
        destroy_session();
        header('Location: login.php?status=session_expired');
        exit;
    }
    
    // 6.4 Actualizar timestamp de última actividad
    $_SESSION['last_activity'] = time();
    
    // 6.5 Regenerar ID de sesión periódicamente (cada 15 minutos)
    $regenerate_interval = $config['session']['regenerate_interval'] ?? 900;
    $last_regeneration = $_SESSION['last_regeneration'] ?? $_SESSION['created_at'] ?? time();
    
    if ((time() - $last_regeneration) > $regenerate_interval) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// 6.6 Inicializar sesión nueva
if (!isset($_SESSION['created_at'])) {
    $_SESSION['created_at'] = time();
    $_SESSION['fingerprint'] = generate_session_fingerprint();
}

// 6.7 Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================================
// 7. CABECERAS DE SEGURIDAD HTTP
// ============================================================================

// Control de referrer
header('Referrer-Policy: strict-origin-when-cross-origin');

// XSS Protection (legacy, pero no hace daño)
header('X-XSS-Protection: 1; mode=block');

// Permissions Policy (reemplaza Feature-Policy)
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// HSTS (solo en HTTPS)
if ($is_https && IS_PRODUCTION) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// Las cabeceras más comunes se establecen aquí.
// Páginas específicas pueden añadir o sobrescribir las suyas.
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

// NOTA: La cabecera Content-Security-Policy (CSP) es muy específica
// y se sigue gestionando en cada página individualmente para mayor control
// (ej: index.php, index2.php, diag_x9k2.php, etc.).

// ============================================================================
// 8. CONSTANTES GLOBALES
// ============================================================================

// IP del cliente (validada y segura)
define('IP_ADDRESS', get_client_ip($config));

// Información del usuario agente
define('USER_AGENT', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

// Base URL de la aplicación
$protocol = $is_https ? 'https' : 'http';
$base_url = $config['app']['url'] ?? ($protocol . '://' . $_SERVER['HTTP_HOST']);
define('BASE_URL', rtrim($base_url, '/'));

// ============================================================================
// 9. LOGGING DE SEGURIDAD
// ============================================================================

/**
 * Log de eventos de seguridad
 * 
 * @param string $event Tipo de evento
 * @param string $details Detalles del evento
 */
function log_security_event(string $event, string $details = ''): void {
    $log_file = __DIR__ . '/logs/security.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0750, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = IP_ADDRESS;
    $user_id = $_SESSION['user_id'] ?? 'anonymous';
    
    $message = "[{$timestamp}] {$event} | User: {$user_id} | IP: {$ip} | {$details}\n";
    @error_log($message, 3, $log_file);
}

// Nota: Las páginas individuales pueden llamar a log_security_event() cuando sea necesario

// ============================================================================
// INICIALIZACIÓN COMPLETADA
// ============================================================================

// Log de inicialización exitosa en desarrollo
if (IS_DEVELOPMENT) {
    error_log('Bootstrap completado exitosamente');
}

// Incluir el gestor de base de datos al final, para que esté disponible para todos los scripts.
require_once __DIR__ . '/database.php';

// ============================================================================
// 10. HELPERS DE CIFRADO (Agregado en Mejora #1)
// ============================================================================
if (!defined('APP_ENCRYPTION_KEY')) {
    define('APP_ENCRYPTION_KEY', $_ENV['APP_ENCRYPTION_KEY'] ?? $config['security']['encryption_key'] ?? '');
}

/**
 * Cifra una contraseña usando AES-256-CBC.
 * @param string $password La contraseña en texto plano.
 * @return string|false La contraseña cifrada en base64 o false si falla.
 */
function encrypt_password(string $password): string|false {
    if (empty(APP_ENCRYPTION_KEY)) {
        error_log('Error de cifrado: APP_ENCRYPTION_KEY no está definida.');
        return false;
    }
    $key = base64_decode(APP_ENCRYPTION_KEY);
    if (strlen($key) !== 32) {
        error_log('Error de cifrado: La clave de cifrado no es válida (debe tener 32 bytes).');
        return false;
    }

    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($password, 'aes-256-cbc', $key, 0, $iv);

    if ($encrypted === false) return false;

    return base64_encode($iv . $encrypted);
}

/**
 * Descifra una contraseña.
 * @param string $encrypted_password La contraseña cifrada en base64.
 * @return string|false El texto plano o false si falla.
 */
function decrypt_password(string $encrypted_password): string|false {
    try {
        if (empty(APP_ENCRYPTION_KEY)) {
            error_log('Error de descifrado: APP_ENCRYPTION_KEY no está definida.');
            return false;
        }
        $key = base64_decode(APP_ENCRYPTION_KEY);
        if (strlen($key) !== 32) {
            error_log('Error de descifrado: La clave de cifrado no es válida.');
            return false;
        }

        $data = base64_decode($encrypted_password);
        $iv_length = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);

        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    } catch (Exception $e) {
        error_log('Error de descifrado: ' . $e->getMessage());
        return false;
    }
}

// ============================================================================
// 11. HELPERS DE CSRF (Agregado en Mejora #2)
// ============================================================================

/**
 * Genera un token CSRF y lo almacena en la sesión
 * 
 * @return string Token CSRF generado
 */
function generate_csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Obtiene el token CSRF actual de la sesión
 * 
 * @return string|null Token CSRF o null si no existe
 */
function get_csrf_token(): ?string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return $_SESSION['csrf_token'] ?? null;
}

/**
 * Valida un token CSRF
 * 
 * @param string|null $token Token a validar
 * @param bool $throw_exception Si es true, lanza excepción en caso de fallo
 * @return bool True si el token es válido
 * @throws Exception Si $throw_exception es true y el token es inválido
 */
function validate_csrf_token(?string $token, bool $throw_exception = true): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $session_token = $_SESSION['csrf_token'] ?? null;
    
    // Validar que ambos tokens existan y sean iguales
    $is_valid = !empty($token) && !empty($session_token) && hash_equals($session_token, $token);
    
    if (!$is_valid && $throw_exception) {
        throw new Exception('Token CSRF inválido o faltante', 403);
    }
    
    return $is_valid;
}

/**
 * Valida el token CSRF de una petición HTTP
 * Lee el token desde el header X-CSRF-Token o desde $_POST['csrf_token']
 * 
 * @param bool $throw_exception Si es true, lanza excepción en caso de fallo
 * @return bool True si el token es válido
 * @throws Exception Si $throw_exception es true y el token es inválido
 */
function validate_request_csrf(bool $throw_exception = true): bool {
    // Intentar obtener el token del header o POST
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
    
    return validate_csrf_token($token, $throw_exception);
}

/**
 * Genera un campo hidden de formulario con el token CSRF
 * 
 * @return string HTML del campo hidden
 */
function csrf_field(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
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
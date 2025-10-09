<?php
/**
 * bootstrap.php - Archivo de inicialización central.
 *
 * Este archivo se encarga de:
 * 1. Configurar el reporte de errores.
 * 2. Cargar la configuración principal de la aplicación.
 * 3. Iniciar y gestionar la sesión, incluyendo el timeout.
 * 4. Establecer las cabeceras de seguridad comunes.
 * 5. Definir la IP del cliente.
 */

// 1. Configuración de Errores
// Detectar entorno de desarrollo. Es más robusto usar una variable de entorno si es posible.
$is_development = getenv('APP_ENV') === 'development' || in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']) || str_contains($_SERVER['HTTP_HOST'], 'local');
if ($is_development) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
}

// 2. Cargar la configuración de la aplicación
$config = @require_once 'config.php';
if ($config === false) {
    http_response_code(503);
    die("Error Crítico: El archivo de configuración principal (config.php) no se encuentra o no es legible.");
}

// Autoloader (PSR-4 simple) para cargar clases desde el directorio /src
spl_autoload_register(function ($class) {
    // Proyecto con namespace base 'SecMTI'
    $prefix = 'SecMTI\\';
    $base_dir = __DIR__ . '/src/';

    // ¿La clase usa el prefijo del namespace?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return; // No, pasar al siguiente autoloader registrado.
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// 3. Gestión de Sesiones
// Configurar parámetros de la cookie de sesión ANTES de iniciarla.
session_name($config['session']['name'] ?? 'PORTAL_SESSID'); // Usar nombre de config o uno por defecto

// Extraer solo el nombre de host, sin puerto, para el dominio de la cookie.
$host = $_SERVER['HTTP_HOST'];
if (strpos($host, ':') !== false) {
    $host = explode(':', $host)[0];
}

// En localhost o IPs, el dominio debe ser vacío. En producción, el nombre de host.
$domain = in_array($host, ['localhost', '127.0.0.1']) ? '' : $host;

$cookie_params = [
    'lifetime' => 0, // La cookie dura hasta que se cierre el navegador
    'path' => '/',
    'domain' => $domain,
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // Solo enviar la cookie sobre HTTPS
    'httponly' => true, // Prevenir acceso a la cookie desde JavaScript
    'samesite' => 'Lax' // Prevenir ataques CSRF, permite la navegación post-login.
];
session_set_cookie_params($cookie_params);

session_start(); // Iniciar la sesión con los parámetros seguros

// Lógica de timeout de sesión
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > ($config['session']['timeout_seconds'] ?? 300)) {
    // La sesión ha expirado. Limpiar, destruir y redirigir al login.
    session_unset();     // Limpia las variables de sesión.
    session_destroy();   // Destruye la sesión en el servidor.
    // No es necesario iniciar una nueva sesión aquí, la página de login lo hará.
    header('Location: login.php?status=session_expired');
    exit;
}


// Si el usuario está logueado, actualizar el timestamp de actividad.
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
}

// Regenerar token CSRF en cada carga de página que no sea POST para mayor seguridad.
// Se genera un token CSRF si no existe uno, para mantenerlo estable durante la sesión.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// 4. Cabeceras de Seguridad Comunes
header('Content-Type: text/html; charset=utf-8');
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("X-XSS-Protection: 1; mode=block");

// 5. Detección de IP segura
define('IP_ADDRESS', filter_var(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'])[0]), FILTER_VALIDATE_IP) ?: 'IP desconocida');

// Nota: La cabecera Content-Security-Policy (CSP) es específica para cada página
// y debe establecerse en cada script individualmente después de incluir este archivo.
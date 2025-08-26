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
// Solo activar en desarrollo
if (in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']) || str_contains($_SERVER['HTTP_HOST'], 'local')) {
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

// 3. Gestión de Sesiones
session_start();

// Lógica de timeout de sesión
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > ($config['session']['timeout_seconds'] ?? 300)) {
    session_unset();
    session_destroy();
    session_start();
}

// Si el usuario está logueado, actualizar el timestamp de actividad.
if (isset($_SESSION['acceso_info'])) {
    $_SESSION['last_activity'] = time();
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
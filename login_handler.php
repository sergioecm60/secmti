<?php
// login_handler.php - Procesa el inicio de sesión para index2.php
session_start();
header('Content-Type: application/json');

// --- Configuración de Errores para API ---
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// --- Constantes de Seguridad ---
const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_MINUTES = 60;

// Leer el cuerpo de la solicitud JSON
$input = json_decode(file_get_contents('php://input'), true);

$user = $input['user'] ?? '';
$pass = $input['pass'] ?? '';
$captcha = $input['captcha'] ?? '';

// Incluir dependencias
$config = require_once 'config.php';
require_once 'database.php';

// --- 1. Validación del Captcha ---
if (empty($_SESSION['captcha_answer']) || (string)$captcha !== (string)$_SESSION['captcha_answer']) {
    unset($_SESSION['captcha_answer']);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La respuesta del captcha es incorrecta.']);
    exit();
}

// El captcha es de un solo uso
unset($_SESSION['captcha_answer']);

// --- 2. Validación de Credenciales ---
if (empty($user) || empty($pass)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos.']);
    exit();
}

$pdo = get_database_connection($config, false);

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor al conectar con la base de datos.']);
    exit();
}

$stmt = $pdo->prepare("SELECT id, pass_hash, failed_login_attempts, lockout_until FROM user WHERE username = ?");
$stmt->execute([$user]);
$user_data = $stmt->fetch();

if (!$user_data) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos.']);
    exit();
}

// --- 3. Comprobar si la cuenta está bloqueada ---
if ($user_data['lockout_until'] && new DateTime() < new DateTime($user_data['lockout_until'])) {
    $lockout_time = new DateTime($user_data['lockout_until']);
    $now = new DateTime();
    $remaining = $now->diff($lockout_time);
    $message = "Cuenta bloqueada. Inténtelo de nuevo en " . $remaining->i . " minutos y " . $remaining->s . " segundos.";
    
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

// --- 4. Verificar la contraseña ---
if (password_verify($pass, $user_data['pass_hash'])) {
    if ($user_data['failed_login_attempts'] > 0 || $user_data['lockout_until']) {
        $reset_stmt = $pdo->prepare("UPDATE user SET failed_login_attempts = 0, lockout_until = NULL WHERE id = ?");
        $reset_stmt->execute([$user_data['id']]);
    }

    $_SESSION['acceso_info'] = true;
    $_SESSION['last_activity'] = time();
    session_regenerate_id(true);
    echo json_encode(['success' => true]);
} else {
    $new_attempts = $user_data['failed_login_attempts'] + 1;

    if ($new_attempts >= MAX_LOGIN_ATTEMPTS) {
        $lock_stmt = $pdo->prepare("UPDATE user SET failed_login_attempts = ?, lockout_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?");
        $lock_stmt->execute([$new_attempts, LOCKOUT_MINUTES, $user_data['id']]);
    } else {
        $inc_stmt = $pdo->prepare("UPDATE user SET failed_login_attempts = ? WHERE id = ?");
        $inc_stmt->execute([$new_attempts, $user_data['id']]);
    }

    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos.']);
}
exit();
<?php
/**
 * api/auth.php
 * Endpoint para autenticación: login y generación de captcha.
 */

require_once '../bootstrap.php'; // bootstrap.php inicia la sesión
use SecMTI\Model\UserModel;

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$action = $_GET['action'] ?? null;

// --- ACCIÓN: GENERAR CAPTCHA ---
if ($action === 'get_captcha') {
    $num1 = rand(1, 9);
    $num2 = rand(1, 9);
    $_SESSION['captcha_answer'] = $num1 + $num2;
    $question = "¿Cuánto es {$num1} + {$num2}?";

    echo json_encode(['question' => $question]);
    exit;
}

// --- ACCIÓN: PROCESAR LOGIN (SOLO POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    // Constantes de Seguridad
    $max_attempts = $config['security']['max_login_attempts'] ?? 5;
    $lockout_minutes = $config['security']['lockout_minutes'] ?? 15;

    $input = json_decode(file_get_contents('php://input'), true);
    $user = $input['username'] ?? '';
    $pass = $input['password'] ?? '';
    $captcha = $input['captcha'] ?? '';

    // 1. Validación del Captcha
    if (empty($_SESSION['captcha_answer']) || (string)$captcha !== (string)$_SESSION['captcha_answer']) {
        unset($_SESSION['captcha_answer']);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'La respuesta del captcha es incorrecta.']);
        exit;
    }
    unset($_SESSION['captcha_answer']);

    // 2. Validación de Credenciales
    if (empty($user) || empty($pass)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos.']);
        exit;
    }

    require_once '../database.php';
    $pdo = get_database_connection($config, false);

    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error del servidor.']);
        exit;
    }

    $userModel = new UserModel($pdo);
    $user_data = $userModel->findByUsername($user);

    if (!$user_data) {
        echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos.']);
        exit;
    }

    // 3. Comprobar bloqueo
    if ($user_data['lockout_until'] && new DateTime() < new DateTime($user_data['lockout_until'])) {
        $lockout_time = new DateTime($user_data['lockout_until']);
        $remaining = (new DateTime())->diff($lockout_time);
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => "Cuenta bloqueada. Intente de nuevo en " . $remaining->i . " minutos."]);
        exit;
    }

    // 4. Verificar contraseña
    if (password_verify($pass, $user_data['pass_hash'])) {
        // Éxito
        $userModel->handleSuccessfulLogin($user_data['id']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user_data['id'];
        $_SESSION['username'] = $user_data['username'];
        $_SESSION['user_role'] = $user_data['role'];
        $_SESSION['last_activity'] = time();
        echo json_encode(['success' => true, 'redirect' => 'index2.php']);
    } else {
        // Fallo
        $userModel->handleFailedLogin($user_data, $max_attempts, $lockout_minutes);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos.']);
    }
    exit;
}

// Si no es una acción válida
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
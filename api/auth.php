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

try {
    // --- ACCIÓN: PROCESAR LOGIN (SOLO POST) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
        // Constantes de Seguridad
        $max_attempts = $config['security']['max_login_attempts'] ?? 5;
        $lockout_minutes = $config['security']['lockout_minutes'] ?? 15;

        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Solicitud JSON inválida.', 400);
        }

        $user = $input['username'] ?? '';
        $pass = $input['password'] ?? '';
        $captcha = $input['captcha'] ?? '';

        // Validación de longitud para prevenir abuso de recursos
        if (strlen($user) > 64 || strlen($pass) > 128) {
            throw new Exception('Datos de entrada inválidos.', 400);
        }

        // 1. Validación del Captcha
        if (empty($_SESSION['captcha_answer']) || !hash_equals((string)$_SESSION['captcha_answer'], (string)$captcha)) {
            unset($_SESSION['captcha_answer']);
            throw new Exception('La respuesta del captcha es incorrecta.', 401);
        }
        unset($_SESSION['captcha_answer']);

        // 2. Validación de Credenciales
        if (empty($user) || empty($pass)) {
            throw new Exception('Usuario o contraseña incorrectos.', 401);
        }

        require_once '../database.php';
        $pdo = get_database_connection($config, false);

        if (!$pdo) {
            throw new Exception('Error del servidor al conectar con la base de datos.', 500);
        }

        $userModel = new UserModel($pdo);
        $user_data = $userModel->findByUsername($user);

        if (!$user_data) {
            // Usamos una excepción para mantener el flujo de error consistente.
            throw new Exception('Usuario o contraseña incorrectos.', 401);
        }

        // 3. Comprobar bloqueo
        if ($user_data['lockout_until'] && new DateTime() < new DateTime($user_data['lockout_until'])) {
            $lockout_time = new DateTime($user_data['lockout_until']);
            $remaining = (new DateTime())->diff($lockout_time);
            throw new Exception("Cuenta bloqueada. Intente de nuevo en " . $remaining->i . " minutos.", 429);
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
            throw new Exception('Usuario o contraseña incorrectos.', 401);
        }
        exit;
    }

    // Si no es una acción válida
    throw new Exception('Acción no válida.', 400);

} catch (Exception $e) {
    // Capturar cualquier excepción para devolver una respuesta JSON controlada.
    $code = $e->getCode();
    if ($code < 400 || $code >= 600) {
        $code = 500; // Default a error interno del servidor
    }
    http_response_code($code);
    // Loguear el error real solo si es un error del servidor
    if ($code >= 500) {
        error_log("API Auth Error: " . $e->getMessage());
    }
    // Devolver un mensaje genérico para errores 500
    $message = ($code >= 500) ? 'Error interno del servidor.' : $e->getMessage();
    echo json_encode(['success' => false, 'message' => $message]);
}
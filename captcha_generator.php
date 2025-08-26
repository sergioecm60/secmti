<?php
// captcha_generator.php - Genera una pregunta de captcha simple.

// --- Configuración de seguridad ---
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
        'use_cookies' => true
    ]);
}

// Generar dos números aleatorios
$num1 = rand(1, 9);
$num2 = rand(1, 9);
$answer = $num1 + $num2;

// Almacenar la respuesta en la sesión
$_SESSION['captcha_answer'] = $answer;

// Crear la pregunta
$question = "¿Cuánto es {$num1} + {$num2}?";

// Validar UTF-8
if (!mb_check_encoding($question, 'UTF-8')) {
    $question = '¿Cuánto es ' . $num1 . ' + ' . $num2 . '?';
}

// Preparar respuesta
$data = ['question' => $question];

// Codificar a JSON
$json_output = json_encode($data, JSON_UNESCAPED_UNICODE);

// Verificar si falló el json_encode
if ($json_output === false) {
    error_log('captcha_generator.php - json_encode falló: ' . json_last_error_msg());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Enviar respuesta
echo $json_output;
exit();
<?php
/**
 * api/hosting.php
 * Endpoint seguro para acciones de la sección de hosting.
 */

require_once '../bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// 1. Verificar autenticación del usuario
if (empty($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit;
}

$action = $_GET['action'] ?? null;
$type = $_GET['type'] ?? null;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action === 'get_password' && $id > 0 && in_array($type, ['hosting_account', 'hosting_ftp', 'hosting_email'])) {
    require_once '../database.php';
    $pdo = get_database_connection($config, false);

    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error del servidor.']);
        exit;
    }

    $table_map = [
        'hosting_account'   => 'dc_hosting_accounts',
        'hosting_ftp'       => 'dc_hosting_ftp_accounts',
        'hosting_email'     => 'dc_hosting_emails',
        'dc_credential'     => 'dc_credentials' // Añadimos la tabla de credenciales del datacenter
    ];

    $table_name = $table_map[$type];

    // Usar una consulta preparada para evitar inyección SQL en el nombre de la tabla
    $stmt = $pdo->prepare("SELECT password FROM `{$table_name}` WHERE id = ?");
    $stmt->execute([$id]);
    $encrypted_password = $stmt->fetchColumn();

    if ($encrypted_password) {
        // Inicializar el servicio de cifrado para descifrar la contraseña
        $decrypted_password = decrypt_password($encrypted_password, $config);
        if ($decrypted_password === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al descifrar credencial.']);
            exit;
        }

        echo json_encode(['success' => true, 'password' => $decrypted_password]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se encontró la credencial.']);
    }
    exit;
}

http_response_code(400); // Bad Request
echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
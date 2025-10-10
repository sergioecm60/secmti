<?php
/**
 * api/datacenter.php
 * Endpoint seguro para acciones del datacenter.
 */

require_once '../bootstrap.php';
require_once '../database.php'; // Asegurar que la función de conexión a BD esté disponible
use SecMTI\Util\Encryption;

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// 1. Verificar autenticación del usuario
if (empty($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit;
}

try {
    $action = $_GET['action'] ?? null;
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    $pdo = get_database_connection($config, false);
    if (!$pdo) {
        throw new Exception('No se pudo conectar a la base de datos.', 500);
    }

    switch ($action) {
        case 'get_password':
            if ($id <= 0) throw new Exception('ID de credencial no válido.', 400);

            $stmt = $pdo->prepare("SELECT password FROM dc_credentials WHERE id = ?");
            $stmt->execute([$id]);
            $encrypted_password = $stmt->fetchColumn();
            
            if (!$encrypted_password) throw new Exception('No se encontró la credencial.', 404);

            if (empty($config['encryption_key']) || strlen(base64_decode($config['encryption_key'])) !== 32) {
                throw new Exception('Error de configuración de cifrado en el servidor.', 500);
            }
            $encryption = new Encryption(base64_decode($config['encryption_key']));
            $decrypted_password = $encryption->decrypt($encrypted_password);

            echo json_encode(['success' => true, 'password' => $decrypted_password]);
            break;

        case 'get_server_details':
            if (($_SESSION['user_role'] ?? 'user') !== 'admin') {
                throw new Exception('No tienes permiso para esta acción.', 403);
            }
            if ($id <= 0) throw new Exception('ID de servidor no válido.', 400);

            $stmt = $pdo->prepare("SELECT * FROM dc_servers WHERE id = ?");
            $stmt->execute([$id]);
            $server = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$server) throw new Exception('Servidor no encontrado.', 404);

            $server['net_dns'] = json_decode($server['net_dns'] ?? '[]', true);

            // Cargar servicios y sus credenciales asociadas (N+1 optimizado)
            $stmt_services = $pdo->prepare("SELECT * FROM dc_services WHERE server_id = ? ORDER BY name");
            $stmt_services->execute([$id]);
            $services = $stmt_services->fetchAll(PDO::FETCH_ASSOC);

            $service_ids = array_column($services, 'id');
            $all_credentials = [];
            if (!empty($service_ids)) {
                $in_sql_svc = implode(',', array_fill(0, count($service_ids), '?'));
                $stmt_creds = $pdo->prepare("SELECT id, service_id, username, role, notes FROM dc_credentials WHERE service_id IN ($in_sql_svc) ORDER BY role DESC");
                $stmt_creds->execute($service_ids);
                foreach ($stmt_creds->fetchAll(PDO::FETCH_ASSOC) as $cred) {
                    $all_credentials[$cred['service_id']][] = $cred;
                }
            }

            foreach ($services as &$service) {
                $service['credentials'] = $all_credentials[$service['id']] ?? [];
            }
            $server['services'] = $services;

            echo json_encode(['success' => true, 'data' => $server]);
            break;

        default:
            throw new Exception('Acción no válida.', 400);
    }

} catch (Exception $e) {
    // Capturar cualquier excepción para devolver una respuesta JSON controlada.
    $code = $e->getCode();
    if ($code < 400 || $code >= 600) {
        $code = 500; // Default a error interno del servidor
    }
    http_response_code($code);
    error_log("API Datacenter Error: " . $e->getMessage()); // Loguear el error real
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
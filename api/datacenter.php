<?php
/**
 * api/datacenter.php
 * Endpoint seguro para acciones del datacenter.
 */

require_once '../bootstrap.php';
use SecMTI\Util\Encryption;

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// 1. Verificar autenticación del usuario
if (empty($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit;
}

$action = $_GET['action'] ?? null;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$pdo = get_database_connection($config, false);
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor.']);
    exit;
}

switch ($action) {
    case 'get_password':
        if ($id > 0) {
            // Se asume que tienes una forma de desencriptar la contraseña si está cifrada.
            $stmt = $pdo->prepare("SELECT password FROM dc_credentials WHERE id = ?");
            $stmt->execute([$id]);
            $encrypted_password = $stmt->fetchColumn();
            
            if ($encrypted_password) {
                if (empty($config['encryption_key']) || strlen(base64_decode($config['encryption_key'])) !== 32) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Error de configuración del servidor.']);
                    exit;
                }
                $encryption = new Encryption(base64_decode($config['encryption_key']));
                $decrypted_password = $encryption->decrypt($encrypted_password);

                echo json_encode(['success' => true, 'password' => $decrypted_password]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No se encontró la credencial.']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID de credencial no válido.']);
        }
        break;

    case 'get_server_details':
        // Solo los administradores pueden obtener detalles completos para editar.
        if (($_SESSION['user_role'] ?? 'user') !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tienes permiso para esta acción.']);
            break;
        }
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM dc_servers WHERE id = ?");
            $stmt->execute([$id]);
            $server = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($server) {
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
            } else {
                echo json_encode(['success' => false, 'message' => 'Servidor no encontrado.']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID de servidor no válido.']);
        }
        break;

    default:
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
}
<?php
/**
 * api/datacenter.php
 * Endpoint seguro para acciones del datacenter.
 * Endpoint seguro para acciones del datacenter - Versión optimizada
 */

require_once '../bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// 1. Verificar autenticación del usuario
if (empty($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

try {
    // Determinar la acción desde GET o POST
    $action = $_REQUEST['action'] ?? null;
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    // Validar CSRF solo para acciones que no son de lectura (no-GET)
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        validate_request_csrf();
    }
    
    // Obtener la conexión a la BD aquí para asegurar su disponibilidad
    $pdo = get_database_connection($config, false);
    if (!$pdo) {
        throw new Exception('Error de conexión a base de datos', 500);
    }
    
    switch ($action) {
        case 'get_password':
            if ($id <= 0) {
                throw new Exception('ID de credencial inválido', 400);
            }

            $type = $_REQUEST['type'] ?? null;
            
            $table_map = [
                'server_main'       => 'dc_servers',
                'dc_credential'     => 'dc_credentials',
                'hosting_account'   => 'dc_hosting_accounts',
                'hosting_ftp'       => 'dc_hosting_ftp_accounts',
                'hosting_email'     => 'dc_hosting_emails',
            ];

            if (!isset($table_map[$type])) {
                throw new Exception('Tipo de credencial no válido', 400);
            }

            $table_name = $table_map[$type];
            $encrypted_password = null;

            $stmt = $pdo->prepare("SELECT password FROM `{$table_name}` WHERE id = ?");
            $stmt->execute([$id]);
            $encrypted_password = $stmt->fetchColumn();
            
            if ($encrypted_password === false || $encrypted_password === null) {
                throw new Exception('Contraseña no disponible', 404);
            }

            // Descifrar la contraseña
            $decrypted_password = decrypt_password($encrypted_password, $config);
            if ($decrypted_password === false) { throw new Exception('Error al descifrar contraseña', 500); }
            echo json_encode(['success' => true, 'password' => $decrypted_password]);
            break;

        case 'get_server_details':
            if (($_SESSION['user_role'] ?? 'user') !== 'admin') {
                throw new Exception('Permisos insuficientes', 403);
            }
            
            if ($id <= 0) {
                throw new Exception('ID de servidor inválido', 400);
            }

            // Obtener servidor
            // Excluimos explícitamente la contraseña de la consulta
            $stmt = $pdo->prepare("
                SELECT id, server_id, label, type, location_id, status, hw_model, hw_cpu, hw_ram, 
                       hw_disk, net_ip_lan, net_ip_wan, net_host_external, net_gateway, net_dns, 
                       notes, username, created_at, updated_at, created_by 
                FROM dc_servers WHERE id = ?
            ");
            $stmt->execute([$id]);
            $server = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$server) {
                throw new Exception('Servidor no encontrado', 404);
            }

            $server['net_dns'] = json_decode($server['net_dns'] ?? '[]', true);

            // Cargar servicios y sus credenciales asociadas (N+1 optimizado)
            // Excluimos la contraseña de las credenciales
            $stmt_services = $pdo->prepare("
                SELECT id, server_id, service_id, name, url_internal, url_external, port, protocol, notes, created_at, updated_at 
                FROM dc_services WHERE server_id = ? ORDER BY name
            ");
            $stmt_services->execute([$id]);
            $services = $stmt_services->fetchAll(PDO::FETCH_ASSOC);

            $service_ids = array_column($services, 'id');
            $all_credentials = [];
            
            if (!empty($service_ids)) {
                $in_sql = implode(',', array_fill(0, count($service_ids), '?'));
                $stmt_creds = $pdo->prepare("
                    SELECT id, service_id, username, role, notes
                    FROM dc_credentials 
                    WHERE service_id IN ($in_sql)
                ");
                $stmt_creds->execute($service_ids);
                
                foreach ($stmt_creds->fetchAll(PDO::FETCH_ASSOC) as $cred) {
                    $all_credentials[$cred['service_id']][] = $cred;
                }
            }

            // Ensamblar
            foreach ($services as &$service) {
                $service['credentials'] = $all_credentials[$service['id']] ?? [];
            }
            $server['services'] = $services;

            echo json_encode(['success' => true, 'server' => $server]);
            break;

        default:
            throw new Exception('Acción no válida', 400);
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
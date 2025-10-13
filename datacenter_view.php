<?php
// datacenter_view.php - Vista de infraestructura con MySQL
require_once 'bootstrap.php';
require_once 'database.php';

$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self';");

// Verificar autenticación y rol de administrador
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? 'user') !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = get_database_connection($config, true);
$status_message = '';

// ============================================================================
// GUARDAR CAMBIOS (Lógica movida desde datacenter_manager_mysql.php)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $status_message = '<div class="status-message error">Error de validación CSRF. Intente de nuevo.</div>';
    } else {
        try {
            $pdo->beginTransaction();
            
            switch ($_POST['action']) {
                case 'save_server':
                    $server_data = $_POST['server'];
                    $encryption = new \SecMTI\Util\Encryption(base64_decode($config['encryption_key']));
                    
                    if (empty($server_data['id']) || strpos($server_data['id'], 'new_') === 0) {
                        // NUEVO SERVIDOR
                        $stmt = $pdo->prepare("
                            INSERT INTO dc_servers (server_id, label, type, location_id, status, hw_model, hw_cpu, hw_ram, hw_disk, net_ip_lan,
                                                    net_ip_wan, net_host_external, net_gateway, net_dns, notes, username, password, created_by)
                            VALUES (?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $server_id = 'srv_' . uniqid();
                        $stmt->execute([
                            $server_id, $server_data['label'], $server_data['type'] ?? 'physical', $server_data['location_id'] ?: null,
                            $server_data['hw_model'] ?? '', $server_data['hw_cpu'] ?? '', $server_data['hw_ram'] ?? '', $server_data['hw_disk'] ?? '',
                            $server_data['net_ip_lan'] ?? '', $server_data['net_ip_wan'] ?? '', $server_data['net_host_external'] ?? '', $server_data['net_gateway'] ?? '',
                            json_encode(array_filter(array_map('trim', explode(',', $server_data['net_dns'] ?? '')))), // DNS como JSON
                            $server_data['notes'] ?? '',
                            $server_data['username'] ?? '',
                            !empty($server_data['password']) ? $encryption->encrypt($server_data['password']) : null, // Cifrar contraseña
                            $_SESSION['user_id']
                        ]);
                        $db_server_id = $pdo->lastInsertId();
                    } else {
                        // ACTUALIZAR SERVIDOR
                        $stmt = $pdo->prepare("
                            UPDATE dc_servers SET label = ?, type = ?, location_id = ?, hw_model = ?, hw_cpu = ?, hw_ram = ?, hw_disk = ?, net_ip_lan = ?,
                                net_ip_wan = ?, net_host_external = ?, net_gateway = ?, net_dns = ?, notes = ?, username = ?
                            WHERE id = ?
                        ");
                        $params = [
                            $server_data['label'], $server_data['type'] ?? 'physical', $server_data['location_id'] ?: null,
                            $server_data['hw_model'] ?? '', $server_data['hw_cpu'] ?? '', $server_data['hw_ram'] ?? '', $server_data['hw_disk'] ?? '',
                            $server_data['net_ip_lan'] ?? '', $server_data['net_ip_wan'] ?? '', $server_data['net_host_external'] ?? '', $server_data['net_gateway'] ?? '',
                            json_encode(array_filter(array_map('trim', explode(',', $server_data['net_dns'] ?? '')))), // DNS como JSON
                            $server_data['notes'] ?? '', $server_data['username'] ?? '', $server_data['id']
                        ];
                        $stmt->execute($params);

                        // Actualizar contraseña solo si se proporcionó una nueva
                        if (!empty($server_data['password'])) {
                            $stmt_pass = $pdo->prepare("UPDATE dc_servers SET password = ? WHERE id = ?");
                            $stmt_pass->execute([$encryption->encrypt($server_data['password']), $server_data['id']]); // Cifrar contraseña
                        }
                        $db_server_id = $server_data['id'];
                    }
                    
                    // Lógica para servicios y credenciales (simplificada para brevedad, pero la idea es la misma)
                    $services_data = $_POST['services'] ?? [];
                    $submitted_service_ids = [];
                    foreach ($services_data as $service_id_key => $service_item) {
                        if (empty($service_item['name'])) continue;

                        if (strpos($service_id_key, 'new_') === 0) {
                            $service_unique_id = 'svc_' . uniqid();
                            $stmt_svc = $pdo->prepare("INSERT INTO dc_services (server_id, service_id, name, url_internal, url_external, port, protocol, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt_svc->execute([$db_server_id, $service_unique_id, $service_item['name'], $service_item['url_internal'] ?? '', $service_item['url_external'] ?? '', $service_item['port'] ?? '', $service_item['protocol'] ?? 'https', $service_item['notes'] ?? '']);
                            $current_service_id = $pdo->lastInsertId();
                        } else {
                            $stmt_svc = $pdo->prepare("UPDATE dc_services SET name=?, url_internal=?, url_external=?, port=?, protocol=?, notes=? WHERE id=?");
                            $stmt_svc->execute([$service_item['name'], $service_item['url_internal'] ?? '', $service_item['url_external'] ?? '', $service_item['port'] ?? '', $service_item['protocol'] ?? 'https', $service_item['notes'] ?? '', $service_id_key]);
                            $current_service_id = $service_id_key;
                            $submitted_service_ids[] = $current_service_id;
                        }

                        // --- Lógica para credenciales de este servicio ---
                        $credentials_data = $service_item['credentials'] ?? [];
                        $submitted_cred_ids = [];
                        foreach ($credentials_data as $cred_id_key => $cred_item) {
                            if (empty($cred_item['username'])) continue;

                            if (strpos($cred_id_key, 'new_') === 0) {
                                if (empty($cred_item['password'])) continue; // La contraseña es obligatoria para credenciales nuevas
                                $stmt_cred = $pdo->prepare("INSERT INTO dc_credentials (service_id, credential_id, username, password, role, notes) VALUES (?, ?, ?, ?, ?, ?)");
                                $stmt_cred->execute([$current_service_id, 'cred_' . uniqid(), $cred_item['username'], $encryption->encrypt($cred_item['password']), $cred_item['role'] ?? 'user', $cred_item['notes'] ?? '']);
                            } else {
                                $stmt_cred = $pdo->prepare("UPDATE dc_credentials SET username=?, role=?, notes=? WHERE id=?");
                                $stmt_cred->execute([$cred_item['username'], $cred_item['role'] ?? 'user', $cred_item['notes'] ?? '', $cred_id_key]);
                                if (!empty($cred_item['password'])) {
                                    $stmt_pass = $pdo->prepare("UPDATE dc_credentials SET password=? WHERE id=?");
                                    $stmt_pass->execute([$encryption->encrypt($cred_item['password']), $cred_id_key]);
                                }
                                $submitted_cred_ids[] = $cred_id_key;
                            }
                        }
                        // Eliminar credenciales que ya no están en el formulario para este servicio
                        $stmt_current_creds = $pdo->prepare("SELECT id FROM dc_credentials WHERE service_id = ?");
                        $stmt_current_creds->execute([$current_service_id]);
                        $current_cred_ids = $stmt_current_creds->fetchAll(PDO::FETCH_COLUMN);
                        $cred_ids_to_delete = array_diff($current_cred_ids, $submitted_cred_ids);
                        if (!empty($cred_ids_to_delete)) {
                            $cred_placeholders = implode(',', array_fill(0, count($cred_ids_to_delete), '?'));
                            $stmt_del_cred = $pdo->prepare("DELETE FROM dc_credentials WHERE id IN ($cred_placeholders)");
                            $stmt_del_cred->execute($cred_ids_to_delete);
                        }
                    }

                    // Eliminar servicios que ya no están en el formulario
                    if (!empty($db_server_id) && strpos($db_server_id, 'new_') !== 0) {
                        $stmt_current_ids = $pdo->prepare("SELECT id FROM dc_services WHERE server_id = ?");
                        $stmt_current_ids->execute([$db_server_id]);
                        $current_ids = $stmt_current_ids->fetchAll(PDO::FETCH_COLUMN);
                        $ids_to_delete = array_diff($current_ids, $submitted_service_ids);
                        if (!empty($ids_to_delete)) {
                            $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
                            $stmt_del = $pdo->prepare("DELETE FROM dc_services WHERE id IN ($placeholders)");
                            $stmt_del->execute($ids_to_delete);
                        }
                    }
                    // ...
                    
                    $pdo->commit();
                    $status_message = '<div class="status-message success">✅ Servidor guardado exitosamente</div>';
                    break;
                    
                case 'delete_server':
                    $server_id = $_POST['server_id'];
                    $stmt = $pdo->prepare("DELETE FROM dc_servers WHERE id = ?");
                    $stmt->execute([$server_id]);
                    $pdo->commit();
                    // Si es una petición AJAX, respondemos con JSON
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Servidor eliminado.']);
                        exit;
                    }
                    $status_message = '<div class="status-message success">✅ Servidor eliminado</div>';
                    break;
            }
            
            if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Location: ' . $_SERVER['PHP_SELF'] . '?status=' . urlencode(strip_tags($status_message)));
                exit;
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = '❌ Error: ' . htmlspecialchars($e->getMessage());
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json', true, 500);
                echo json_encode(['success' => false, 'message' => $error_msg]);
                exit;
            }
            $status_message = '<div class="status-message error">' . $error_msg . '</div>';
            error_log('Datacenter View/Manager Error: ' . $e->getMessage());
        }
    }
}

// Mostrar mensaje de estado si viene de una redirección
if (isset($_GET['status'])) {
    $status_message = '<div class="status-message success">' . htmlspecialchars($_GET['status']) . '</div>';
}

// Registrar acceso en log
// Esta es una buena práctica para la auditoría de seguridad.
try {
    $stmt = $pdo->prepare("INSERT INTO dc_access_log (action, entity_type, entity_id, ip_address) VALUES ('view', 'infrastructure', 0, ?)");
    $stmt->execute([IP_ADDRESS]);
} catch (Exception $e) {
    error_log('Error logging access: ' . $e->getMessage());
}

// --- LÓGICA DE CARGA DE DATOS ---
$locations = [];
$search = $_GET['search'] ?? '';
$servers = [];

try {
    // 1. Obtener todas las ubicaciones
    $stmt_locations = $pdo->query("SELECT id, name FROM dc_locations ORDER BY name");
    $locations = $stmt_locations->fetchAll(PDO::FETCH_ASSOC);

    // 2. Obtener los servidores
    $base_query = "
        SELECT s.id, s.server_id, s.label, s.type, s.location_id, s.status, s.hw_model, s.hw_cpu, s.hw_ram, s.hw_disk, s.net_ip_lan, s.net_ip_wan, s.net_host_external, s.net_gateway, s.net_dns, s.notes, s.username, s.password, l.name as location_name FROM dc_servers s 
        LEFT JOIN dc_locations l ON s.location_id = l.id
    ";

    if (!empty($search)) {
        // Búsqueda con CALL a procedimiento almacenado
        $stmt = $pdo->prepare("CALL sp_search_infrastructure(?)");
        $stmt->execute([$search]);
        $servers_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Cargar todos
        $stmt = $pdo->query($base_query . " WHERE s.status = 'active' ORDER BY s.label");
        $servers_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- OPTIMIZACIÓN N+1 ---
    // En lugar de hacer una consulta por cada servidor para obtener sus servicios (N+1 queries),
    // obtenemos todos los datos necesarios en solo 3 consultas eficientes.
    $server_ids = array_column($servers_raw, 'id');
    $all_services = [];
    $all_credentials = [];

    if (!empty($server_ids)) {
        // 1. OBTENER SERVICIOS: Traemos todos los servicios de los servidores encontrados.
        $in_sql = implode(',', array_fill(0, count($server_ids), '?'));
        $stmt_services = $pdo->prepare("SELECT * FROM dc_services WHERE server_id IN ($in_sql) ORDER BY name");
        $stmt_services->execute($server_ids);
        $services_raw = $stmt_services->fetchAll(PDO::FETCH_ASSOC);
        
        // Extraemos los IDs de los servicios para la siguiente consulta.
        $service_ids = array_column($services_raw, 'id');

        // 2. OBTENER CREDENCIALES: Traemos todas las credenciales de los servicios encontrados.
        if (!empty($service_ids)) {
            $in_sql_svc = implode(',', array_fill(0, count($service_ids), '?'));
            // Seleccionamos la contraseña cifrada para poder usar la función de copiar.
            $stmt_creds = $pdo->prepare("SELECT id, service_id, username, role, notes, password FROM dc_credentials WHERE service_id IN ($in_sql_svc) ORDER BY role DESC");
            $stmt_creds->execute($service_ids);
            
            // Agrupamos las credenciales por el ID de su servicio para un fácil acceso.
            foreach ($stmt_creds->fetchAll(PDO::FETCH_ASSOC) as $cred) {
                $all_credentials[$cred['service_id']][] = $cred;
            }
        }

        // Agrupar servicios por server_id
        foreach ($services_raw as $service) {
            // Asignamos las credenciales correspondientes a cada servicio.
            $service['credentials'] = $all_credentials[$service['id']] ?? [];
            $all_services[$service['server_id']][] = $service;
        }
    }

    // 3. ENSAMBLAR ESTRUCTURA FINAL: Recorremos los servidores y les asignamos sus servicios ya poblados.
    foreach ($servers_raw as $server_data) {
        $server_data['net_dns'] = json_decode($server_data['net_dns'] ?? '[]', true);
        $server_data['services'] = $all_services[$server_data['id']] ?? [];
        $servers[] = $server_data;
    }

    // --- NUEVO: Agrupar servidores por ubicación ---
    $grouped_servers = [];
    foreach ($servers as $server) {
        $location_id = $server['location_id'] ?? 0; // 0 para 'Sin Ubicación'
        if (!isset($grouped_servers[$location_id])) {
            $grouped_servers[$location_id] = ['name' => $server['location_name'] ?? 'Sin Ubicación', 'servers' => []];
        }
        $grouped_servers[$location_id]['servers'][] = $server;
    }
} catch (Exception $e) {
    error_log('Error loading infrastructure: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Infraestructura</title>
    <link rel="stylesheet" href="./assets/css/main.css">
    <link rel="stylesheet" href="./assets/css/datacenter.css">
</head>
<body class="page-manage">
    <div class="admin-container admin-container-full-width">
        <header class="admin-header">
            <h1>🏢 Gestión de Infraestructura</h1>
            <p>Visualice, agregue, edite y elimine los activos de su datacenter.</p>
        </header>

        <?= $status_message ?>

        <!-- Estadísticas (sin cambios) -->
        <?php
        $total_servers = count($servers);
        $total_services = array_sum(array_map(function($s) { return count($s['services']); }, $servers));
        $total_credentials = array_sum(array_map(function($s) { 
            return array_sum(array_map(function($sv) { 
                return count($sv['credentials']); 
            }, $s['services'])); 
        }, $servers));
        ?>
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-number"><?= $total_servers ?></div>
                <div class="stat-label">Servidores</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $total_services ?></div>
                <div class="stat-label">Servicios</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $total_credentials ?></div>
                <div class="stat-label">Credenciales</div>
            </div>
        </div>

        <!-- Búsqueda -->
        <div class="search-box">
            <form method="GET" action="">
                <label for="main-search-input" class="visually-hidden">Buscar en infraestructura</label>
                <input type="search" id="main-search-input" name="search" value="<?= htmlspecialchars($search) ?>"
                       placeholder="🔍 Buscar servidor, IP, servicio..."
                       autocomplete="off">
            </form>
            <a href="locations_manager.php" class="action-btn action-btn--warning">📍 Gestionar Ubicaciones</a>
            <button type="button" id="addServerBtn" class="add-btn">+ Agregar Servidor</button>
        </div>

        <!-- Token CSRF para que lo use el JS de borrado -->
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <?php if (empty($servers)): ?>
            <div class="no-data">
                <h2><?= empty($search) ? 'No hay servidores configurados' : 'No se encontraron resultados' ?></h2>
                <p><?= empty($search) ? 'Comience agregando infraestructura' : 'Intente con otro término de búsqueda' ?></p>
            </div>
        <?php else: ?>
            <?php if ($total_servers > 1): ?>
                <div class="view-all-controls">
                    <button type="button" id="expandAllBtn" class="quick-link">Expandir Todo</button>
                    <button type="button" id="collapseAllBtn" class="quick-link">Contraer Todo</button>
                </div>
            <?php endif; ?>

            <!-- NUEVO: Contenedor de secciones por ubicación -->
            <div class="sections-container">
                <?php foreach ($grouped_servers as $location_id => $group): ?>
                <section class="service-section" id="location-<?= $location_id ?>">
                    <h2 class="section-title">
                        <button class="section-toggle-btn" aria-expanded="true">
                            <span class="section-title-text">📍 <?= htmlspecialchars($group['name']) ?></span>
                            <span class="section-badge"><?= count($group['servers']) ?></span>
                        </button>
                    </h2>
                    <div class="section-body servers-grid">
                        <?php foreach ($group['servers'] as $server): ?>
                        <div class="server-card collapsed" data-server-id="<?= $server['id'] ?>">
                            <div class="server-header">
                                <div>
                                    <?php
                                        $icons = ['physical' => '🖥️', 'virtual' => '💿', 'container' => '📦', 'cloud' => '☁️', 'isp' => '🌐'];
                                        $icon = $icons[$server['type']] ?? '⚙️';
                                        echo $icon;
                                    ?>
                                    <strong><?= htmlspecialchars($server['label']) ?></strong>
                                </div>
                                <div class="server-header-actions">
                                    <button type="button" class="view-toggle-btn edit-btn" data-server-id="<?= $server['id'] ?>" aria-label="Editar servidor" title="Editar Servidor">✏️</button>
                                    <button type="button" class="view-toggle-btn delete-btn" data-server-id="<?= $server['id'] ?>" data-server-name="<?= htmlspecialchars($server['label']) ?>" aria-label="Eliminar servidor" title="Eliminar Servidor">🗑️</button>
                                    <button type="button" class="view-toggle-btn" aria-expanded="false" aria-controls="server-body-<?= $server['id'] ?>" aria-label="Expandir/Contraer servidor <?= htmlspecialchars($server['label']) ?>" title="Ver/Ocultar Detalles">▶</button>
                                </div>
                            </div>

                            <div class="server-body" id="server-body-<?= $server['id'] ?>">
                                <div class="server-type-badge-body"><?= ucfirst($server['type']) ?></div>
                                <!-- Hardware -->
                                <?php if (!empty($server['hw_model'])): ?>
                                <div class="info-row">
                                    <div class="info-label">💻 Hardware</div>
                                    <div><?= htmlspecialchars($server['hw_model']) ?>
                                        <?php if (!empty($server['hw_cpu']) || !empty($server['hw_ram'])): ?>
                                        <br><small>
                                            <?= htmlspecialchars($server['hw_cpu']) ?>
                                            <?= !empty($server['hw_ram']) ? ' | ' . htmlspecialchars($server['hw_ram']) : '' ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Red -->
                                <?php if (!empty($server['net_ip_lan']) || !empty($server['net_ip_wan'])): ?>
                                <div class="info-row">
                                    <div class="info-label">🌐 Red</div>
                                    <div class="network-grid">
                                        <?php if (!empty($server['net_ip_lan'])): ?>
                                        <div class="network-item">
                                            <span class="network-label">LAN</span>
                                            <span class="network-value"><?= htmlspecialchars($server['net_ip_lan']) ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($server['net_ip_wan'])): ?>
                                        <div class="network-item">
                                            <span class="network-label">WAN</span>
                                            <span class="network-value"><?= htmlspecialchars($server['net_ip_wan']) ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($server['net_host_external'])): ?>
                                        <div class="network-item">
                                            <span class="network-label">Host</span>
                                            <span class="network-value"><?= htmlspecialchars($server['net_host_external']) ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($server['net_gateway'])): ?>
                                        <div class="network-item">
                                            <span class="network-label">Gateway</span>
                                            <span class="network-value"><?= htmlspecialchars($server['net_gateway']) ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Credenciales Principales del Servidor -->
                                <?php if (!empty($server['username'])): ?>
                                <div class="info-row">
                                    <div class="info-label">🔑 Credencial Principal</div>
                                    <div class="cred-row">
                                        <span>
                                            👤 <strong><?= htmlspecialchars($server['username']) ?></strong>
                                        </span>
                                        <?php if (!empty($server['password'])): // Mostrar el botón solo si hay una contraseña para copiar ?>
                                            <button type="button" class="copy-cred-btn" data-type="server_main" data-id="<?= $server['id'] ?>" title="Copiar contraseña del servidor">📋</button>
                                        <?php endif; ?>                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Servicios -->
                                <?php if (!empty($server['services'])): ?>
                                <div class="info-row">
                                    <div class="info-label">⚙️ Servicios (<?= count($server['services']) ?>)</div>
                                    <?php foreach ($server['services'] as $service): ?>
                                    <div class="service-card-wrapper">
                                    <div class="service-card">
                                        <div class="service-title">
                                            <?= htmlspecialchars($service['name']) ?>
                                            <?php if (!empty($service['port'])): ?>
                                            <small class="port-number">:<?= htmlspecialchars($service['port']) ?></small>
                                            <?php endif; ?>
                                        </div>

                                        <div class="quick-links">
                                            <?php if (!empty($service['url_internal'])): ?>
                                            <a href="<?= htmlspecialchars($service['url_internal']) ?>" 
                                               target="_blank" class="quick-link">🏠 LAN</a>
                                            <?php endif; ?>
                                            <?php if (!empty($service['url_external'])): ?>
                                            <a href="<?= htmlspecialchars($service['url_external']) ?>" 
                                               target="_blank" class="quick-link">🌍 WAN</a>
                                            <?php endif; ?>
                                        </div>

                                        <?php require 'templates/credentials_list.php'; // Muestra la lista de credenciales ?>
                                     </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <button type="button" class="add-btn add-service-quick-btn" data-server-id="<?= $server['id'] ?>">+ Agregar Servicio</button>
                                </div>
                                <?php endif; // Fin de if (!empty($server['services'])) ?>

                                <!-- Notas -->
                                <?php require 'templates/notes_section.php'; // Muestra la sección de notas ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <a href="./index2.php" class="back-btn">← Volver al Portal</a>

    <?php require_once 'templates/footer.php'; ?>

    <!-- Modal para Agregar/Editar Servidor (movido desde datacenter_manager_mysql.php) -->
    <div id="serverModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modalTitle">Agregar Servidor</h2>
            <form method="POST" id="serverForm" action="datacenter_view.php">
                <input type="hidden" name="action" value="save_server">

                <!-- Pestañas de navegación del modal -->
                <div class="modal-tabs">
                    <button type="button" class="tab-link active" data-tab="tab-general">General</button>
                    <button type="button" class="tab-link" data-tab="tab-services">Servicios</button>
                    <button type="button" class="tab-link" data-tab="tab-network">Red</button>
                </div>

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="server[id]" id="serverId">
                
                <div class="form-group">
                    <label for="serverLocation">Ubicación</label>
                    <select name="server[location_id]" id="serverLocation">
                        <option value="">-- Sin Ubicación --</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?= $location['id'] ?>">
                                <?= htmlspecialchars($location['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <div id="tab-general" class="tab-content active">
                        <div class="form-group">
                            <label for="serverLabel">Nombre del Servidor *</label>
                            <input type="text" name="server[label]" id="serverLabel" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="serverType">Tipo</label>
                            <select name="server[type]" id="serverType">
                                <option value="physical">Físico</option>
                                <option value="virtual">Virtual</option>
                                <option value="container">Container</option>
                                <option value="cloud">Cloud</option>
                                <option value="isp">Proveedor de Internet</option>
                            </select>
                        </div>

                        <h3>💻 Hardware</h3>
                        <div class="form-grid form-grid-with-labels">
                            <div class="form-group"><label for="hwModel">Modelo</label><input type="text" name="server[hw_model]" id="hwModel" placeholder="Ej: Dell PowerEdge R740"></div>
                            <div class="form-group"><label for="hwCpu">CPU</label><input type="text" name="server[hw_cpu]" id="hwCpu" placeholder="Ej: 2x Xeon Gold 6248R"></div>
                            <div class="form-group"><label for="hwRam">RAM</label><input type="text" name="server[hw_ram]" id="hwRam" placeholder="Ej: 128GB DDR4"></div>
                            <div class="form-group"><label for="hwDisk">Disco</label><input type="text" name="server[hw_disk]" id="hwDisk" placeholder="Ej: 2x 1TB NVMe RAID1"></div>
                        </div>

                        <h3>🔑 Credencial Principal</h3>
                        <div class="form-grid form-grid-with-labels">
                            <div class="form-group"><label for="serverUsername">Usuario Principal</label><input type="text" name="server[username]" id="serverUsername" placeholder="Ej: root, admin" autocomplete="username"></div>
                            <div class="form-group"><label for="serverPassword">Contraseña Principal</label><input type="password" name="server[password]" id="serverPassword" placeholder="Dejar en blanco para no cambiar" autocomplete="new-password"></div>
                        </div>

                        <div class="form-group">
                            <label for="serverNotes">📝 Notas Generales del Servidor</label>
                            <textarea name="server[notes]" id="serverNotes" rows="3"></textarea>
                        </div>
                    </div>

                    <div id="tab-services" class="tab-content">
                        <h3>⚙️ Servicios</h3>
                        <div id="servicesContainer" class="dynamic-container"></div>
                        <button type="button" class="add-btn" id="addServiceModalBtn">+ Agregar Servicio</button>
                    </div>

                    <div id="tab-network" class="tab-content">
                        <h3>🌐 Red</h3>
                        <div class="form-grid form-grid-with-labels">
                            <div class="form-group">
                                <label for="netIpWan">IP WAN</label>
                                <input type="text" name="server[net_ip_wan]" id="netIpWan" placeholder="IP WAN">
                            </div>
                            <div class="form-group">
                                <label for="netGateway">Gateway</label>
                                <input type="text" name="server[net_gateway]" id="netGateway" placeholder="Gateway">
                            </div>
                            <div class="form-group">
                                <label for="netDns">DNS</label>
                                <input type="text" name="server[net_dns]" id="netDns" placeholder="DNS (separados por coma)">
                            </div>
                            <div class="form-group">
                                <label for="netIpLan">IP LAN</label>
                                <input type="text" name="server[net_ip_lan]" id="netIpLan" placeholder="IP LAN">
                            </div>
                            <div class="form-group">
                                <label for="netHostExt">Host Externo</label>
                                <input type="text" name="server[net_host_external]" id="netHostExt" placeholder="Host externo">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="save-btn">💾 Guardar</button>
                    <button type="button" class="cancel-btn">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <?php require_once 'templates/footer.php'; ?>
    <script src="assets/js/datacenter_view.js" nonce="<?= htmlspecialchars($nonce) ?>"></script>
</body>
</html>
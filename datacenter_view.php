<?php
// datacenter_view.php - Vista de infraestructura optimizada
require_once 'bootstrap.php';
require_once 'database.php';

$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self'; img-src 'self' data:;");

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
            $encryption = new \SecMTI\Util\Encryption(base64_decode($config['encryption_key']));
            
            switch ($_POST['action']) {
                case 'save_server':
                    $server_data = $_POST['server'];
                    $is_new = empty($server_data['id']) || strpos($server_data['id'], 'new_') === 0;
                    
                    if (empty($server_data['id']) || strpos($server_data['id'], 'new_') === 0) {
                        // NUEVO SERVIDOR
                        $stmt = $pdo->prepare("
                            INSERT INTO dc_servers (server_id, label, type, location_id, status, hw_model, hw_cpu, hw_ram, hw_disk, net_ip_lan,
                                                    net_ip_wan, net_host_external, net_gateway, net_dns, notes, username, password, created_by)
                            VALUES (?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $server_id = 'srv_' . uniqid();
                        $stmt->execute([
                            $server_id,
                            $server_data['label'],
                            $server_data['type'] ?? 'physical',
                            $server_data['location_id'] ?: null,
                            $server_data['hw_model'] ?? '',
                            $server_data['hw_cpu'] ?? '',
                            $server_data['hw_ram'] ?? '',
                            $server_data['hw_disk'] ?? '',
                            $server_data['net_ip_lan'] ?? '',
                            $server_data['net_ip_wan'] ?? '',
                            $server_data['net_host_external'] ?? '',
                            $server_data['net_gateway'] ?? '',
                            json_encode(array_filter(array_map('trim', explode(',', $server_data['net_dns'] ?? '')))),
                            $server_data['notes'] ?? '',
                            $server_data['username'] ?? '',
                            !empty($server_data['password']) ? $encryption->encrypt($server_data['password']) : null,
                            $_SESSION['user_id']
                        ]);
                        $db_server_id = $pdo->lastInsertId();
                    } else {
                        // ACTUALIZAR SERVIDOR
                        $stmt = $pdo->prepare("
                            UPDATE dc_servers SET 
                                label = ?, type = ?, location_id = ?, hw_model = ?, hw_cpu = ?, hw_ram = ?, hw_disk = ?, 
                                net_ip_lan = ?, net_ip_wan = ?, net_host_external = ?, net_gateway = ?, net_dns = ?, 
                                notes = ?, username = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $server_data['label'],
                            $server_data['type'] ?? 'physical',
                            $server_data['location_id'] ?: null,
                            $server_data['hw_model'] ?? '',
                            $server_data['hw_cpu'] ?? '',
                            $server_data['hw_ram'] ?? '',
                            $server_data['hw_disk'] ?? '',
                            $server_data['net_ip_lan'] ?? '',
                            $server_data['net_ip_wan'] ?? '',
                            $server_data['net_host_external'] ?? '',
                            $server_data['net_gateway'] ?? '',
                            json_encode(array_filter(array_map('trim', explode(',', $server_data['net_dns'] ?? '')))),
                            $server_data['notes'] ?? '',
                            $server_data['username'] ?? '',
                            $server_data['id']
                        ]);

                        // Actualizar contraseña solo si se proporcionó una nueva
                        if (!empty($server_data['password'])) {
                            $stmt_pass = $pdo->prepare("UPDATE dc_servers SET password = ? WHERE id = ?");
                            $stmt_pass->execute([$encryption->encrypt($server_data['password']), $server_data['id']]);
                        }
                        $db_server_id = $server_data['id'];
                    }
                    
                    // ========================================================================
                    // PROCESAR SERVICIOS Y CREDENCIALES (PARA NUEVOS Y EXISTENTES)
                    // ========================================================================
                    $services_data = $_POST['services'] ?? [];
                    $submitted_service_ids = [];

                    foreach ($services_data as $service_id_key => $service_item) {
                        if (empty($service_item['name'])) continue;

                        if (strpos($service_id_key, 'new_') === 0) {
                            // Nuevo servicio
                            $stmt_svc = $pdo->prepare("
                                INSERT INTO dc_services (server_id, service_id, name, url_internal, url_external, 
                                                        port, protocol, notes) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt_svc->execute([
                                $db_server_id,
                                'svc_' . uniqid(),
                                $service_item['name'],
                                $service_item['url_internal'] ?? '',
                                $service_item['url_external'] ?? '',
                                $service_item['port'] ?? '',
                                $service_item['protocol'] ?? 'https',
                                $service_item['notes'] ?? ''
                            ]);
                            $current_service_id = $pdo->lastInsertId();
                        } else {
                            // Actualizar servicio existente
                            $stmt_svc = $pdo->prepare("
                                UPDATE dc_services 
                                SET name=?, url_internal=?, url_external=?, port=?, protocol=?, notes=? 
                                WHERE id=?
                            ");
                            $stmt_svc->execute([
                                $service_item['name'],
                                $service_item['url_internal'] ?? '',
                                $service_item['url_external'] ?? '',
                                $service_item['port'] ?? '',
                                $service_item['protocol'] ?? 'https',
                                $service_item['notes'] ?? '',
                                $service_id_key
                            ]);
                            $current_service_id = $service_id_key;
                        }
                        $submitted_service_ids[] = $current_service_id;

                        // --- Lógica para credenciales de este servicio ---
                        $credentials_data = $service_item['credentials'] ?? [];
                        $submitted_cred_ids = [];
                        foreach ($credentials_data as $cred_id_key => $cred_item) {
                            if (empty($cred_item['username'])) continue;
                            
                            if (strpos($cred_id_key, 'new_') === 0) {
                                if (empty($cred_item['password'])) continue;
                                $stmt_cred = $pdo->prepare("
                                    INSERT INTO dc_credentials (service_id, credential_id, username, password, role, notes) 
                                    VALUES (?, ?, ?, ?, ?, ?)
                                ");
                                $stmt_cred->execute([
                                    $current_service_id,
                                    'cred_' . uniqid(),
                                    $cred_item['username'],
                                    $encryption->encrypt($cred_item['password']),
                                    $cred_item['role'] ?? 'user',
                                    $cred_item['notes'] ?? ''
                                ]);
                                $submitted_cred_ids[] = $pdo->lastInsertId();
                            } else {
                                $stmt_cred = $pdo->prepare("
                                    UPDATE dc_credentials SET username=?, role=?, notes=? WHERE id=?
                                ");
                                $stmt_cred->execute([
                                    $cred_item['username'],
                                    $cred_item['role'] ?? 'user',
                                    $cred_item['notes'] ?? '',
                                    $cred_id_key
                                ]);
                                if (!empty($cred_item['password'])) {
                                    $stmt_pass = $pdo->prepare("UPDATE dc_credentials SET password=? WHERE id=?");
                                    $stmt_pass->execute([$encryption->encrypt($cred_item['password']), $cred_id_key]);
                                }
                                $submitted_cred_ids[] = $cred_id_key;
                            }
                        }
                        // Eliminar credenciales no enviadas
                        if (strpos($service_id_key, 'new_') !== 0) {
                            $stmt_current_creds = $pdo->prepare("SELECT id FROM dc_credentials WHERE service_id = ?");
                            $stmt_current_creds->execute([$current_service_id]);
                            $current_cred_ids = $stmt_current_creds->fetchAll(PDO::FETCH_COLUMN);
                            $cred_ids_to_delete = array_diff($current_cred_ids, $submitted_cred_ids);
                            if (!empty($cred_ids_to_delete)) {
                                $placeholders = implode(',', array_fill(0, count($cred_ids_to_delete), '?'));
                                $stmt_del_cred = $pdo->prepare("DELETE FROM dc_credentials WHERE id IN ($placeholders)");
                                $stmt_del_cred->execute($cred_ids_to_delete);
                            }
                        }
                    }

                    // Eliminar servicios no enviados
                    if (!$is_new) {
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
                    $pdo->commit();
                    $status_message = '<div class="status-message success">✅ Servidor guardado exitosamente</div>';
                    break;
                    
                case 'delete_server':
                    $server_id = $_POST['server_id'];
                    $stmt = $pdo->prepare("DELETE FROM dc_servers WHERE id = ?");
                    $stmt->execute([$server_id]);
                    $pdo->commit();
                    
                    // Respuesta AJAX
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
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
            
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json', true, 500);
                echo json_encode(['success' => false, 'message' => $error_msg]);
                exit;
            }
            $status_message = '<div class="status-message error">' . $error_msg . '</div>';
            error_log('Datacenter View/Manager Error: ' . $e->getMessage());
        }
    }
}

// Mensaje de estado desde redirección
if (isset($_GET['status'])) {
    $status_message = '<div class="status-message success">' . htmlspecialchars($_GET['status']) . '</div>';
}

// Registrar acceso en log
try {
    $stmt = $pdo->prepare("INSERT INTO dc_access_log (action, entity_type, entity_id, ip_address) VALUES ('view', 'infrastructure', 0, ?)");
    $stmt->execute([IP_ADDRESS]);
} catch (Exception $e) {
    error_log('Error logging access: ' . $e->getMessage());
}

// ============================================================================
// CARGAR DATOS
// ============================================================================
$locations = [];
$search = $_GET['search'] ?? '';
$servers = [];

try {
    // Obtener ubicaciones
    $stmt_locations = $pdo->query("SELECT id, name FROM dc_locations ORDER BY name");
    $locations = $stmt_locations->fetchAll(PDO::FETCH_ASSOC);

    // Obtener servidores
    $base_query = "
        SELECT s.id, s.server_id, s.label, s.type, s.location_id, s.status, s.hw_model, s.hw_cpu, s.hw_ram, 
               s.hw_disk, s.net_ip_lan, s.net_ip_wan, s.net_host_external, s.net_gateway, s.net_dns, s.notes, 
               s.username, s.password, l.name as location_name 
        FROM dc_servers s 
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

    // OPTIMIZACIÓN: Evitar consultas N+1
    $server_ids = array_column($servers_raw, 'id');
    $all_services = [];
    $all_credentials = [];

    if (!empty($server_ids)) {
        // Obtener todos los servicios
        $in_sql = implode(',', array_fill(0, count($server_ids), '?'));
        $stmt_services = $pdo->prepare("SELECT * FROM dc_services WHERE server_id IN ($in_sql) ORDER BY name");
        $stmt_services->execute($server_ids);
        $services_raw = $stmt_services->fetchAll(PDO::FETCH_ASSOC);
        
        $service_ids = array_column($services_raw, 'id');

        // Obtener todas las credenciales
        if (!empty($service_ids)) {
            $in_sql_svc = implode(',', array_fill(0, count($service_ids), '?'));
            $stmt_creds = $pdo->prepare("
                SELECT id, service_id, username, role, notes, password 
                FROM dc_credentials 
                WHERE service_id IN ($in_sql_svc) 
                ORDER BY role DESC
            ");
            $stmt_creds->execute($service_ids);
            
            foreach ($stmt_creds->fetchAll(PDO::FETCH_ASSOC) as $cred) {
                if (!empty($cred['id']) && !empty($cred['service_id'])) {
                    $all_credentials[$cred['service_id']][] = $cred;
                } else {
                    error_log("Credencial inválida: " . json_encode($cred));
                }
            }
        }

        // Agrupar servicios por server_id
        foreach ($services_raw as $service) {
            $service['credentials'] = $all_credentials[$service['id']] ?? [];
            $all_services[$service['server_id']][] = $service;
        }
    }

    // Ensamblar estructura final
    foreach ($servers_raw as $server_data) {
        $server_data['net_dns'] = json_decode($server_data['net_dns'] ?? '[]', true);
        $server_data['services'] = $all_services[$server_data['id']] ?? [];
        $servers[] = $server_data;
    }

    // Agrupar servidores por ubicación
    $grouped_servers = [];
    foreach ($servers as $server) {
        $location_id = $server['location_id'] ?? 0;
        if (!isset($grouped_servers[$location_id])) {
            $grouped_servers[$location_id] = [
                'name' => $server['location_name'] ?? 'Sin Ubicación',
                'servers' => []
            ];
        }
        $grouped_servers[$location_id]['servers'][] = $server;
    }

} catch (Exception $e) {
    error_log('Error loading infrastructure: ' . $e->getMessage());
}

// Calcular estadísticas
$total_servers = count($servers);
$total_services = array_sum(array_map(function($s) { return count($s['services']); }, $servers));
$total_credentials = array_sum(array_map(function($s) { 
    return array_sum(array_map(function($sv) { 
        return count($sv['credentials']); 
    }, $s['services']));
}, $servers));

// TEMPORAL: Solo para depuración - ELIMINAR en producción
if (isset($_GET['debug']) && $_SESSION['user_role'] === 'admin') {
    echo '<!-- DEBUG INFO -->';
    echo '<script nonce="' . htmlspecialchars($nonce) . '">';
    echo 'console.log("Total servers:", ' . count($servers) . ');';
    echo 'console.log("Servers data:", ' . json_encode($servers, JSON_PRETTY_PRINT) . ');';
    echo '</script>';
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
        <!-- Header Compacto -->
        <div class="compact-header">
            <div class="header-left">
                <h1>🏢 Gestión de Infraestructura</h1>
                <span class="stats-compact">
                    <span class="stat-badge">📦 <?= $total_servers ?></span>
                    <span class="stat-badge">⚙️ <?= $total_services ?></span>
                    <span class="stat-badge">🔑 <?= $total_credentials ?></span>
                </span>
            </div>
            <div class="header-actions">
                <form method="GET" action="" class="compact-search">
                    <input type="search" 
                           id="main-search-input" 
                           name="search" 
                           value="<?= htmlspecialchars($search) ?>"
                           placeholder="🔍 Buscar..."
                           autocomplete="off">
                </form>
                <a href="locations_manager.php" class="btn-action btn-warning">📍 Ubicaciones</a>
                <button type="button" id="addServerBtn" class="btn-action btn-primary">+ Servidor</button>
            </div>
        </div>

        <?= $status_message ?>

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

            <!-- Secciones por ubicación -->
            <div class="sections-container">
                <?php foreach ($grouped_servers as $location_id => $group): ?>
                <section class="service-section" id="location-<?= $location_id ?>">
                    <h2 class="section-title">
                        <button class="section-toggle-btn" aria-expanded="true">
                            <span class="section-title-text">📍 <?= htmlspecialchars($group['name']) ?></span>
                            <span class="section-badge"><?= count($group['servers']) ?></span>
                        </button>
                    </h2>
                    <div class="section-body">
                        <div class="servers-grid">
                            <?php foreach ($group['servers'] as $server): ?>
                            <div class="server-card collapsed" data-server-id="<?= $server['id'] ?>">
                                <div class="server-header">
                                    <div>
                                        <?php
                                            $icons = [
                                                'physical' => '🖥️', 
                                                'virtual' => '💿', 
                                                'container' => '📦', 
                                                'cloud' => '☁️', 
                                                'isp' => '🌐'
                                            ];
                                            echo $icons[$server['type']] ?? '⚙️';
                                        ?>
                                        <strong><?= htmlspecialchars($server['label']) ?></strong>
                                    </div>
                                    <div class="server-header-actions">
                                        <button type="button" 
                                                class="action-btn edit-btn" 
                                                data-server-id="<?= $server['id'] ?>" 
                                                aria-label="Editar servidor" 
                                                title="Editar Servidor">✏️</button>
                                        <button type="button" 
                                                class="action-btn delete-btn" 
                                                data-server-id="<?= $server['id'] ?>" 
                                                data-server-name="<?= htmlspecialchars($server['label']) ?>" 
                                                aria-label="Eliminar servidor" 
                                                title="Eliminar Servidor">🗑️</button>
                                        <button type="button" 
                                                class="toggle-server-btn" 
                                                aria-expanded="false" 
                                                aria-controls="server-body-<?= $server['id'] ?>" 
                                                aria-label="Expandir/Contraer servidor" 
                                                title="Ver/Ocultar Detalles">▶</button>
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

                                    <!-- Credencial Principal -->
                                    <?php if (!empty($server['username'])): ?>
                                    <div class="info-row">
                                        <div class="info-label">🔑 Credencial Principal</div>
                                        <div class="cred-row">
                                            <span>👤 <strong><?= htmlspecialchars($server['username']) ?></strong></span>
                                            <?php if (!empty($server['password'])): ?>
                                                <button type="button" 
                                                        class="copy-cred-btn" 
                                                        data-type="server_main" 
                                                        data-id="<?= $server['id'] ?>" 
                                                        title="Copiar contraseña">📋</button>
                                            <?php endif; ?>
                                        </div>
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

                                                <?php require 'templates/credentials_list.php'; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        <button type="button" 
                                                class="add-btn add-service-quick-btn" 
                                                data-server-id="<?= $server['id'] ?>">+ Agregar Servicio</button>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Notas -->
                                    <?php require 'templates/notes_section.php'; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <a href="./index2.php" class="back-btn">← Volver al Portal</a>

    <?php require_once 'templates/footer.php'; ?>

    <!-- Modal para Agregar/Editar Servidor -->
    <?php require_once 'templates/server_modal.php'; ?>

    <script src="assets/js/datacenter_view.js" nonce="<?= htmlspecialchars($nonce) ?>"></script>
</body>
</html>
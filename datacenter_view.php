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
                    
                    if (empty($server_data['id']) || strpos($server_data['id'], 'new_') === 0) {
                        // NUEVO SERVIDOR
                        $stmt = $pdo->prepare("
                            INSERT INTO dc_servers (server_id, label, type, location_id, status, hw_model, hw_cpu, hw_ram, hw_disk,
                                                    net_ip_lan, net_ip_wan, net_host_external, net_gateway, net_dns, notes, created_by)
                            VALUES (?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $server_id = 'srv_' . uniqid();
                        $stmt->execute([
                            $server_id, $server_data['label'], $server_data['type'] ?? 'physical', $server_data['location_id'] ?: null,
                            $server_data['hw_model'] ?? '', $server_data['hw_cpu'] ?? '', $server_data['hw_ram'] ?? '', $server_data['hw_disk'] ?? '',
                            $server_data['net_ip_lan'] ?? '', $server_data['net_ip_wan'] ?? '', $server_data['net_host_external'] ?? '', $server_data['net_gateway'] ?? '',
                            json_encode(array_filter(array_map('trim', explode(',', $server_data['net_dns'] ?? '')))),
                            $server_data['notes'] ?? '', $_SESSION['user_id']
                        ]);
                        $db_server_id = $pdo->lastInsertId();
                    } else {
                        // ACTUALIZAR SERVIDOR
                        $stmt = $pdo->prepare("
                            UPDATE dc_servers SET label = ?, type = ?, location_id = ?, hw_model = ?, hw_cpu = ?, hw_ram = ?, hw_disk = ?,
                                net_ip_lan = ?, net_ip_wan = ?, net_host_external = ?, net_gateway = ?, net_dns = ?, notes = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $server_data['label'], $server_data['type'] ?? 'physical', $server_data['location_id'] ?: null,
                            $server_data['hw_model'] ?? '', $server_data['hw_cpu'] ?? '', $server_data['hw_ram'] ?? '', $server_data['hw_disk'] ?? '',
                            $server_data['net_ip_lan'] ?? '', $server_data['net_ip_wan'] ?? '', $server_data['net_host_external'] ?? '', $server_data['net_gateway'] ?? '',
                            json_encode(array_filter(array_map('trim', explode(',', $server_data['net_dns'] ?? '')))),
                            $server_data['notes'] ?? '', $server_data['id']
                        ]);
                        $db_server_id = $server_data['id'];
                    }
                    
                    // Lógica para servicios y credenciales (simplificada para brevedad, pero la idea es la misma)
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
        SELECT s.*, l.name as location_name FROM dc_servers s 
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
            // No seleccionamos el hash de la contraseña por seguridad.
            $stmt_creds = $pdo->prepare("SELECT id, service_id, username, role, notes FROM dc_credentials WHERE service_id IN ($in_sql_svc) ORDER BY role DESC");
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

        <!-- Estadísticas -->
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
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>"
                       placeholder="🔍 Buscar servidor, IP, servicio..."
                       autocomplete="off" autofocus>
            </form>
            <button type="button" id="addServerBtn" class="add-btn" onclick="showServerModal()">+ Agregar Servidor</button>
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

            <div class="servers-grid">
                <?php foreach ($servers as $server): ?>
                <div class="server-card collapsed">
                    <div class="server-header">
                        <div>
                            <?= $server['type'] === 'physical' ? '🖥️' : ($server['type'] === 'virtual' ? '💿' : '📦') ?>
                            <strong><?= htmlspecialchars($server['label']) ?></strong>
                        </div>
                        <div class="server-header-actions">
                            <button type="button" class="view-toggle-btn edit-btn" data-server-id="<?= $server['id'] ?>" aria-label="Editar servidor">✏️</button>
                            <button type="button" class="view-toggle-btn delete-btn" data-server-id="<?= $server['id'] ?>" data-server-name="<?= htmlspecialchars($server['label']) ?>" aria-label="Eliminar servidor">🗑️</button>
                            <button type="button" class="view-toggle-btn" aria-expanded="false" aria-controls="server-body-<?= $server['id'] ?>">▶</button>
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

                        <!-- Servicios -->
                        <?php if (!empty($server['services'])): ?>
                        <div class="info-row">
                            <div class="info-label">⚙️ Servicios (<?= count($server['services']) ?>)</div>
                            <?php foreach ($server['services'] as $service): ?>
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

                                <?php if (!empty($service['credentials'])): ?>
                                <div class="credentials-box">
                                    <?php foreach ($service['credentials'] as $cred): ?>
                                    <div class="cred-row">
                                        <span>
                                            👤 <strong><?= htmlspecialchars($cred['username']) ?></strong>
                                            <?php if (!empty($cred['role']) && strtolower($cred['role']) !== 'user'): ?>
                                            <small class="role-badge">(<?= htmlspecialchars($cred['role']) ?>)</small>
                                            <?php endif; ?>
                                        </span>
                                        <span class="cred-pass-container">
                                            <span class="cred-pass">••••••••</span>
                                            <button type="button" class="copy-cred-btn" data-type="dc_credential" data-id="<?= $cred['id'] ?>">📋</button>
                                        </span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Notas -->
                        <?php if (!empty($server['notes'])): ?>
                        <div class="info-row">
                            <div class="info-label">📝 Notas</div>
                            <small class="server-notes"><?= nl2br(htmlspecialchars($server['notes'])) ?></small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <a href="./index2.php" class="back-btn">← Volver al Portal</a>

    <footer class="footer">
        <strong><?= htmlspecialchars($config['footer']['line1'] ?? '') ?></strong>
    </footer>

    <!-- Modal para Agregar/Editar Servidor (movido desde datacenter_manager_mysql.php) -->
    <div id="serverModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeServerModal()">&times;</span>
            <h2 id="modalTitle">Agregar Servidor</h2>
            <form method="POST" id="serverForm" action="datacenter_view.php">
                <input type="hidden" name="action" value="save_server">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="server[id]" id="serverId">
                
                <div class="form-group">
                    <label>Ubicación</label>
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
                    <label>Nombre del Servidor *</label>
                    <input type="text" name="server[label]" id="serverLabel" required>
                </div>
                
                <div class="form-group">
                    <label>Tipo</label>
                    <select name="server[type]" id="serverType">
                        <option value="physical">Físico</option>
                        <option value="virtual">Virtual</option>
                        <option value="container">Container</option>
                        <option value="cloud">Cloud</option>
                    </select>
                </div>

                <h3>💻 Hardware</h3>
                <div class="form-grid">
                    <input type="text" name="server[hw_model]" id="hwModel" placeholder="Modelo">
                    <input type="text" name="server[hw_cpu]" id="hwCpu" placeholder="CPU">
                    <input type="text" name="server[hw_ram]" id="hwRam" placeholder="RAM">
                    <input type="text" name="server[hw_disk]" id="hwDisk" placeholder="Disco">
                </div>

                <h3>🌐 Red</h3>
                <div class="form-grid">
                    <input type="text" name="server[net_ip_lan]" id="netIpLan" placeholder="IP LAN">
                    <input type="text" name="server[net_ip_wan]" id="netIpWan" placeholder="IP WAN">
                    <input type="text" name="server[net_dns]" id="netDns" placeholder="DNS (separados por coma)">
                    <input type="text" name="server[net_host_external]" id="netHostExt" placeholder="Host externo">
                    <input type="text" name="server[net_gateway]" id="netGateway" placeholder="Gateway">
                </div>

                <h3>📝 Notas</h3>
                <textarea name="server[notes]" id="serverNotes" rows="3"></textarea>

                <div class="form-actions">
                    <button type="submit" class="save-btn">💾 Guardar</button>
                    <button type="button" class="cancel-btn" onclick="closeServerModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="./assets/js/datacenter_view.js" nonce="<?= htmlspecialchars($nonce) ?>" defer></script>
</body>
</html>
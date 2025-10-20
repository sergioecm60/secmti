<?php
// datacenter_view.php - Vista de infraestructura optimizada
require_once 'bootstrap.php';

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
    try {
            validate_request_csrf();
            $pdo->beginTransaction();
            
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
                            $server_data['type'] ?? 'physical', // Corregido: El estado se toma del formulario
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
                            encrypt_password($server_data['password']),
                            $_SESSION['user_id']
                        ]);
                        $db_server_id = $pdo->lastInsertId();
                    } else {
                        // ACTUALIZAR SERVIDOR
                        $stmt = $pdo->prepare("
                            UPDATE dc_servers SET 
                                label = ?, type = ?, status = ?, location_id = ?, hw_model = ?, hw_cpu = ?, hw_ram = ?, hw_disk = ?, 
                                net_ip_lan = ?, net_ip_wan = ?, net_host_external = ?, net_gateway = ?, net_dns = ?, 
                                notes = ?, username = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $server_data['label'],
                            $server_data['type'] ?? 'physical',
                            $server_data['status'] ?? 'active', // ¡CORRECCIÓN: Añadido el valor para 'status'!
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
                            $stmt_pass->execute([encrypt_password($server_data['password']), $server_data['id']]);
                        }
                        $db_server_id = $server_data['id'];
                    }
                    
                    // --- LOGGING DE CAMBIOS ---
                    $log_action = $is_new ? 'create' : 'edit';
                    $entity_id = $is_new ? $db_server_id : $server_data['id'];
                    $details = '';

                    if (!$is_new) {
                        $stmt_before = $pdo->prepare("SELECT * FROM dc_servers WHERE id = ?");
                        $stmt_before->execute([$entity_id]);
                        $server_before = $stmt_before->fetch(PDO::FETCH_ASSOC);
                        
                        $changes = [];
                        $fields_map = ['label' => 'Etiqueta', 'type' => 'Tipo', 'status' => 'Estado', 'net_ip_lan' => 'IP LAN', 'net_ip_wan' => 'IP WAN'];

                        foreach ($fields_map as $key => $label) {
                            $old_value = $server_before[$key] ?? '';
                            $new_value = $server_data[$key] ?? '';
                            if ($old_value != $new_value) {
                                $changes[] = "{$label}: '{$old_value}' -> '{$new_value}'";
                            }
                        }
                        if (!empty($changes)) {
                            $details = "Cambios: " . implode('; ', $changes);
                        }
                    } else {
                        // Para creaciones, registrar los datos iniciales
                        $details = "Creado con Etiqueta: '{$server_data['label']}', Tipo: '{$server_data['type']}', IP LAN: '{$server_data['net_ip_lan']}'";
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
                                // CREAR: Para credenciales nuevas, la contraseña es obligatoria.
                                if (empty($cred_item['password'])) continue;
                                $stmt_cred = $pdo->prepare("
                                    INSERT INTO dc_credentials (service_id, credential_id, username, password, role, notes) 
                                    VALUES (?, ?, ?, ?, ?, ?)
                                ");
                                $stmt_cred->execute([
                                    $current_service_id,
                                    'cred_' . uniqid(),
                                    $cred_item['username'],
                                    encrypt_password($cred_item['password']),
                                    $cred_item['role'] ?? 'user',
                                    $cred_item['notes'] ?? ''
                                ]);
                                $submitted_cred_ids[] = $pdo->lastInsertId();
                            } else {
                                // ACTUALIZAR: Para credenciales existentes, la contraseña es opcional.
                                $stmt_cred = $pdo->prepare("
                                    UPDATE dc_credentials SET username=?, role=?, notes=? WHERE id=?
                                ");
                                $stmt_cred->execute([
                                    $cred_item['username'],
                                    $cred_item['role'] ?? 'user',
                                    $cred_item['notes'] ?? '',
                                    $cred_id_key
                                ]);
                                // Actualizar contraseña solo si se proporcionó una nueva.
                                if (!empty($cred_item['password'])) {
                                    $stmt_pass = $pdo->prepare("UPDATE dc_credentials SET password=? WHERE id=?");
                                    $stmt_pass->execute([encrypt_password($cred_item['password']), $cred_id_key]);
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
                    
                    // Registrar en el log DESPUÉS de que la transacción se haya completado
                    $log_stmt = $pdo->prepare("INSERT INTO dc_access_log (user_id, action, entity_type, entity_id, ip_address, details) VALUES (?, ?, ?, ?, ?, ?)");
                    $log_stmt->execute([$_SESSION['user_id'], $log_action, 'server', $entity_id, IP_ADDRESS, $details]);

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
        // Búsqueda inteligente con relevancia, adaptada de tu sugerencia.
        $search_param = "%{$search}%";
        $query = "
            SELECT s.id, s.server_id, s.label, s.type, s.location_id, s.status, s.hw_model, s.hw_cpu, s.hw_ram, 
                   s.hw_disk, s.net_ip_lan, s.net_ip_wan, s.net_host_external, s.net_gateway, s.net_dns, s.notes, 
                   s.username, s.password, l.name as location_name,
                   CASE 
                       WHEN s.label LIKE :search1 THEN 1
                       WHEN s.net_ip_lan LIKE :search2 THEN 2
                       WHEN s.net_ip_wan LIKE :search3 THEN 3
                       ELSE 4
                   END as relevance
            FROM dc_servers s 
            LEFT JOIN dc_locations l ON s.location_id = l.id
            WHERE (
                s.label LIKE :search4 
                OR s.net_ip_lan LIKE :search5 
                OR s.net_ip_wan LIKE :search6 
                OR s.net_host_external LIKE :search7 
                OR s.hw_model LIKE :search8 
                OR s.notes LIKE :search9 
                OR s.type LIKE :search10 
                OR l.name LIKE :search11
            )
            ORDER BY 
                relevance ASC, 
                CASE s.status 
                    WHEN 'active' THEN 1 
                    WHEN 'maintenance' THEN 2 
                    ELSE 3 
                END, 
                l.name, s.label ASC
        ";
        $stmt = $pdo->prepare($query);
        
        // Vincular los 11 parámetros
        for ($i = 1; $i <= 11; $i++) {
            $stmt->bindValue(":search{$i}", $search_param);
        }
        $stmt->execute();
        $servers_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // Cargar todos, ordenando por ubicación y luego por etiqueta
        $stmt = $pdo->query($base_query . " ORDER BY 
            CASE s.status 
                WHEN 'active' THEN 1 
                WHEN 'maintenance' THEN 2 
                ELSE 3 
            END, 
            l.name, 
            s.label
        ");
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
$total_locations = count($grouped_servers);

$total_services = 0;
foreach ($servers as $server) {
    $total_services += count($server['services'] ?? []);
}

$total_credentials = 0;
foreach ($servers as $server) {
    foreach ($server['services'] ?? [] as $service) {
        $total_credentials += count($service['credentials'] ?? []);
    }
}

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
                    <span class="stat-badge">📍 <?= $total_locations ?> Ubicaciones</span>
                    <span class="stat-badge">📦 <?= $total_servers ?> Servidores</span>
                    <span class="stat-badge">⚙️ <?= $total_services ?> Servicios</span>
                    <span class="stat-badge">🔓 <?= $total_credentials ?> Credenciales</span>
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

        <?= csrf_field() ?>

        <?php if (empty($servers)): ?>
            <div class="no-data">
                <h2><?= empty($search) ? 'No hay servidores configurados' : 'No se encontraron resultados' ?></h2>
                <p><?= empty($search) ? 'Comience agregando infraestructura' : 'Intente con otro término de búsqueda' ?></p>
            </div>
        <?php else: ?>
            <!-- Controles Globales de Vista -->
            <div class="global-view-controls">
                <button type="button" id="expandAllBtn" class="btn-action expand-all-btn">⬇️ Expandir Todo</button>
                <button type="button" id="collapseAllBtn" class="btn-action collapse-all-btn">⬆️ Contraer Todo</button>
            </div>

            <!-- Secciones por ubicación -->
            <?php foreach ($grouped_servers as $location_id => $group): ?>
                <div class="location-section">
                    <!-- Header de la ubicación -->
                    <div class="location-header">
                        <h2 class="location-title">
                            📍 <?= htmlspecialchars($group['name']) ?>
                            <span class="location-badge"><?= count($group['servers']) ?> servidor<?= count($group['servers']) !== 1 ? 'es' : '' ?></span>
                        </h2>
                    </div>
                    
                    <!-- Servidores de esta ubicación -->
                    <div class="servers-container">
                        <?php foreach ($group['servers'] as $server): ?>
                    <div class="server-card" data-server-id="<?= $server['id'] ?>">
                        <!-- Header -->
                        <div class="server-header">
                            <div class="server-main-info">
                                <?php
                                    $icons = ['physical' => '🖥️', 'virtual' => '🐳', 'container' => '📦', 'cloud' => '☁️', 'isp' => '🌐', 'switch' => '🔀', 'router' => '🧱', 'dvr' => '📼', 'alarmas' => '🚨'];
                                    $icon = $icons[strtolower($server['type'] ?? 'physical')] ?? '⚙️';
                                ?>
                                <div class="server-icon server-type-<?= strtolower($server['type'] ?? 'physical') ?>">
                                    <?= $icon ?>
                                </div>
                                <div class="server-title-area">
                                    <h3 class="server-name"><?= htmlspecialchars($server['label']) ?></h3>
                                    <div class="server-meta">
                                        <?php
                                            $status_map = [
                                                'active' => ['icon' => '🟢', 'text' => 'Activo'],
                                                'inactive' => ['icon' => '🔴', 'text' => 'Inactivo'],
                                                'maintenance' => ['icon' => '🟡', 'text' => 'Mantenimiento']
                                            ];
                                            $current_status = strtolower($server['status'] ?? 'inactive');
                                        ?>
                                        <span class="server-badge status-<?= $current_status ?>">
                                            <?= $status_map[$current_status]['icon'] ?? '❓' ?> <?= $status_map[$current_status]['text'] ?? 'Desconocido' ?>
                                        </span>
                                        <span class="server-badge">📦 <?= htmlspecialchars(ucfirst($server['type'])) ?></span>
                                        <?php if (!empty($server['net_ip_lan'])): ?>
                                        <span class="server-badge">🏠 <?= htmlspecialchars($server['net_ip_lan']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="server-header-actions">
                                <?php if (!empty($server['net_host_external'])): ?>
                                <?php
                                    $external_url = $server['net_host_external'];
                                    // Añadir http:// si no tiene un protocolo
                                    if (!preg_match("~^(?:f|ht)tps?://~i", $external_url)) {
                                        $external_url = "http://" . $external_url;
                                    }
                                ?>
                                <button class="server-quick-action" title="Abrir en nueva pestaña" data-action="open-external" data-url="<?= htmlspecialchars($external_url) ?>">🔗</button>
                                <?php endif; ?>
                                <button class="server-quick-action" title="Editar servidor" data-action="edit" data-server-id="<?= $server['id'] ?>">✏️</button>
                                <button class="server-quick-action" title="Eliminar servidor" data-action="delete" data-server-id="<?= $server['id'] ?>" data-server-name="<?= htmlspecialchars($server['label']) ?>">🗑️</button>
                                <button class="toggle-server-btn" title="Expandir/Colapsar">▶</button>
                            </div>
                        </div>
                        
                        <!-- Body (Colapsable) -->
                        <div class="server-body">
                            <div class="server-content">
                                <!-- Tabs -->
                                <div class="server-tabs">
                                    <button class="server-tab active" data-tab="info-<?= $server['id'] ?>">📋 Información</button>
                                    <button class="server-tab" data-tab="services-<?= $server['id'] ?>">⚙️ Servicios (<?= count($server['services'] ?? []) ?>)</button>
                                    <button class="server-tab" data-tab="network-<?= $server['id'] ?>">🌐 Red</button>
                                </div>
                                
                                <!-- Tab: Información -->
                                <div id="info-<?= $server['id'] ?>" class="server-tab-content active">
                                    <div class="info-grid">
                                        <?php if (!empty($server['hw_model'])): ?><div class="info-item"><span class="info-label">Modelo</span><span class="info-value"><?= htmlspecialchars($server['hw_model']) ?></span></div><?php endif; ?>
                                        <?php if (!empty($server['hw_cpu'])): ?><div class="info-item"><span class="info-label">CPU</span><span class="info-value"><?= htmlspecialchars($server['hw_cpu']) ?></span></div><?php endif; ?>
                                        <?php if (!empty($server['hw_ram'])): ?><div class="info-item"><span class="info-label">RAM</span><span class="info-value"><?= htmlspecialchars($server['hw_ram']) ?></span></div><?php endif; ?>
                                        <?php if (!empty($server['hw_disk'])): ?><div class="info-item"><span class="info-label">Disco</span><span class="info-value"><?= htmlspecialchars($server['hw_disk']) ?></span></div><?php endif; ?>
                                        <?php if (!empty($server['location_name'])): ?><div class="info-item"><span class="info-label">Ubicación</span><span class="info-value"><?= htmlspecialchars($server['location_name']) ?></span></div><?php endif; ?>
                                    </div>
                                    <?php if (!empty($server['username'])): ?>
                                    <div class="info-item info-item-spaced">
                                        <span class="info-label">🔑 Credencial Principal</span>
                                        <div class="cred-row cred-row-styled">
                                            <div class="cred-info">
                                                <span class="cred-username">👤 <?= htmlspecialchars($server['username']) ?></span>
                                            </div>
                                            <?php if (!empty($server['password'])): ?>
                                            <button type="button" class="copy-cred-btn" data-type="server_main" data-id="<?= $server['id'] ?>" title="Copiar contraseña">📋</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php require 'templates/notes_section.php'; ?>
                                </div>
                                
                                <!-- Tab: Servicios -->
                                <div id="services-<?= $server['id'] ?>" class="server-tab-content">
                                    <?php if (!empty($server['services'])): ?>
                                    <div class="services-list">
                                        <?php foreach ($server['services'] as $service): ?>
                                        <div class="service-item">
                                            <div class="service-header">
                                                <div class="service-title">
                                                    <div class="service-icon">⚙️</div>
                                                    <span><?= htmlspecialchars($service['name']) ?></span>
                                                </div>
                                                <div class="service-actions">
                                                    <?php if (!empty($service['url_internal'])): ?><a href="<?= htmlspecialchars($service['url_internal']) ?>" target="_blank" class="service-action-btn">🏠 LAN</a><?php endif; ?>
                                                    <?php if (!empty($service['url_external'])): ?><a href="<?= htmlspecialchars($service['url_external']) ?>" target="_blank" class="service-action-btn">🌍 WAN</a><?php endif; ?>
                                                </div>
                                            </div>
                                            <?php require 'templates/credentials_list.php'; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php else: ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon">📭</div>
                                        <div class="empty-state-text">No hay servicios configurados</div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Tab: Red -->
                                <div id="network-<?= $server['id'] ?>" class="server-tab-content">
                                    <div class="info-grid">
                                        <div class="info-item"><span class="info-label">IP LAN</span><span class="info-value"><code><?= htmlspecialchars($server['net_ip_lan'] ?: 'N/A') ?></code></span></div>
                                        <div class="info-item"><span class="info-label">IP WAN</span><span class="info-value"><code><?= htmlspecialchars($server['net_ip_wan'] ?: 'N/A') ?></code></span></div>
                                        <div class="info-item"><span class="info-label">Gateway</span><span class="info-value"><code><?= htmlspecialchars($server['net_gateway'] ?: 'N/A') ?></code></span></div>
                                        <div class="info-item"><span class="info-label">Host Externo</span><span class="info-value"><code><?= htmlspecialchars($server['net_host_external'] ?: 'N/A') ?></code></span></div>
                                        <div class="info-item"><span class="info-label">DNS</span><span class="info-value"><code><?= htmlspecialchars(is_array($server['net_dns']) ? implode(', ', $server['net_dns']) : 'N/A') ?></code></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <a href="./index2.php" class="back-btn">← Volver al Portal</a>
    
    <?php require_once 'templates/footer.php'; 

    // ========================================================================
    // DEFINICIÓN DEL MODAL DE SERVIDOR (Refactorizado a Mejora #3)
    // ========================================================================
    ob_start(); // Tab General
    ?>
    <div class="form-grid">
        <div class="form-group">
            <label for="serverLabel">Etiqueta del Servidor *</label>
            <input type="text" id="serverLabel" name="server[label]" required form="serverForm">
        </div>
        <div class="form-group">
            <label for="serverLocation">Ubicación</label>
            <select id="serverLocation" name="server[location_id]" form="serverForm">
                <option value="">-- Sin Ubicación --</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="serverType">Tipo de Servidor</label>
            <select id="serverType" name="server[type]" form="serverForm">
                <option value="physical">Físico</option>
                <option value="virtual">Virtual</option>
                <option value="container">Contenedor</option>
                <option value="cloud">Cloud</option>
                <option value="isp">ISP</option>
                <option value="Switch">Switch</option>
                <option value="Router">Router</option>
                <option value="Dvr">DVR</option>
                <option value="Alarmas">Alarmas</option>
            </select>
        </div>
        <div class="form-group">
            <label for="serverStatus">Estado</label>
            <select id="serverStatus" name="server[status]" form="serverForm">
                <option value="active">Activo</option>
                <option value="inactive">Inactivo</option>
                <option value="maintenance">Mantenimiento</option>
            </select>
        </div>
    </div>
    <fieldset>
        <legend>Credencial Principal (Opcional)</legend>
        <div class="form-grid">
            <div class="form-group">
                <label for="serverUsername">Usuario</label>
                <input type="text" id="serverUsername" name="server[username]" autocomplete="off" form="serverForm">
            </div>
            <div class="form-group">
                <label for="serverPassword">Contraseña</label>
                <input type="password" id="serverPassword" name="server[password]" placeholder="Dejar en blanco para no cambiar" autocomplete="new-password" form="serverForm">
            </div>
        </div>
    </fieldset>
    <?php $tab_general = ob_get_clean();

    ob_start(); // Tab Hardware
    ?>
    <div class="form-grid"><div class="form-group"><label for="hwModel">Modelo</label><input type="text" id="hwModel" name="server[hw_model]" form="serverForm"></div><div class="form-group"><label for="hwCpu">CPU</label><input type="text" id="hwCpu" name="server[hw_cpu]" form="serverForm"></div><div class="form-group"><label for="hwRam">RAM</label><input type="text" id="hwRam" name="server[hw_ram]" form="serverForm"></div><div class="form-group"><label for="hwDisk">Disco</label><input type="text" id="hwDisk" name="server[hw_disk]" form="serverForm"></div></div>
    <?php $tab_hardware = ob_get_clean();

    ob_start(); // Tab Red
    ?>
    <div class="form-grid"><div class="form-group"><label for="netIpLan">IP LAN</label><input type="text" id="netIpLan" name="server[net_ip_lan]" form="serverForm"></div><div class="form-group"><label for="netIpWan">IP WAN</label><input type="text" id="netIpWan" name="server[net_ip_wan]" form="serverForm"></div></div><div class="form-grid"><div class="form-group"><label for="netHostExt">Host Externo</label><input type="text" id="netHostExt" name="server[net_host_external]" form="serverForm"></div><div class="form-group"><label for="netGateway">Gateway</label><input type="text" id="netGateway" name="server[net_gateway]" form="serverForm"></div></div><div class="form-group"><label for="netDns">Servidores DNS (separados por coma)</label><input type="text" id="netDns" name="server[net_dns]" form="serverForm"></div>
    <?php $tab_network = ob_get_clean();

    ob_start(); // Tab Servicios
    ?>
    <h3>⚙️ Servicios del Servidor</h3><div id="servicesContainer"></div><button type="button" id="addServiceModalBtn" class="add-btn">+ Agregar Servicio</button>
    <?php $tab_services = ob_get_clean();

    ob_start(); // Tab Notas
    ?>
    <div class="form-group"><label for="serverNotes">Notas Adicionales</label><textarea id="serverNotes" name="server[notes]" rows="6" form="serverForm"></textarea></div>
    <?php $tab_notes = ob_get_clean();

    ?>
    <form id="serverForm" method="POST" action="datacenter_view.php">
        <input type="hidden" name="action" value="save_server">
        <?= csrf_field() ?>
        <input type="hidden" name="server[id]" id="serverId">
        <?php
        echo render_modal([
            'id' => 'serverModal',
            'title' => 'Gestionar Servidor',
            'size' => 'large',
            'tabs' => [
                ['id' => 'tab-general', 'label' => 'General', 'content' => $tab_general],
                ['id' => 'tab-hardware', 'label' => 'Hardware', 'content' => $tab_hardware],
                ['id' => 'tab-network', 'label' => 'Red', 'content' => $tab_network],
                ['id' => 'tab-services', 'label' => 'Servicios', 'content' => $tab_services],
                ['id' => 'tab-notes', 'label' => 'Notas', 'content' => $tab_notes],
            ],
            'form_id' => 'serverForm',
            'submit_text' => '💾 Guardar'
        ]);
        ?>
    </form>

    <script src="assets/js/modal-system.js" nonce="<?= htmlspecialchars($nonce) ?>"></script>
    <script src="assets/js/datacenter_view.js" nonce="<?= htmlspecialchars($nonce) ?>"></script>
</body>
</html>
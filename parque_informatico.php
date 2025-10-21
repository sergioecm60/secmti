<?php
/**
 * Parque Informático - Vista de Gestión de PCs
 * ============================================
 * Sistema de visualización y gestión del parque informático de la empresa
 * Organizado por ubicaciones físicas
 */

require_once 'bootstrap.php';
require_once 'include/permissions_helper.php'; // <-- CORREGIDO

// Generar nonce para CSP
$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");

// Verificar autenticación
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = get_database_connection($config, true);

// NUEVO: Obtener permisos
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$allowed_locations = get_user_allowed_locations($pdo, $user_id, $user_role);
$is_readonly = is_regular_user();

$status_message = '';

// --- MANEJO DE ACCIONES POST (GUARDAR, ELIMINAR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($is_readonly) {
        throw new Exception("No tienes permisos para realizar esta acción.");
    }
    try {
        validate_request_csrf();
        $pdo->beginTransaction();
        $action = $_POST['action'] ?? '';

        if ($action === 'save_pc') {
            $pc_data = $_POST['pc'];
            $is_new = empty($pc_data['id']) || strpos($pc_data['id'], 'new_') === 0;
            $details = '';

            if (!$is_new) {
                // Para ediciones, generar detalles de cambios
                $stmt_before = $pdo->prepare("SELECT * FROM pc_equipment WHERE id = ?");
                $stmt_before->execute([$pc_data['id']]);
                $pc_before = $stmt_before->fetch(PDO::FETCH_ASSOC);

                $changes = [];
                $fields_map = [
                    'asset_tag' => 'Nombre-Tipo', 'assigned_to' => 'Asignado a', 'pc_model' => 'Modelo',
                    'department' => 'Sector', 'status' => 'Estado', 'os' => 'SO', 'office_suite' => 'Office'
                ];

                foreach ($fields_map as $key => $label) {
                    $old_value = $pc_before[$key] ?? '';
                    $new_value = $pc_data[$key] ?? '';
                    if ($old_value != $new_value) {
                        $changes[] = "{$label}: '{$old_value}' -> '{$new_value}'";
                    }
                }
                if (!empty($changes)) {
                    $details = "Cambios: " . implode('; ', $changes);
                }
            }

            $sql = $is_new
                ? "INSERT INTO pc_equipment (asset_tag, location_id, assigned_to, pc_model, department, status, os, office_suite, phone, printer, notes) VALUES (:asset_tag, :location_id, :assigned_to, :pc_model, :department, :status, :os, :office_suite, :phone, :printer, :notes)"
                : "UPDATE pc_equipment SET asset_tag=:asset_tag, location_id=:location_id, assigned_to=:assigned_to, pc_model=:pc_model, department=:department, status=:status, os=:os, office_suite=:office_suite, phone=:phone, printer=:printer, notes=:notes WHERE id=:id";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':asset_tag', $pc_data['asset_tag'] ?: null);
            $stmt->bindValue(':location_id', $pc_data['location_id'] ?: null, PDO::PARAM_INT);
            $stmt->bindValue(':assigned_to', $pc_data['assigned_to'] ?: null);
            $stmt->bindValue(':pc_model', $pc_data['pc_model'] ?? '');
            $stmt->bindValue(':department', $pc_data['department'] ?? '');
            $stmt->bindValue(':status', $pc_data['status'] ?? 'Usada');
            $stmt->bindValue(':os', $pc_data['os'] ?? '');
            $stmt->bindValue(':office_suite', $pc_data['office_suite'] ?? '');
            $stmt->bindValue(':phone', $pc_data['phone'] ?? '');
            $stmt->bindValue(':printer', $pc_data['printer'] ?? '');
            $stmt->bindValue(':notes', $pc_data['notes'] ?? '');
            if (!$is_new) {
                $stmt->bindValue(':id', $pc_data['id'], PDO::PARAM_INT);
            }
            $stmt->execute();
            $status_message = '<div class="status-message success">✅ PC guardada correctamente.</div>';

            // Logging
            $log_action = $is_new ? 'create' : 'edit';
            $entity_id = $is_new ? $pdo->lastInsertId() : $pc_data['id'];
            $log_stmt = $pdo->prepare("INSERT INTO dc_access_log (user_id, action, entity_type, entity_id, ip_address, details) VALUES (?, ?, ?, ?, ?, ?)");
            $log_stmt->execute([$user_id, $log_action, 'pc_equipment', $entity_id, IP_ADDRESS, $details]);

        } elseif ($action === 'delete_pc') {
            $pc_id = $_POST['pc_id'] ?? 0;
            $details = '';

            // Obtener datos antes de borrar para el log
            $stmt_before = $pdo->prepare("SELECT assigned_to, pc_model FROM pc_equipment WHERE id = ?");
            $stmt_before->execute([$pc_id]);
            $pc_before = $stmt_before->fetch(PDO::FETCH_ASSOC);
            if ($pc_before) {
                $details = "PC eliminada: " . ($pc_before['assigned_to'] ?: 'Equipo Libre') . " (" . $pc_before['pc_model'] . ")";
            }

            $stmt = $pdo->prepare("DELETE FROM pc_equipment WHERE id = ?");
            $stmt->execute([$pc_id]);
            $status_message = '<div class="status-message success">✅ PC eliminada correctamente.</div>';

            // Logging
            if ($pc_id > 0) {
                $log_stmt = $pdo->prepare("INSERT INTO dc_access_log (user_id, action, entity_type, entity_id, ip_address, details) VALUES (?, ?, ?, ?, ?, ?)");
                $log_stmt->execute([$user_id, 'delete', 'pc_equipment', $pc_id, IP_ADDRESS, $details]);
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $status_message = '<div class="status-message error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// --- Parámetros de búsqueda y filtros ---
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_location = $_GET['location'] ?? '';

// --- Lógica para cargar datos ---
$locations = [];
$pcs = [];
$grouped_pcs = [];

try {
    // 1. Obtener todas las ubicaciones (para filtros y modales)
    $stmt_locations = $pdo->query("SELECT id, name FROM dc_locations ORDER BY name ASC");
    $locations = $stmt_locations->fetchAll(PDO::FETCH_ASSOC);

    // 2. Construir consulta con filtros
    $query = "
        SELECT p.*, l.name as location_name 
        FROM pc_equipment p
        LEFT JOIN dc_locations l ON p.location_id = l.id
        WHERE 1=1
    ";
    $params = [];
    
    // NUEVO: Aplicar filtro de locación por permisos
    if ($allowed_locations !== null) {
        if (empty($allowed_locations)) {
            $pcs = []; // No mostrar nada si no tiene locaciones
        } else {
            $filter = get_location_filter_sql($allowed_locations, 'p.location_id');
            $query .= $filter['sql'];
            $params = array_merge($params, $filter['params']);
        }
    }

    // Aplicar filtro de búsqueda
    if (!empty($search)) {
        $query .= " AND (
            p.assigned_to LIKE :search 
            OR p.pc_model LIKE :search 
            OR p.department LIKE :search
            OR p.os LIKE :search
            OR p.office_suite LIKE :search
        )";
        $params[':search'] = '%' . $search . '%';
    }
    
    // Aplicar filtro de estado
    if (!empty($filter_status)) {
        $query .= " AND p.status = :status";
        $params[':status'] = $filter_status;
    }
    
    // Aplicar filtro de ubicación
    if (!empty($filter_location)) {
        $query .= " AND p.location_id = :location_id";
        $params[':location_id'] = $filter_location;
    }
    
    $query .= " ORDER BY l.name, p.department, p.assigned_to";
    
    // Solo ejecutar si no hemos vaciado ya el array de PCs
    if (empty($pcs)) {
        $stmt_pcs = $pdo->prepare($query);
        $stmt_pcs->execute($params);
        $pcs = $stmt_pcs->fetchAll(PDO::FETCH_ASSOC);
    }
    // 3. Agrupar las PCs por ubicación
    foreach ($pcs as $pc) {
        $location_id = $pc['location_id'] ?? 0;
        if (!isset($grouped_pcs[$location_id])) {
            $grouped_pcs[$location_id] = [
                'id' => $location_id,
                'name' => $pc['location_name'] ?? 'Sin Ubicación Asignada',
                'pcs' => []
            ];
        }
        $grouped_pcs[$location_id]['pcs'][] = $pc;
    }

} catch (Exception $e) {
    error_log('Error loading PC data: ' . $e->getMessage());
    $status_message = '<div class="status-message error">❌ Error al cargar los datos de las PCs.</div>';
}

// --- Calcular estadísticas reales ---
$total_pcs = count($pcs);
$total_locations_with_pcs = count($grouped_pcs);

// Estadísticas por estado
$stats_by_status = [
    'Nueva' => 0,
    'Usada' => 0,
    'Reacondicionada' => 0,
    'Libre' => 0
];

foreach ($pcs as $pc) {
    if (isset($stats_by_status[$pc['status']])) {
        $stats_by_status[$pc['status']]++;
    }
    if (empty($pc['assigned_to'])) {
        $stats_by_status['Libre']++;
    }
}

// Mapeo de estados a clases CSS
$status_classes = [
    'Nueva' => 'status-active',
    'Usada' => 'status-maintenance',
    'Reacondicionada' => 'status-info',
    'En Depósito' => 'status-inactive',
    'De Baja' => 'status-inactive',
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💻 Parque Informático - SECMTI</title>
    <link rel="stylesheet" href="./assets/css/main.css">
    <link rel="stylesheet" href="./assets/css/datacenter.css"> <!-- Reutilizamos estilos -->
    <style nonce="<?= htmlspecialchars($nonce) ?>">
        /* Estilos específicos para Parque Informático */
        .stats-compact {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .stat-badge {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            white-space: nowrap;
        }
        
        .filters-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .filter-group select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
        }
        
        .status-info {
            background-color: #17a2b8;
            color: white;
        }
        
        .pc-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .pc-detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .pc-detail-label {
            font-weight: 600;
            font-size: 0.75rem;
            color: #666;
            text-transform: uppercase;
        }
        
        .pc-detail-value {
            font-size: 0.875rem;
            color: #333;
        }
        
        .location-section {
            margin-bottom: 2rem;
        }
        
        .location-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px 8px 0 0;
            margin-bottom: 1rem;
        }
        
        .location-title {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.25rem;
        }
        
        .location-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: normal;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .pc-detail-item-full-width {
            grid-column: 1 / -1;
        }
        .readonly-badge-align-center {
            align-self: center;
        }
    </style>
</head>
<body class="page-manage">
    <div class="admin-container admin-container-full-width">
        <!-- Header Compacto -->
        <div class="compact-header">
            <div class="header-left">
                <h1>💻 Parque Informático</h1>
                <span class="stats-compact">
                    <span class="stat-badge">📍 <?= $total_locations_with_pcs ?> Ubicaciones</span>
                    <span class="stat-badge">💻 <?= $total_pcs ?> PCs</span>
                    <span class="stat-badge">🆕 <?= $stats_by_status['Nueva'] ?> Nuevas</span>
                    <span class="stat-badge">♻️ <?= $stats_by_status['Reacondicionada'] ?> Reacond.</span>
                    <span class="stat-badge">📦 <?= $stats_by_status['Usada'] ?> Usadas</span>
                    <span class="stat-badge">🔓 <?= $stats_by_status['Libre'] ?> Libres</span>
                </span>
            </div>
            <div class="header-actions">
                <form method="GET" action="" class="compact-search">
                    <input type="search" 
                           id="main-search-input" 
                           name="search" 
                           value="<?= htmlspecialchars($search) ?>"
                           placeholder="🔍 Buscar por usuario, equipo, sector..."
                           autocomplete="off">
                </form>
                <?php if (!$is_readonly): ?>
                    <button type="button" id="addPcBtn" class="btn-action btn-primary">+ Agregar PC</button>
                <?php else: ?>
                    <span class="readonly-badge readonly-badge-align-center">👁️ Solo Lectura</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filtros -->
        <?php if (!empty($locations)): ?>
        <div class="filters-row">
            <form method="GET" action="">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                
                <div class="filter-group">
                    <label for="filter-status">Estado:</label>
                    <select name="status" id="filter-status">
                        <option value="">Todos</option>
                        <option value="Nueva" <?= $filter_status === 'Nueva' ? 'selected' : '' ?>>Nuevas</option>
                        <option value="Usada" <?= $filter_status === 'Usada' ? 'selected' : '' ?>>Usadas</option>
                        <option value="Reacondicionada" <?= $filter_status === 'Reacondicionada' ? 'selected' : '' ?>>Reacondicionadas</option>
                        <option value="En Depósito" <?= $filter_status === 'En Depósito' ? 'selected' : '' ?>>En Depósito</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="filter-location">Ubicación:</label>
                    <select name="location" id="filter-location">
                        <option value="">Todas</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= $loc['id'] ?>" <?= $filter_location == $loc['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($loc['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if (!empty($filter_status) || !empty($filter_location)): ?>
                    <a href="?search=<?= urlencode($search) ?>" class="btn-action btn-secondary">Limpiar Filtros</a>
                <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>

        <?= $status_message ?>
        <?= csrf_field() ?>

        <?php if (empty($grouped_pcs) && $is_readonly): ?>
             <div class="empty-state">
                <div class="empty-state-icon">⚠️</div>
                <h2>Sin Acceso a Locaciones</h2>
                <p>No tienes locaciones asignadas. Contacta a un administrador para obtener acceso.</p>
            </div>
        <?php elseif (empty($grouped_pcs)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><?= (!empty($search) || !empty($filter_status) || !empty($filter_location)) ? '🧐' : '💻' ?></div>
                <h2>No hay PCs registradas</h2>
                <p>Comience agregando un equipo con el botón "+ Agregar PC" o importando desde un archivo.</p>
                <?php if (!empty($search) || !empty($filter_status) || !empty($filter_location)): ?>
                    <p><a href="?" class="btn-action btn-primary">Ver todas las PCs</a></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Controles Globales de Vista -->
            <div class="global-view-controls">
                <button type="button" id="expandAllBtn" class="btn-action expand-all-btn">⬇️ Expandir Todo</button>
                <button type="button" id="collapseAllBtn" class="btn-action collapse-all-btn">⬆️ Contraer Todo</button>
            </div>

            <!-- Secciones por ubicación -->
            <?php foreach ($grouped_pcs as $location_id => $group): ?>
                <div class="location-section" data-location-id="<?= $location_id ?>">
                    <div class="location-header">
                        <h2 class="location-title">
                            📍 <?= htmlspecialchars($group['name']) ?>
                            <span class="location-badge"><?= count($group['pcs']) ?> PC<?= count($group['pcs']) !== 1 ? 's' : '' ?></span>
                        </h2>
                    </div>
                    
                    <!-- Contenedor de PCs para esta ubicación -->
                    <div class="servers-container">
                        <?php foreach ($group['pcs'] as $pc): ?>
                        <div class="server-card" data-pc-id="<?= $pc['id'] ?>">
                            <div class="server-header">
                                <div class="server-main-info">
                                    <div class="server-icon server-type-physical">💻</div>
                                    <div class="server-title-area">
                                        <h3 class="server-name">
                                            <?= htmlspecialchars($pc['assigned_to'] ?: '🔓 Equipo Libre') ?>
                                        </h3>
                                        <div class="server-meta">
                                            <span class="server-badge">📁 <?= htmlspecialchars($pc['department']) ?></span>
                                            <?php
                                                $status_class = $status_classes[$pc['status']] ?? 'status-inactive';
                                            ?>
                                            <span class="server-badge <?= $status_class ?>">
                                                <?= htmlspecialchars($pc['status']) ?>
                                            </span>
                                            <?php if (!empty($pc['asset_tag'])): ?>
                                                <span class="server-badge">🏷️ <?= htmlspecialchars($pc['asset_tag']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="server-header-actions">
                                    <?php if (!$is_readonly): ?>
                                        <button class="server-quick-action" title="Editar PC" data-action="edit" data-pc-id="<?= $pc['id'] ?>">✏️</button>
                                        <button class="server-quick-action" title="Eliminar PC" data-action="delete" data-pc-id="<?= $pc['id'] ?>" data-pc-name="<?= htmlspecialchars($pc['assigned_to'] ?: 'Equipo Libre') ?>">🗑️</button>
                                    <?php endif; ?>
                                    <button class="toggle-server-btn" title="Ver detalles">▶</button>
                                </div>
                            </div>
                            
                            <!-- Cuerpo expandible con detalles -->
                            <div class="server-body">
                                <div class="pc-details">
                                    <!-- Modelo del Equipo -->
                                    <div class="pc-detail-item">
                                        <span class="pc-detail-label">💻 Modelo</span>
                                        <span class="pc-detail-value"><?= htmlspecialchars($pc['pc_model']) ?></span>
                                    </div>
                                    
                                    <!-- Sistema Operativo -->
                                    <?php if (!empty($pc['os'])): ?>
                                    <div class="pc-detail-item">
                                        <span class="pc-detail-label">🖥️ Sistema Operativo</span>
                                        <span class="pc-detail-value"><?= htmlspecialchars($pc['os']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Suite Office -->
                                    <?php if (!empty($pc['office_suite'])): ?>
                                    <div class="pc-detail-item">
                                        <span class="pc-detail-label">📊 Office</span>
                                        <span class="pc-detail-value"><?= htmlspecialchars($pc['office_suite']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Teléfono -->
                                    <?php if (!empty($pc['phone'])): ?>
                                    <div class="pc-detail-item">
                                        <span class="pc-detail-label">☎️ Teléfono</span>
                                        <span class="pc-detail-value"><?= htmlspecialchars($pc['phone']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Impresora -->
                                    <?php if (!empty($pc['printer'])): ?>
                                    <div class="pc-detail-item">
                                        <span class="pc-detail-label">🖨️ Impresora</span>
                                        <span class="pc-detail-value"><?= htmlspecialchars($pc['printer']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Notas -->
                                    <?php if (!empty($pc['notes'])): ?>
                                    <div class="pc-detail-item pc-detail-item-full-width">
                                        <span class="pc-detail-label">📝 Notas</span>
                                        <span class="pc-detail-value"><?= htmlspecialchars($pc['notes']) ?></span>
                                    </div>
                                    <?php endif; ?>
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
    
    <?php
    // --- MODAL PARA AGREGAR/EDITAR PC ---
    ob_start();
    ?>
    <div class="form-grid">
        <div class="form-group">
            <label for="pcAssetTag">Nombre - Tipo</label>
            <input type="text" id="pcAssetTag" name="pc[asset_tag]" form="pcForm">
        </div>
        <div class="form-group">
            <label for="pcAssignedTo">Asignado a</label>
            <input type="text" id="pcAssignedTo" name="pc[assigned_to]" placeholder="Dejar vacío si está libre" form="pcForm">
        </div>
    </div>
    <div class="form-group">
        <label for="pcModel">Modelo del Equipo *</label>
        <input type="text" id="pcModel" name="pc[pc_model]" required form="pcForm">
    </div>
    <div class="form-grid">
        <div class="form-group">
            <label for="pcDepartment">Sector / Departamento</label>
            <input type="text" id="pcDepartment" name="pc[department]" form="pcForm">
        </div>
        <div class="form-group">
            <label for="pcLocation">Ubicación</label>
            <select id="pcLocation" name="pc[location_id]" form="pcForm">
                <option value="">-- Sin Ubicación --</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="pcStatus">Estado</label>
            <select id="pcStatus" name="pc[status]" form="pcForm">
                <option value="Nueva">Nueva</option>
                <option value="Usada">Usada</option>
                <option value="Reacondicionada">Reacondicionada</option>
                <option value="En Depósito">En Depósito</option>
                <option value="De Baja">De Baja</option>
            </select>
        </div>
    </div>
    <fieldset>
        <legend>Software y Periféricos</legend>
        <div class="form-grid">
            <div class="form-group"><label for="pcOs">Sistema Operativo</label><input type="text" id="pcOs" name="pc[os]" form="pcForm"></div>
            <div class="form-group"><label for="pcOffice">Suite Ofimática</label><input type="text" id="pcOffice" name="pc[office_suite]" form="pcForm"></div>
            <div class="form-group"><label for="pcPhone">Teléfono</label><input type="text" id="pcPhone" name="pc[phone]" form="pcForm"></div>
            <div class="form-group"><label for="pcPrinter">Impresora</label><input type="text" id="pcPrinter" name="pc[printer]" form="pcForm"></div>
        </div>
    </fieldset>
    <div class="form-group">
        <label for="pcNotes">Notas</label>
        <textarea id="pcNotes" name="pc[notes]" rows="3" form="pcForm"></textarea>
    </div>
    <?php
    $pc_form_content = ob_get_clean();
    ?>
    <form id="pcForm" method="POST" action="parque_informatico.php" style="<?= $is_readonly ? 'display:none;' : '' ?>">
        <input type="hidden" name="action" value="save_pc">
        <?= csrf_field() ?>
        <?php
        echo render_modal([
            'id' => 'pcModal',
            'title' => 'Gestionar PC',
            'size' => 'large',
            'content' => $pc_form_content,
            'form_id' => 'pcForm',
            'submit_text' => 'Guardar PC'
        ]);
        ?>
    </form>
    
    <!-- Script para funcionalidad de expandir/colapsar -->
    <script src="assets/js/modal-system.js" nonce="<?= htmlspecialchars($nonce) ?>"></script>
    <script nonce="<?= htmlspecialchars($nonce) ?>">
    document.addEventListener('DOMContentLoaded', function() {
        console.log('💻 Parque Informático cargado - Total PCs:', <?= $total_pcs ?>);
        const IS_READONLY = <?= json_encode($is_readonly) ?>;
        const allPcsData = <?= json_encode($pcs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
        
        // Lógica para expandir/colapsar tarjetas
        const container = document.querySelector('.admin-container');
        
        if (container) {
            container.addEventListener('click', function(e) {
                const toggleBtn = e.target.closest('.toggle-server-btn');
                const editBtn = e.target.closest('[data-action="edit"]');
                const deleteBtn = e.target.closest('[data-action="delete"]');
                
                if (editBtn) {
                    if (IS_READONLY) return;
                    const pcId = editBtn.getAttribute('data-pc-id');
                    const pcData = allPcsData.find(p => p.id == pcId);
                    openPcModal(pcData);
                    return;
                }

                if (deleteBtn) {
                    if (IS_READONLY) return;
                    const pcId = deleteBtn.getAttribute('data-pc-id');
                    const pcName = deleteBtn.getAttribute('data-pc-name');
                    if (confirm(`¿Estás seguro de que quieres eliminar la PC asignada a "${pcName}"?`)) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'parque_informatico.php';

                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'action';
                        actionInput.value = 'delete_pc';
                        form.appendChild(actionInput);

                        const idInput = document.createElement('input');
                        idInput.type = 'hidden';
                        idInput.name = 'pc_id';
                        idInput.value = pcId;
                        form.appendChild(idInput);

                        form.appendChild(document.querySelector('input[name="csrf_token"]').cloneNode());
                        document.body.appendChild(form);
                        form.submit();
                    }
                    return;
                }
                
                if (!toggleBtn) return;
                
                const header = toggleBtn.closest('.server-header');
                if (!header) return;
                
                const card = header.closest('.server-card');
                const body = card.querySelector('.server-body');
                const isExpanded = card.classList.contains('expanded');
                
                // Toggle expansion
                card.classList.toggle('expanded', !isExpanded);
                toggleBtn.textContent = isExpanded ? '▶' : '▼';
                
                // Animar altura
                if (!isExpanded) {
                    body.style.maxHeight = body.scrollHeight + 'px';
                } else {
                    body.style.maxHeight = null;
                }
            });
        }
        
        // --- Lógica para Expandir/Contraer Todo ---
        const expandAllBtn = document.getElementById('expandAllBtn');
        const collapseAllBtn = document.getElementById('collapseAllBtn');

        function togglePcCard(card, expand) {
            const body = card.querySelector('.server-body');
            const toggleBtn = card.querySelector('.toggle-server-btn');
            const isExpanded = card.classList.contains('expanded');

            if (expand && !isExpanded) {
                card.classList.add('expanded');
                toggleBtn.textContent = '▼';
                body.style.maxHeight = body.scrollHeight + 'px';
            } else if (!expand && isExpanded) {
                card.classList.remove('expanded');
                toggleBtn.textContent = '▶';
                body.style.maxHeight = null;
            }
        }

        if (expandAllBtn) {
            expandAllBtn.addEventListener('click', () => {
                document.querySelectorAll('.server-card').forEach(card => {
                    togglePcCard(card, true);
                });
            });
        }

        if (collapseAllBtn) {
            collapseAllBtn.addEventListener('click', () => {
                document.querySelectorAll('.server-card').forEach(card => {
                    togglePcCard(card, false);
                });
            });
        }

        // Búsqueda en tiempo real (opcional)
        const searchInput = document.getElementById('main-search-input');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    // Auto-submit después de 500ms de inactividad
                    if (searchInput.value.length >= 3 || searchInput.value.length === 0) {
                        searchInput.form.submit();
                    }
                }, 500);
            });
        }
        
        // --- Lógica para los filtros ---
        const statusFilter = document.getElementById('filter-status');
        const locationFilter = document.getElementById('filter-location');

        if (statusFilter) {
            statusFilter.addEventListener('change', function() {
                this.form.submit();
            });
        }
        if (locationFilter) {
            locationFilter.addEventListener('change', function() {
                this.form.submit();
            });
        }

        // Botón Agregar PC
        const addPcBtn = document.getElementById('addPcBtn');
        if (addPcBtn) {
            addPcBtn.addEventListener('click', () => openPcModal());
        }

        function openPcModal(pcData = null) {
            const form = document.getElementById('pcForm');
            form.reset();

            // Limpiar o añadir el input de ID
            let idInput = form.querySelector('input[name="pc[id]"]');
            if (!idInput) {
                idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'pc[id]';
                form.appendChild(idInput);
            }

            const modalTitle = 'Gestionar PC';
            if (pcData) {
                idInput.value = pcData.id;
                document.getElementById('pcAssetTag').value = pcData.asset_tag || '';
                document.getElementById('pcAssignedTo').value = pcData.assigned_to || '';
                document.getElementById('pcModel').value = pcData.pc_model || '';
                document.getElementById('pcDepartment').value = pcData.department || '';
                document.getElementById('pcLocation').value = pcData.location_id || '';
                document.getElementById('pcStatus').value = pcData.status || 'Usada';
                document.getElementById('pcOs').value = pcData.os || '';
                document.getElementById('pcOffice').value = pcData.office_suite || '';
                document.getElementById('pcPhone').value = pcData.phone || '';
                document.getElementById('pcPrinter').value = pcData.printer || '';
                document.getElementById('pcNotes').value = pcData.notes || '';
            } else {
                idInput.value = 'new_' + Date.now(); // Para identificarlo como nuevo en el backend
            }
            document.querySelector('#pcModal .modal-title').textContent = pcData ? 'Editar PC' : 'Agregar Nueva PC';
            modalManager.open('pcModal');
        }
    });
    </script>
</body>
</html>
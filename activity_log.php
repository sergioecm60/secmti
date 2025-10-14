<?php
/**
 * activity_log.php - Página para visualizar el log de actividad del portal.
 *
 * Muestra un registro filtrable y paginado de las acciones realizadas,
 * permitiendo a los administradores monitorear y auditar el sistema.
 */

require_once 'bootstrap.php';

// Verificar autenticación y rol de administrador
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = get_database_connection($config, true);

// --- Parámetros de filtrado y paginación ---
$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$limit = 50;
$offset = ($page - 1) * $limit;

// Filtros opcionales
$filter_user = filter_input(INPUT_GET, 'user', FILTER_UNSAFE_RAW);
$filter_action = filter_input(INPUT_GET, 'action', FILTER_UNSAFE_RAW);
$filter_entity = filter_input(INPUT_GET, 'entity', FILTER_UNSAFE_RAW);
$filter_date_from = filter_input(INPUT_GET, 'date_from', FILTER_UNSAFE_RAW);
$filter_date_to = filter_input(INPUT_GET, 'date_to', FILTER_UNSAFE_RAW);

// --- Construcción de consulta con filtros ---
$where_conditions = [];
$params = [];

if ($filter_user) {
    $where_conditions[] = "u.username LIKE :username";
    $params[':username'] = "%{$filter_user}%";
}
if ($filter_action) {
    $where_conditions[] = "l.action = :action";
    $params[':action'] = $filter_action;
}
if ($filter_entity) {
    $where_conditions[] = "l.entity_type = :entity_type";
    $params[':entity_type'] = $filter_entity;
}
if ($filter_date_from) {
    $where_conditions[] = "l.created_at >= :date_from";
    $params[':date_from'] = $filter_date_from . ' 00:00:00';
}
if ($filter_date_to) {
    $where_conditions[] = "l.created_at <= :date_to";
    $params[':date_to'] = $filter_date_to . ' 23:59:59';
}

$where_sql = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// --- Obtener logs ---
$logs = [];
$total_logs = 0;
try {
    // Contar total de registros
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM dc_access_log l
        LEFT JOIN users u ON l.user_id = u.id
        {$where_sql}
    ");
    $count_stmt->execute($params);
    $total_logs = $count_stmt->fetchColumn();
    
    // Obtener logs paginados
    $stmt = $pdo->prepare("
        SELECT l.id, l.action, l.entity_type, l.entity_id, l.ip_address, 
               l.created_at, l.user_agent, u.username
        FROM dc_access_log l
        LEFT JOIN users u ON l.user_id = u.id
        {$where_sql}
        ORDER BY l.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener listas únicas para filtros
    $actions = $pdo->query("SELECT DISTINCT action FROM dc_access_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
    $entities = $pdo->query("SELECT DISTINCT entity_type FROM dc_access_log ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    $status_message = '<div class="status-message error">Error al cargar los logs de actividad.</div>';
    error_log('Activity Log Page Error: ' . $e->getMessage());
}

$total_pages = ceil($total_logs / $limit);

// --- Exportar a CSV ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity_log_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Fecha', 'Usuario', 'Acción', 'Entidad', 'ID Entidad', 'IP', 'User Agent']);
    
    // Para la exportación, obtenemos todos los logs que coinciden con el filtro, sin paginación
    try {
        $export_stmt = $pdo->prepare("
            SELECT l.created_at, u.username, l.action, l.entity_type, l.entity_id, l.ip_address, l.user_agent
            FROM dc_access_log l
            LEFT JOIN users u ON l.user_id = u.id
            {$where_sql}
            ORDER BY l.created_at DESC
        ");
        $export_stmt->execute($params);
        while ($log = $export_stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $log['created_at'],
                $log['username'] ?? 'Sistema',
                $log['action'],
                $log['entity_type'],
                $log['entity_id'],
                $log['ip_address'] ?? 'N/A',
                $log['user_agent'] ?? 'N/A'
            ]);
        }
    } catch (Exception $e) {
        error_log('CSV Export Error: ' . $e->getMessage());
    }
    
    fclose($output);
    exit;
}

/**
 * Sanitiza un valor para usar en atributos HTML de forma segura
 */
function safe_attr($value) {
    return preg_replace('/[^a-z0-9_-]/i', '', $value);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log de Actividad</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        .filters-form {
            background: rgba(0,0,0,0.03);
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        .filter-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 5px;
        }
        .filter-group input, .filter-group select {
            width: 100%;
            padding: 8px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            text-decoration: none;
            color: var(--primary-color);
            background-color: var(--container-background);
        }
        .pagination .current {
            background: var(--primary-color);
            color: var(--primary-text-color);
            border-color: var(--primary-color);
        }
        .action-badge {
            padding: 3px 8px;
            border-radius: 5px;
            font-weight: 500;
            color: white;
            cursor: help;
        }
        .action-view { background-color: #17a2b8; }
        .action-edit, .action-create { background-color: #28a745; }
        .action-delete { background-color: #dc3545; }
        .action-copy_password { background-color: #ffc107; color: black; }
    </style>
</head>
<body class="page-manage">
    <div class="admin-container">
        <header class="admin-header">
            <h1>📜 Log de Actividad del Portal</h1>
            <p>Total de eventos: <?= number_format($total_logs) ?> | Mostrando página <?= $page ?> de <?= $total_pages > 0 ? $total_pages : 1 ?></p>
        </header>

        <div class="content">
            <?php if (isset($status_message)) echo $status_message; ?>

            <!-- Formulario de filtros -->
            <form class="filters-form" method="get">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="user-filter">Usuario:</label>
                        <input type="text" id="user-filter" name="user" value="<?= htmlspecialchars($filter_user ?? '') ?>" placeholder="Buscar usuario...">
                    </div>
                    <div class="filter-group">
                        <label for="action-filter">Acción:</label>
                        <select id="action-filter" name="action">
                            <option value="">Todas</option>
                            <?php foreach ($actions as $action): ?>
                                <option value="<?= htmlspecialchars($action) ?>" <?= ($filter_action ?? '') === $action ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $action))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="entity-filter">Entidad:</label>
                        <select id="entity-filter" name="entity">
                            <option value="">Todas</option>
                            <?php foreach ($entities as $entity): ?>
                                <option value="<?= htmlspecialchars($entity) ?>" <?= ($filter_entity ?? '') === $entity ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $entity))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="date-from-filter">Desde:</label>
                        <input type="date" id="date-from-filter" name="date_from" value="<?= htmlspecialchars($filter_date_from ?? '') ?>">
                    </div>
                    <div class="filter-group">
                        <label for="date-to-filter">Hasta:</label>
                        <input type="date" id="date-to-filter" name="date_to" value="<?= htmlspecialchars($filter_date_to ?? '') ?>">
                    </div>
                    <div class="filter-group" style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" class="save-btn" style="padding: 8px 16px;">🔍 Filtrar</button>
                        <a href="activity_log.php" class="back-btn" style="margin-top: 0; padding: 8px 16px;">Limpiar</a>
                        <a href="?export=csv&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'export')) ?>" class="add-btn" style="margin-top: 0; padding: 8px 16px;">📥 Exportar CSV</a>
                    </div>
                </div>
            </form>

            <div class="table-container">
                <table id="logs-table">
                    <thead>
                        <tr>
                            <th scope="col">Fecha y Hora</th>
                            <th scope="col">Usuario</th>
                            <th scope="col">Acción</th>
                            <th scope="col">Entidad</th>
                            <th scope="col">ID Entidad</th>
                            <th scope="col">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No hay registros que coincidan con los filtros.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td data-label="Fecha y Hora">
                                    <?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($log['created_at']))) ?>
                                </td>
                                <td data-label="Usuario">
                                    <?= htmlspecialchars($log['username'] ?? 'Sistema') ?>
                                </td>
                                <td data-label="Acción">
                                    <span class="action-badge action-<?= safe_attr($log['action']) ?>" 
                                          title="<?= htmlspecialchars($log['user_agent'] ?? 'N/A') ?>">
                                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $log['action']))) ?>
                                    </span>
                                </td>
                                <td data-label="Entidad">
                                    <?= htmlspecialchars($log['entity_type']) ?>
                                </td>
                                <td data-label="ID Entidad">
                                    <?= htmlspecialchars($log['entity_id']) ?>
                                </td>
                                <td data-label="IP">
                                    <?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php
                $query_params = http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY));
                ?>
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&<?= $query_params ?>">← Anterior</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>&<?= $query_params ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&<?= $query_params ?>">Siguiente →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <a href="index2.php" class="back-btn">← Volver al Portal</a>

    <?php require_once 'templates/footer.php'; ?>
</body>
</html>

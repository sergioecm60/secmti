<?php
// activity_log.php - Página para visualizar el log de actividad del portal.

require_once 'bootstrap.php';
require_once 'database.php';

// Verificar autenticación y rol de administrador.
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = get_database_connection($config, true);

// --- Carga de datos para mostrar en la tabla ---
$logs = [];
$limit = 50; // Mostrar los últimos 50 registros
try {
    $stmt = $pdo->prepare("
        SELECT l.id, l.action, l.entity_type, l.entity_id, l.ip_address, l.created_at, u.username
        FROM dc_access_log l
        LEFT JOIN users u ON l.user_id = u.id
        ORDER BY l.created_at DESC
        LIMIT :limit
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $status_message = '<div class="status-message error">Error al cargar los logs de actividad.</div>';
    error_log('Activity Log Page Error: ' . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log de Actividad</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="page-manage">
    <div class="admin-container">
        <header class="admin-header">
            <h1>📜 Log de Actividad del Portal</h1>
            <p>Últimos <?= $limit ?> eventos registrados en el sistema.</p>
        </header>

        <div class="content">
            <?php if (isset($status_message)) echo $status_message; ?>

            <div class="table-container">
                <table id="logs-table">
                    <thead>
                        <tr>
                            <th>Fecha y Hora</th>
                            <th>Usuario</th>
                            <th>Acción</th>
                            <th>Entidad</th>
                            <th>ID Entidad</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No hay registros de actividad.</td>
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
                                    <span class="action-badge action-<?= htmlspecialchars($log['action']) ?>">
                                        <?= htmlspecialchars(ucfirst($log['action'])) ?>
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
        </div>
    </div>

    <a href="index2.php" class="back-btn">← Volver al Portal</a>

    <footer class="footer">
        <strong><?= htmlspecialchars($config['footer']['line1'] ?? '') ?></strong><br>
        <span><?= htmlspecialchars($config['footer']['line2'] ?? '') ?></span>
    </footer>
</body>
</html>
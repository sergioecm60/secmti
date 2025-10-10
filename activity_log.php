<?php
/**
 * activity_log.php - Página para visualizar el log de actividad del portal.
 *
 * Este script muestra un registro de las acciones realizadas en el portal,
 * permitiendo a los administradores monitorear la actividad del sistema.
 */

require_once 'bootstrap.php';
require_once 'database.php';

// Verificar autenticación y rol de administrador (control de acceso).
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    // Si el usuario no ha iniciado sesión o no es administrador, redirigir a la página de inicio de sesión.
    header('Location: login.php');
    exit;
}

// Obtener conexión a la base de datos.
$pdo = get_database_connection($config, true);

// --- Carga de datos para mostrar en la tabla de logs ---
$logs = [];
$limit = 50; // Define el número máximo de registros a mostrar en la tabla.
try {
    // Preparar la consulta SQL para obtener los logs de actividad.
    $stmt = $pdo->prepare("
        SELECT l.id, l.action, l.entity_type, l.entity_id, l.ip_address, l.created_at, u.username
        FROM dc_access_log l
        LEFT JOIN users u ON l.user_id = u.id
        ORDER BY l.created_at DESC
        LIMIT :limit
    "); // Ordena los logs por fecha de creación descendente y limita la cantidad.
    
    // Vincular el parámetro :limit con el valor de $limit (protección contra SQL injection).
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    
    // Ejecutar la consulta preparada.
    $stmt->execute();
    
    // Obtener todos los resultados como un array asociativo.
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
        <div class="footer-contact-line">
            <span><?= htmlspecialchars($config['footer']['line2'] ?? '') ?></span>
            <?php if (!empty($config['footer']['whatsapp_number']) && !empty($config['footer']['whatsapp_svg_path'])): ?>
                <a href="https://wa.me/<?= htmlspecialchars($config['footer']['whatsapp_number']) ?>" target="_blank" rel="noopener noreferrer" class="footer-whatsapp-link" aria-label="Contactar por WhatsApp" tabindex="0">
                    <svg class="icon" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="<?= htmlspecialchars($config['footer']['whatsapp_svg_path']) ?>"/>
                    </svg>
                </a>
            <?php endif; ?>
        </div>
        <a href="<?= htmlspecialchars($config['footer']['license_url'] ?? '#') ?>" target="_blank" rel="license">Términos y Condiciones</a>
    </footer>
</body>
</html>

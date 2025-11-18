<?php
/**
 * templates/dashboard_stats.php - Widget de estadísticas para el portal.
 *
 * Este widget muestra un resumen de la infraestructura y la actividad reciente.
 * Solo se muestra a los administradores.
 */

// Doble verificación de seguridad: solo para administradores.
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? 'user') !== 'admin') {
    return; // No mostrar nada si no es admin.
}

// Incluir dependencias solo si no se han cargado antes.
// if (!function_exists('get_database_connection')) { // Obsolete, using Registry
    require_once __DIR__ . '/../database.php';
}

$stats = [];
$recent_activity = [];
$error_message = '';

try {
    // Usar la conexión PDO ya disponible desde bootstrap.php si es posible.
    // Usar la conexión PDO ya disponible desde el Registry.
    $pdo = \SecMTI\Core\Registry::get('pdo');

    if ($pdo) {
        // 1. Obtener estadísticas usando el procedimiento almacenado.
        $stmt_stats = $pdo->query("CALL sp_get_stats()");
        $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
        $stmt_stats->closeCursor(); // ¡CORRECCIÓN! Cerrar el cursor inmediatamente después de usarlo.

        // 2. Obtener estadísticas de PCs
        $stmt_pcs = $pdo->query("SELECT COUNT(*) as total_pcs, SUM(CASE WHEN assigned_to IS NULL THEN 1 ELSE 0 END) as free_pcs FROM pc_equipment");
        $pc_stats = $stmt_pcs->fetch(PDO::FETCH_ASSOC);

    } else {
        $error_message = 'No se pudo conectar a la base de datos para cargar las estadísticas.';
    }
} catch (Exception $e) {
    $error_message = 'Error al cargar las estadísticas del dashboard.';
    error_log('Dashboard Stats Error: ' . $e->getMessage());
}

if (!empty($error_message)): ?>
    <div class="dashboard-warning">
        <div class="warning-icon">⚠️</div>
        <div class="warning-content">
            <h3>Error en el Dashboard</h3>
            <p><?= htmlspecialchars($error_message) ?></p>
        </div>
    </div>
<?php return; endif; ?>

<div class="dashboard-stats">
    <!-- Grid de Estadísticas Principales -->
    <div class="stats-grid">
        <div class="stat-card stat-card-primary">
            <div class="stat-icon">🖥️</div>
            <div class="stat-content"> 
                <div class="stat-number"><?= htmlspecialchars($stats['total_servers'] ?? 0) ?></div>
                <div class="stat-label">Servidores</div>
            </div>
        </div>
        <div class="stat-card stat-card-info">
            <div class="stat-icon">⚙️</div>
            <div class="stat-content">
                <div class="stat-number"><?= htmlspecialchars($stats['total_services'] ?? 0) ?></div>
                <div class="stat-label">Servicios</div>
            </div>
        </div>
        <div class="stat-card stat-card-success">
            <div class="stat-icon">🔑</div>
            <div class="stat-content">
                <div class="stat-number"><?= htmlspecialchars($stats['total_credentials'] ?? 0) ?></div>
                <div class="stat-label">Credenciales</div>
            </div>
        </div>
        <div class="stat-card stat-card-purple">
            <div class="stat-icon">🌐</div>
            <div class="stat-content">
                <div class="stat-number"><?= htmlspecialchars($stats['hosting_servers'] ?? 0) ?></div>
                <div class="stat-label">Hosting Servers</div>
            </div>
        </div>
        <div class="stat-card stat-card-cyan">
            <div class="stat-icon">✉️</div>
            <div class="stat-content">
                <div class="stat-number"><?= htmlspecialchars($stats['email_accounts'] ?? 0) ?></div>
                <div class="stat-label">Email Accounts</div>
            </div>
        </div>
        <div class="stat-card stat-card-warning">
            <div class="stat-icon">⚠️</div>
            <div class="stat-content">
                <div class="stat-number"><?= htmlspecialchars($stats['servers_maintenance'] ?? 0) ?></div>
                <div class="stat-label">En Mantenimiento</div>
            </div>
        </div>
        <div class="stat-card stat-card-blue">
            <div class="stat-icon">💻</div>
            <div class="stat-content">
                <div class="stat-number"><?= htmlspecialchars($pc_stats['total_pcs'] ?? 0) ?></div>
                <div class="stat-label">Total PCs</div>
            </div>
        </div>
        <div class="stat-card stat-card-teal">
            <div class="stat-icon">🔓</div>
            <div class="stat-content">
                <div class="stat-number"><?= htmlspecialchars($pc_stats['free_pcs'] ?? 0) ?></div>
                <div class="stat-label">PCs Libres</div>
            </div>
        </div>
    </div>
</div>
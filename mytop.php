<?php
/**
 * mytop.php - Monitor de Procesos MySQL/MariaDB
 * 
 * Herramienta de diagnóstico para visualizar procesos activos en tiempo real.
 * Solo accesible para administradores.
 * 
 * CARACTERÍSTICAS:
 * - Actualización automática cada 5 segundos
 * - Filtrado en tiempo real
 * - Protección CSRF
 * - Logging de acciones críticas
 * - Rate limiting
 */

// Incluir el archivo de inicialización central.
require_once 'bootstrap.php';

// ============================================================================
// CONTROL DE ACCESO
// ============================================================================

// Verificar autenticación Y rol de administrador
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    log_security_event('unauthorized_access_attempt', 'Intento de acceso a mytop.php sin permisos');
    header('Location: index2.php');
    exit;
}

// Rate limiting
if (!check_rate_limit('mytop_access', 60, 60)) { // 60 accesos por minuto
    http_response_code(429);
    die('Demasiadas solicitudes. Por favor, espera un momento.');
}

// Logging de acceso
try {
    $log_stmt = get_database_connection($config, false)->prepare("INSERT INTO dc_access_log (user_id, action, entity_type, entity_id, ip_address) VALUES (?, 'view', 'mytop', 0, ?)");
    $log_stmt->execute([$_SESSION['user_id'], IP_ADDRESS]);
} catch (Exception $e) {
    error_log("Error al registrar acceso a mytop: " . $e->getMessage());
}

// Generar nonce para CSP
$nonce = base64_encode(random_bytes(16));

// CSP específica
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");

// Conexión a BD
$pdo = get_database_connection($config, true);

// ============================================================================
// MANEJO DE SOLICITUD AJAX
// ============================================================================

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $stmt = $pdo->query("SHOW FULL PROCESSLIST");
        $all_processes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ofuscar información sensible en consultas
        foreach ($all_processes as &$process) {
            if (!empty($process['Info'])) {
                // Ofuscar posibles contraseñas en queries
                $process['Info'] = preg_replace(
                    "/(password|passwd|pwd)\s*=\s*['\"]([^'\"]+)['\"]/i",
                    '$1=\'***HIDDEN***\'',
                    $process['Info']
                );
                
                // Truncar consultas muy largas
                if (strlen($process['Info']) > 500) {
                    $process['Info'] = substr($process['Info'], 0, 500) . '... [truncado]';
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'processes' => array_values($all_processes),
            'timestamp' => time()
        ]);
        
    } catch (PDOException $e) {
        error_log("Error en mytop AJAX: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'No se pudo obtener la lista de procesos.'
        ]);
    }
    exit;
}

// ============================================================================
// ACCIÓN: MATAR PROCESO (con CSRF y logging)
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kill_process'])) {
    try {
        validate_request_csrf();
        
        // Validar ID de proceso
        $id = filter_input(INPUT_POST, 'process_id', FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new Exception('ID de proceso inválido');
        }

        // Obtener información del proceso antes de matarlo (para logging)
        $stmt = $pdo->prepare("SELECT * FROM INFORMATION_SCHEMA.PROCESSLIST WHERE ID = ?");
        $stmt->execute([$id]);
        $process_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Matar el proceso
        $stmt = $pdo->prepare("KILL ?");
        $stmt->execute([$id]);
        
        // Logging detallado
        log_security_event(
            'process_killed',
            "Usuario {$_SESSION['username']} mató proceso ID: {$id}. " .
            "Info: User={$process_info['USER']}, Host={$process_info['HOST']}, " .
            "Command={$process_info['COMMAND']}, Time={$process_info['TIME']}s"
        );
        
        // Redirigir con mensaje de éxito
        $_SESSION['flash_message'] = "Proceso #{$id} terminado exitosamente.";
        header("Location: mytop.php");
        exit;
        
    } catch (Exception $e) {
        error_log("Error al matar proceso {$id}: " . $e->getMessage());
        
        log_security_event(
            'process_kill_failed',
            "Usuario {$_SESSION['username']} intentó matar proceso {$id} pero falló: " . $e->getMessage()
        );
        
        $_SESSION['flash_error'] = "No se pudo terminar el proceso. Verifica los permisos.";
        header("Location: mytop.php");
        exit;
    }
}

// ============================================================================
// CARGA INICIAL
// ============================================================================

$initial_processes = [];
$stats = [
    'total' => 0,
    'sleeping' => 0,
    'queries' => 0,
    'long_running' => 0
];

try {
    $stmt = $pdo->query("SHOW FULL PROCESSLIST");
    $all_processes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular estadísticas
    $stats['total'] = count($all_processes);
    
    foreach ($all_processes as &$process) {
        // Ofuscar información sensible
        if (!empty($process['Info'])) {
            $process['Info'] = preg_replace(
                "/(password|passwd|pwd)\s*=\s*['\"]([^'\"]+)['\"]/i",
                '$1=\'***HIDDEN***\'',
                $process['Info']
            );
            
            if (strlen($process['Info']) > 500) {
                $process['Info'] = substr($process['Info'], 0, 500) . '... [truncado]';
            }
        }
        
        // Estadísticas
        if ($process['Command'] === 'Sleep') {
            $stats['sleeping']++;
        } elseif ($process['Command'] === 'Query') {
            $stats['queries']++;
        }
        
        if (isset($process['Time']) && $process['Time'] > 60) {
            $stats['long_running']++;
        }
    }
    
    $initial_processes = $all_processes;
    
} catch (PDOException $e) {
    error_log("Error carga inicial mytop: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor de Procesos MySQL - <?= htmlspecialchars($config['landing_page']['company_name'] ?? 'Portal') ?></title>
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: #f7f9fc;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #4b6cb7;
            text-align: center;
        }
        
        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #4b6cb7;
            display: block;
        }
        
        .stat-card .label {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.5rem;
        }
        
        .stat-card.warning {
            border-left-color: #ff9800;
        }
        
        .stat-card.warning .value {
            color: #ff9800;
        }
        
        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .filter-container {
            flex: 1;
            min-width: 250px;
        }
        
        #filterInput {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        
        #filterInput:focus {
            outline: none;
            border-color: #4b6cb7;
        }
        
        .refresh-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .refresh-toggle {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 24px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #4b6cb7;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .last-update {
            font-size: 0.85rem;
            color: #666;
        }
        
        .kill-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.35rem 0.75rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        
        .kill-btn:hover {
            background: #c82333;
            transform: translateY(-1px);
        }
        
        .long-running {
            background: #fff3cd;
        }
        
        .flash-message {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            border-left: 4px solid;
        }
        
        .flash-message.success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .flash-message.error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        table code {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            background: #f4f4f4;
            padding: 2px 4px;
            border-radius: 3px;
        }
    </style>
</head>
<body class="page-mytop">
    <div class="container">
        <header class="admin-header">
            <h1>📊 Monitor de Procesos MySQL/MariaDB</h1>
            <p>Usuario: <?= htmlspecialchars($_SESSION['username']) ?> | IP: <?= htmlspecialchars(IP_ADDRESS) ?></p>
        </header>

        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="flash-message success">
                ✓ <?= htmlspecialchars($_SESSION['flash_message']) ?>
            </div>
            <?php unset($_SESSION['flash_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div class="flash-message error">
                ✗ <?= htmlspecialchars($_SESSION['flash_error']) ?>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="value"><?= $stats['total'] ?></span>
                <span class="label">Total Procesos</span>
            </div>
            <div class="stat-card">
                <span class="value"><?= $stats['queries'] ?></span>
                <span class="label">Ejecutando</span>
            </div>
            <div class="stat-card">
                <span class="value"><?= $stats['sleeping'] ?></span>
                <span class="label">En Sleep</span>
            </div>
            <div class="stat-card <?= $stats['long_running'] > 0 ? 'warning' : '' ?>">
                <span class="value"><?= $stats['long_running'] ?></span>
                <span class="label">Largos (>60s)</span>
            </div>
        </div>

        <!-- Controles -->
        <div class="controls">
            <div class="filter-container">
                <input type="search" 
                       id="filterInput" 
                       placeholder="🔍 Buscar por IP, usuario, consulta, etc." 
                       aria-label="Filtrar procesos">
            </div>
            
            <div class="refresh-controls">
                <div class="refresh-toggle">
                    <label class="switch">
                        <input type="checkbox" id="autoRefresh" checked>
                        <span class="slider"></span>
                    </label>
                    <span>Auto-refresh</span>
                </div>
                <div class="last-update" id="lastUpdate">
                    Última actualización: Ahora
                </div>
            </div>
        </div>

        <!-- Tabla de Procesos -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Host</th>
                        <th>DB</th>
                        <th>Comando</th>
                        <th>Tiempo</th>
                        <th>Estado</th>
                        <th>Consulta</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody id="processTableBody">
                    <?php if (empty($initial_processes)): ?>
                        <tr><td colspan="9" style="text-align:center;">No hay procesos activos.</td></tr>
                    <?php else: ?>
                        <?php foreach ($initial_processes as $p): ?>
                        <tr class="<?= (isset($p['Time']) && $p['Time'] > 60) ? 'long-running' : '' ?>">
                            <td data-label="ID"><?= htmlspecialchars($p['Id'] ?? 'N/A') ?></td>
                            <td data-label="Usuario"><?= htmlspecialchars($p['User'] ?? '') ?></td>
                            <td data-label="Host"><?= htmlspecialchars($p['Host'] ?? '') ?></td>
                            <td data-label="DB"><?= htmlspecialchars($p['db'] ?? '') ?></td>
                            <td data-label="Comando"><?= htmlspecialchars($p['Command'] ?? '') ?></td>
                            <td data-label="Tiempo"><?= htmlspecialchars($p['Time'] ?? '') ?></td>
                            <td data-label="Estado"><?= htmlspecialchars($p['State'] ?? '') ?></td>
                            <td data-label="Consulta">
                                <code><?= htmlspecialchars($p['Info'] ?? '') ?></code>
                            </td>
                            <td data-label="Acción">
                                <?php if ($p['Command'] !== 'Sleep' && $p['Id'] != $pdo->query("SELECT CONNECTION_ID()")->fetchColumn()): ?>
                                    <form method="POST" style="display:inline;" class="kill-process-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="process_id" value="<?= htmlspecialchars($p['Id']) ?>">
                                        <button type="submit" name="kill_process" class="kill-btn" title="Terminar proceso">
                                            ✕ Kill
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <a href="index2.php" class="back-btn">← Volver al Portal</a>

    <!-- Pie de página unificado -->
    <footer class="footer">
        <strong><?= htmlspecialchars($config['footer']['line1'] ?? '') ?></strong><br>
        <div class="footer-contact-line">
            <span><?= htmlspecialchars($config['footer']['line2'] ?? '') ?></span>
            <?php if (!empty($config['footer']['whatsapp_number']) && !empty($config['footer']['whatsapp_svg_path'])): ?>
                <a href="https://wa.me/<?= htmlspecialchars($config['footer']['whatsapp_number']) ?>" 
                   target="_blank" rel="noopener noreferrer" 
                   class="footer-whatsapp-link" 
                   aria-label="Contactar por WhatsApp">
                    <svg class="icon" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="<?= $config['footer']['whatsapp_svg_path'] ?>"/>
                    </svg>
                </a>
            <?php endif; ?>
        </div>
        <a href="<?= htmlspecialchars($config['footer']['license_url'] ?? '#') ?>" target="_blank" rel="license">Términos y Condiciones (Licencia GNU GPL v3)</a>
    </footer>

    <script nonce="<?= htmlspecialchars($nonce) ?>">
        document.addEventListener('DOMContentLoaded', function() {
            const tbody = document.getElementById('processTableBody');
            const filterInput = document.getElementById('filterInput');
            const autoRefreshToggle = document.getElementById('autoRefresh');
            const lastUpdateEl = document.getElementById('lastUpdate');
            
            let currentProcesses = [];
            let refreshInterval = null;
            let myConnectionId = <?= $pdo->query("SELECT CONNECTION_ID()")->fetchColumn() ?>;

            function escapeHTML(str) {
                if (str === null || str === undefined) return '';
                const div = document.createElement('div');
                div.textContent = String(str);
                return div.innerHTML;
            }

            function formatTime(seconds) {
                const date = new Date();
                return date.toLocaleTimeString('es-AR');
            }

            function renderTable() {
                const filterText = filterInput.value.toLowerCase();

                const processesToRender = filterText
                    ? currentProcesses.filter(p =>
                        Object.values(p).some(val =>
                            String(val).toLowerCase().includes(filterText)
                        )
                      )
                    : currentProcesses;

                tbody.innerHTML = '';

                if (processesToRender.length === 0) {
                    const message = filterText 
                        ? 'No se encontraron procesos con ese filtro.' 
                        : 'No hay procesos activos.';
                    tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;">${message}</td></tr>`;
                    return;
                }

                processesToRender.forEach(p => {
                    const isLongRunning = p.Time > 60;
                    const canKill = p.Command !== 'Sleep' && p.Id != myConnectionId;
                    
                    const row = document.createElement('tr');
                    if (isLongRunning) row.classList.add('long-running');
                    
                    row.innerHTML = `
                        <td data-label="ID">${escapeHTML(p.Id)}</td>
                        <td data-label="Usuario">${escapeHTML(p.User)}</td>
                        <td data-label="Host">${escapeHTML(p.Host)}</td>
                        <td data-label="DB">${escapeHTML(p.db)}</td>
                        <td data-label="Comando">${escapeHTML(p.Command)}</td>
                        <td data-label="Tiempo">${escapeHTML(p.Time)}</td>
                        <td data-label="Estado">${escapeHTML(p.State)}</td>
                        <td data-label="Consulta"><code>${escapeHTML(p.Info)}</code></td>
                        <td data-label="Acción">
                            ${canKill ? `
                                <form method="POST" style="display:inline;" class="kill-process-form" action="mytop.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="process_id" value="${p.Id}">
                                    <button type="submit" name="kill_process" class="kill-btn" title="Terminar proceso">
                                        ✕ Kill
                                    </button>
                                </form>
                            ` : ''}
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }

            async function fetchAndUpdate() {
                try {
                    const response = await fetch('mytop.php?ajax=1');
                    if (!response.ok) throw new Error('Error en la respuesta del servidor');

                    const data = await response.json();

                    if (data.success) {
                        currentProcesses = data.processes || [];
                        renderTable();
                        
                        // Actualizar timestamp
                        const now = new Date();
                        lastUpdateEl.textContent = `Última actualización: ${now.toLocaleTimeString('es-AR')}`;
                    } else {
                        console.error('Error:', data.error);
                        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;">Error: ${escapeHTML(data.error)}</td></tr>`;
                    }
                } catch (error) {
                    console.error('Error al actualizar procesos:', error);
                    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;">Error al cargar los procesos.</td></tr>';
                }
            }

            function startAutoRefresh() {
                if (refreshInterval) clearInterval(refreshInterval);
                refreshInterval = setInterval(fetchAndUpdate, 5000);
            }

            function stopAutoRefresh() {
                if (refreshInterval) {
                    clearInterval(refreshInterval);
                    refreshInterval = null;
                }
            }

            // Event Listeners
            filterInput.addEventListener('input', renderTable);
            
            autoRefreshToggle.addEventListener('change', function() {
                if (this.checked) {
                    startAutoRefresh();
                } else {
                    stopAutoRefresh();
                }
            });

            // Event delegation para los formularios de "kill"
            tbody.addEventListener('submit', function(e) {
                if (e.target.classList.contains('kill-process-form')) {
                    const processId = e.target.querySelector('input[name="process_id"]').value;
                    if (!confirm(`¿Estás seguro de que quieres terminar el proceso #${processId}?`)) {
                        e.preventDefault();
                    }
                }
            });


            // Inicial
            fetchAndUpdate();
            startAutoRefresh();
        });
    </script>
</body>
</html>
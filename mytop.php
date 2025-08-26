<?php
// mytop.php - Monitor de procesos de la base de datos.

// Incluir el archivo de inicialización central.
require_once 'bootstrap.php';

// Generar un 'nonce' para el script inline, mejorando la seguridad CSP.
$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self';");

// Verificar autenticación. Si no está logueado, redirigir a la página de acceso.
if (!isset($_SESSION['acceso_info']) || $_SESSION['acceso_info'] !== true) {
    header('Location: index2.php');
    exit;
}

// Conexión a la base de datos a través del nuevo manejador.
require_once 'database.php';
$pdo = get_database_connection($config, true);

// --- MANEJO DE LA SOLICITUD AJAX ---
// Si la solicitud es para AJAX (hecha por el JavaScript), devolvemos solo los datos en formato JSON.
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    // --- Configuración de Errores para API ---
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);

    try {
        $stmt = $pdo->query("SHOW FULL PROCESSLIST");
        $all_processes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_values($all_processes));
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'No se pudo obtener la lista de procesos.']);
    }
    exit();
}

// --- ACCIÓN PARA MATAR UN PROCESO ---
if (isset($_GET['kill']) && is_numeric($_GET['kill'])) {
    $id = (int)$_GET['kill'];
    try {
        $pdo->exec("KILL $id");
        // Redirigir para evitar reenvío del formulario y limpiar la URL
        header("Location: mytop.php");
        exit;
    } catch (PDOException $e) {
        error_log("Error al intentar matar el proceso $id: " . $e->getMessage());
        die("Error al matar proceso. Es posible que no tenga los permisos necesarios o que el proceso ya haya terminado.");
    }
}

// --- Carga inicial de procesos para renderizado PHP ---
$initial_processes = [];
try {
    $stmt = $pdo->query("SHOW FULL PROCESSLIST");
    $all_processes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $initial_processes = $all_processes;
} catch (PDOException $e) {
    error_log("Error en la carga inicial de procesos en mytop.php: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesos de MySQL/MariaDB - MyTop</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="page-mytop">
    <div class="container">
        <header class="admin-header">
            <h1>Procesos de MySQL/MariaDB</h1>
        </header>
        <div class="filter-container">
            <input type="search" id="filterInput" placeholder="Buscar por IP, usuario, consulta, etc." aria-label="Filtrar procesos">
        </div>
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
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($initial_processes)): ?>
                        <tr><td colspan="8" style="text-align:center;">No hay procesos activos.</td></tr>
                    <?php else: ?>
                        <?php foreach (array_values($initial_processes) as $p): ?>
                        <tr>
                            <td data-label="ID"><?= htmlspecialchars($p['Id'] ?? 'N/A') ?></td>
                            <td data-label="Usuario"><?= htmlspecialchars($p['User'] ?? '') ?></td>
                            <td data-label="Host"><?= htmlspecialchars($p['Host'] ?? '') ?></td>
                            <td data-label="DB"><?= htmlspecialchars($p['db'] ?? '') ?></td>
                            <td data-label="Comando"><?= htmlspecialchars($p['Command'] ?? '') ?></td>
                            <td data-label="Tiempo"><?= htmlspecialchars($p['Time'] ?? '') ?></td>
                            <td data-label="Estado"><?= htmlspecialchars($p['State'] ?? '') ?></td>
                            <td data-label="Consulta"><code><?= htmlspecialchars($p['Info'] ?? '') ?></code></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <a href="index2.php" class="back-btn">
        ← Volver al Portal de Servicios
    </a>

    <!-- Pie de página unificado -->
    <footer class="footer">
        <strong><?= htmlspecialchars($config['footer']['line1'] ?? '') ?></strong><br>
        <div class="footer-contact-line">
            <span><?= htmlspecialchars($config['footer']['line2'] ?? '') ?></span>
            <?php if (!empty($config['footer']['whatsapp_number']) && !empty($config['footer']['whatsapp_svg_path'])): ?>
                <a href="https://wa.me/<?= htmlspecialchars($config['footer']['whatsapp_number']) ?>" target="_blank" rel="noopener noreferrer" class="footer-whatsapp-link" aria-label="Contactar por WhatsApp" tabindex="0">
                    <svg class="icon" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="<?= $config['footer']['whatsapp_svg_path'] ?>"/>
                    </svg>
                    <span><?= htmlspecialchars($config['footer']['whatsapp_number']) ?></span>
                </a>
            <?php endif; ?>
        </div>
        <a href="<?= htmlspecialchars($config['footer']['license_url'] ?? '#') ?>" target="_blank" rel="license">Términos y Condiciones (Licencia GNU GPL v3)</a>
    </footer>

    <script nonce="<?= htmlspecialchars($nonce) ?>">
        document.addEventListener('DOMContentLoaded', function() {
            const tbody = document.querySelector('table tbody');
            const filterInput = document.getElementById('filterInput');
            let currentProcesses = [];

            function escapeHTML(str) {
                if (str === null || str === undefined) return '';
                const p = document.createElement('p');
                p.textContent = String(str);
                return p.innerHTML;
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
                    const message = filterText ? 'No se encontraron procesos con ese filtro.' : 'No hay procesos activos.';
                    tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;">${message}</td></tr>`;
                    return;
                }

                processesToRender.forEach(p => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td data-label="ID">${escapeHTML(p.Id)}</td>
                        <td data-label="Usuario">${escapeHTML(p.User)}</td>
                        <td data-label="Host">${escapeHTML(p.Host)}</td>
                        <td data-label="DB">${escapeHTML(p.db)}</td>
                        <td data-label="Comando">${escapeHTML(p.Command)}</td>
                        <td data-label="Tiempo">${escapeHTML(p.Time)}</td>
                        <td data-label="Estado">${escapeHTML(p.State)}</td>
                        <td data-label="Consulta"><code>${escapeHTML(p.Info)}</code></td>
                    `;
                    tbody.appendChild(row);
                });
            }

            async function fetchAndUpdate() {
                try {
                    const response = await fetch('mytop.php?ajax=1');
                    if (!response.ok) throw new Error('La respuesta de la red no fue correcta.');

                    const processes = await response.json();

                    if (processes.error) {
                        currentProcesses = [];
                        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;">Error: ${escapeHTML(processes.error)}</td></tr>`;
                    } else {
                        currentProcesses = processes || [];
                        renderTable();
                    }
                } catch (error) {
                    console.error('Error al actualizar la lista de procesos:', error);
                    currentProcesses = [];
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">Error al cargar los procesos.</td></tr>';
                }
            }

            filterInput.addEventListener('input', renderTable);
            fetchAndUpdate();
            setInterval(fetchAndUpdate, 5000);
        });
    </script>
</body>
</html>
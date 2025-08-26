<?php
// info.php - Página de información del servidor con acceso restringido.

// Incluir el archivo de inicialización central.
require_once 'bootstrap.php';

// CSP específica para esta página
header("Content-Security-Policy: default-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;");

// Verificar autenticación
if (!isset($_SESSION['acceso_info']) || $_SESSION['acceso_info'] !== true) {
    header('Location: index2.php');
    exit;
}

// Conexión a la base de datos
require_once 'database.php';
$pdo = get_database_connection($config, false);

// --- Constante para detectar el Sistema Operativo ---
define('IS_WINDOWS', strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

// --- Funciones para obtener informacion del sistema ---
function get_os_info() {
    if (!IS_WINDOWS && is_readable('/etc/os-release')) {
        $os_release = file_get_contents('/etc/os-release');
        if (preg_match('/PRETTY_NAME="(.+?)"/', $os_release, $matches)) {
            return $matches[1];
        }
    }
    return php_uname('s') . ' ' . php_uname('r');
}

function get_software_version($command, $version_flag = '-v', $name = '') {
    if (IS_WINDOWS) {
        return '<span class="not-found">No disponible en Windows</span>';
    }
    if (!function_exists('shell_exec') || stripos(ini_get('disable_functions'), 'shell_exec') !== false) {
        return '<span class="not-found">N/A (shell_exec deshabilitado)</span>';
    }
    $output = shell_exec("$command $version_flag 2>&1");
    if ($output === null || preg_match('/(not found|command not found|no se encuentra|no se reconoce)/i', $output)) {
        return '<span class="not-found">No encontrado</span>';
    }
    if ($name === 'java') {
        preg_match('/version "(.+?)"/', $output, $matches);
        return htmlspecialchars($matches[1] ?? trim($output));
    }
    return htmlspecialchars(trim($output));
}

function get_ram_usage() {
    if (!IS_WINDOWS && is_readable('/proc/meminfo')) {
        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
        if (isset($total[1]) && isset($available[1])) {
            $total_kb = (int)$total[1];
            $available_kb = (int)$available[1];
            $used_kb = $total_kb - $available_kb;
            $percent = ($total_kb > 0) ? ($used_kb / $total_kb) * 100 : 0;
            return ['total' => $total_kb, 'used' => $used_kb, 'percent' => round($percent)];
        }
    }
    return null;
}

function get_cpu_info() {
    if (!IS_WINDOWS && is_readable('/proc/cpuinfo')) {
        $cpuinfo = file_get_contents('/proc/cpuinfo');
        preg_match('/model name\s+:\s+(.+)/', $cpuinfo, $model);
        $cores = preg_match_all('/^processor/m', $cpuinfo, $matches);
        return ($model ? trim($model[1]) : 'Desconocido') . " ($cores núcleos)";
    }
    return 'No disponible';
}

function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// --- Obtener datos del sistema ---
$os_info = get_os_info();
$disk_total = @disk_total_space('/');
$disk_free = disk_free_space('/');
$disk_used = $disk_total - $disk_free;
$disk_percent = ($disk_total > 0) ? ($disk_used / $disk_total) * 100 : 0;
$ram_usage = get_ram_usage();
$cpu_info = get_cpu_info();

try {
    $db_version = $pdo ? $pdo->query("SELECT VERSION()")->fetchColumn() : '<span class="not-found">No se pudo conectar</span>';
} catch (PDOException $e) {
    error_log("Error al obtener la versión de la BD en info.php: " . $e->getMessage());
    $db_version = '<span class="not-found">No se pudo conectar</span>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Info Servidor - <?= htmlspecialchars($config['landing_page']['company_name'] ?? 'Empresa') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="page-info">
    <div class="container">
        <header class="admin-header">
            <h1>🔍 Información del Servidor</h1>
            <p>Acceso autorizado - Solo para administradores</p>
        </header>

        <div class="content">
            <!-- Informacion Basica -->
            <div class="info-grid">
                <div class="info-card">
                    <strong>IP Cliente</strong>
                    <span><?= htmlspecialchars(IP_ADDRESS, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="info-card">
                    <strong>Software</strong>
                    <span><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="info-card">
                    <strong>PHP v<?= phpversion() ?></strong>
                    <span><?= php_sapi_name() ?></span>
                </div>
                <div class="info-card">
                    <strong>Sistema</strong>
                    <span><?= htmlspecialchars($os_info, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>

            <!-- Entorno de Desarrollo -->
            <div class="section">
                <div class="section-header">🛠️ Entorno de Desarrollo</div>
                <div class="section-body">
                    <div class="info-grid">
                        <div class="info-card">
                            <strong>Base de Datos</strong>
                            <span><?= $db_version ?></span>
                        </div>
                        <div class="info-card">
                            <strong>Java</strong>
                            <span><?= get_software_version('java', '-version', 'java') ?></span>
                        </div>
                        <div class="info-card">
                            <strong>Node.js</strong>
                            <span><?= get_software_version('node', '-v', 'node') ?></span>
                        </div>
                        <div class="info-card">
                            <strong>Strapi</strong>
                            <span><?= get_software_version('strapi', '-v', 'strapi') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estado del Sistema -->
            <div class="section">
                <div class="section-header">📊 Estado del Sistema</div>
                <div class="section-body">
                    <div class="info-card" style="margin-bottom: 1rem;">
                        <strong>CPU</strong>
                        <span><?= htmlspecialchars($cpu_info) ?></span>
                    </div>
                    <div class="info-card" style="margin-bottom: 1rem;">
                        <strong>Uso de Disco (/)</strong>
                        <span><?= format_bytes($disk_used) ?> / <?= format_bytes($disk_total) ?></span>
                        <div class="progress-bar">
                            <div class="progress-bar-inner" style="width: <?= round($disk_percent) ?>%;"><?= round($disk_percent) ?>%</div>
                        </div>
                    </div>
                    <?php if ($ram_usage): ?>
                    <div class="info-card">
                        <strong>Uso de RAM</strong>
                        <span><?= format_bytes($ram_usage['used'] * 1024) ?> / <?= format_bytes($ram_usage['total'] * 1024) ?></span>
                        <div class="progress-bar">
                            <div class="progress-bar-inner" style="width: <?= $ram_usage['percent'] ?>%;"><?= $ram_usage['percent'] ?>%</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section">
                <div class="section-header">📦 Modulos PHP Cargados</div>
                <div class="section-body">
                    <pre><?php echo implode("\n", get_loaded_extensions()); ?></pre>
                </div>
            </div>

            <!-- Variables de Entorno Relevantes -->
            <div class="section">
                <div class="section-header">⚙️ Variables de Entorno (Clave)</div>
                <div class="section-body">
                    <pre><?php
                        $claves = [
                            'SERVER_NAME',
                            'DOCUMENT_ROOT',
                            'HTTP_HOST',
                            'HTTPS',
                            'PHP_SAPI',
                            'REQUEST_METHOD',
                            'REDIRECT_STATUS',
                            'HTTP_USER_AGENT',
                            'HTTP_X_FORWARDED_FOR',
                            'REMOTE_ADDR'
                        ];
                        $env = [];
                        foreach ($claves as $k) {
                            if (isset($_SERVER[$k])) {
                                $env[] = "$k: " . $_SERVER[$k];
                            }
                        }
                        echo implode("\n", $env);
                    ?></pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Botón de Volver -->
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
</body>
</html>
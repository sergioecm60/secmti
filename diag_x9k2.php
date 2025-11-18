<?php
/**
 * diag_x9k2.php - Información del Servidor (Acceso Restringido)
 * 
 * Página de diagnóstico del sistema con información técnica
 * SOLO para administradores de nivel superior.
 */

require_once 'bootstrap.php';

// ============================================================================
// CONTROL DE ACCESO
// ============================================================================

if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    \SecMTI\Core\Registry::get('securityLogger')->log('unauthorized_access_attempt', 'Intento de acceso a diag_x9k2.php sin permisos');
    header('Location: index2.php');
    exit;
}

if (!\SecMTI\Core\Registry::get('rateLimiter')->check('server_info_access', 10, 300)) {
    \SecMTI\Core\Registry::get('securityLogger')->log('rate_limit_exceeded', 'Rate limit excedido en diag_x9k2.php');
    http_response_code(429);
    die('Demasiadas solicitudes. Por favor, intente más tarde.');
}

\SecMTI\Core\Registry::get('securityLogger')->log('server_info_access', "Usuario {$_SESSION['username']} accedió a información del servidor");

// Generar nonce para CSP
$nonce = base64_encode(random_bytes(16));

// CSP específica
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");

// ============================================================================
// CONSTANTES
// ============================================================================

define('IS_WINDOWS', PHP_OS_FAMILY === 'Windows');
define('CACHE_TTL', 300);

// ============================================================================
// FUNCIONES (las mismas que ya tienes)
// ============================================================================

function get_cached_system_info(string $key, callable $callback) {
    if (!isset($_SESSION['system_info_cache'])) {
        $_SESSION['system_info_cache'] = [];
    }
    
    $now = time();
    $cache_key = "cache_{$key}";
    $time_key = "time_{$key}";
    
    if (isset($_SESSION['system_info_cache'][$cache_key]) &&
        isset($_SESSION['system_info_cache'][$time_key]) &&
        ($now - $_SESSION['system_info_cache'][$time_key]) < CACHE_TTL) {
        return $_SESSION['system_info_cache'][$cache_key];
    }
    
    $data = $callback();
    $_SESSION['system_info_cache'][$cache_key] = $data;
    $_SESSION['system_info_cache'][$time_key] = $now;
    
    return $data;
}

function get_os_info(): string {
    return get_cached_system_info('os_info', function() {
        if (!IS_WINDOWS && is_readable('/etc/os-release')) {
            try {
                $os_release = file_get_contents('/etc/os-release');
                if (preg_match('/PRETTY_NAME="(.+?)"/', $os_release, $matches)) {
                    return htmlspecialchars($matches[1]);
                }
            } catch (Exception $e) {
                error_log("Error leyendo /etc/os-release: " . $e->getMessage());
            }
        }
        return htmlspecialchars(php_uname('s') . ' ' . php_uname('r'));
    });
}

function get_software_version(string $software): string {
    return get_cached_system_info("software_{$software}", function() use ($software) {
        if (IS_WINDOWS) {
            return '<span class="not-found">No disponible en Windows</span>';
        }
        
        if (!function_exists('exec') || in_array('exec', explode(',', ini_get('disable_functions')))) {
            return '<span class="not-found">N/A (exec deshabilitado)</span>';
        }
        
        $allowed_software = [
            'php' => ['cmd' => 'php', 'flag' => '-v', 'pattern' => '/PHP (\d+\.\d+\.\d+)/'],
            'mysql' => ['cmd' => 'mysql', 'flag' => '--version', 'pattern' => '/Distrib (\d+\.\d+\.\d+)/'],
            'apache' => ['cmd' => 'apache2', 'flag' => '-v', 'pattern' => '/Apache\/(\S+)/'],
            'nginx' => ['cmd' => 'nginx', 'flag' => '-v', 'pattern' => '/nginx\/(\S+)/'],
            'node' => ['cmd' => 'node', 'flag' => '--version', 'pattern' => '/v(\d+\.\d+\.\d+)/'],
            'python' => ['cmd' => 'python3', 'flag' => '--version', 'pattern' => '/Python (\d+\.\d+\.\d+)/'],
            'git' => ['cmd' => 'git', 'flag' => '--version', 'pattern' => '/git version (\S+)/'],
        ];
        
        if (!isset($allowed_software[$software])) {
            return '<span class="not-found">Software no reconocido</span>';
        }
        
        $cfg = $allowed_software[$software];
        
        $which = exec("which " . escapeshellarg($cfg['cmd']) . " 2>/dev/null", $output, $return_code);
        if ($return_code !== 0 || empty($which)) {
            return '<span class="not-found">No instalado</span>';
        }
        
        $cmd = escapeshellcmd($cfg['cmd']) . ' ' . escapeshellarg($cfg['flag']) . ' 2>&1';
        $output = [];
        exec($cmd, $output, $return_code);
        
        if ($return_code !== 0 || empty($output)) {
            return '<span class="not-found">Error al consultar</span>';
        }
        
        $output_str = implode(' ', $output);
        
        if (isset($cfg['pattern']) && preg_match($cfg['pattern'], $output_str, $matches)) {
            return htmlspecialchars($matches[1]);
        }
        
        return htmlspecialchars(substr($output_str, 0, 100));
    });
}

function get_ram_usage(): ?array {
    return get_cached_system_info('ram_usage', function() {
        if (!IS_WINDOWS && is_readable('/proc/meminfo')) {
            try {
                $meminfo = file_get_contents('/proc/meminfo');
                preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
                preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
                
                if (isset($total[1]) && isset($available[1])) {
                    $total_kb = (int)$total[1];
                    $available_kb = (int)$available[1];
                    $used_kb = $total_kb - $available_kb;
                    $percent = ($total_kb > 0) ? ($used_kb / $total_kb) * 100 : 0;
                    
                    return [
                        'total' => $total_kb,
                        'used' => $used_kb,
                        'available' => $available_kb,
                        'percent' => round($percent, 1)
                    ];
                }
            } catch (Exception $e) {
                error_log("Error leyendo /proc/meminfo: " . $e->getMessage());
            }
        }
        return null;
    });
}

function get_cpu_info(): string {
    return get_cached_system_info('cpu_info', function() {
        if (!IS_WINDOWS && is_readable('/proc/cpuinfo')) {
            try {
                $cpuinfo = file_get_contents('/proc/cpuinfo');
                preg_match('/model name\s+:\s+(.+)/m', $cpuinfo, $model);
                $model_name = isset($model[1]) ? trim($model[1]) : 'Desconocido';
                $cores = preg_match_all('/^processor/m', $cpuinfo, $matches);
                $model_name = preg_replace('/\s+/', ' ', $model_name);
                $model_name = preg_replace('/\(R\)|\(TM\)/', '', $model_name);
                return htmlspecialchars($model_name . " ({$cores} núcleos)");
            } catch (Exception $e) {
                error_log("Error leyendo /proc/cpuinfo: " . $e->getMessage());
            }
        }
        return 'No disponible';
    });
}

function get_load_average(): ?string {
    if (!IS_WINDOWS && function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        if ($load !== false) {
            return sprintf('%.2f, %.2f, %.2f', $load[0], $load[1], $load[2]);
        }
    }
    return null;
}

function format_bytes(int $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function get_usage_class(float $percent): string {
    if ($percent >= 90) return 'critical';
    if ($percent >= 75) return 'warning';
    if ($percent >= 50) return 'moderate';
    return 'normal';
}

// ============================================================================
// OBTENER DATOS
// ============================================================================

$pdo = \SecMTI\Core\Registry::get('pdo');

$os_info = get_os_info();
$php_version = phpversion();
$php_sapi = php_sapi_name();
$server_software = htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido');

$software_versions = [
    'PHP' => $php_version,
    'MySQL/MariaDB' => 'N/A',
    'Apache/Nginx' => 'N/A',
    'Node.js' => get_software_version('node'),
    'Python' => get_software_version('python'),
    'Git' => get_software_version('git'),
];

try {
    if ($pdo) {
        $db_version = $pdo->query("SELECT VERSION()")->fetchColumn();
        if (preg_match('/^(\d+\.\d+)\.\d+/', $db_version, $matches)) {
            $software_versions['MySQL/MariaDB'] = htmlspecialchars($matches[1] . '.x');
        } else {
            $software_versions['MySQL/MariaDB'] = 'Conectado';
        }
    }
} catch (PDOException $e) {
    error_log("Error al obtener versión de BD: " . $e->getMessage());
    $software_versions['MySQL/MariaDB'] = '<span class="not-found">Error de conexión</span>';
}

if (stripos($server_software, 'apache') !== false) {
    $software_versions['Apache/Nginx'] = get_software_version('apache');
} elseif (stripos($server_software, 'nginx') !== false) {
    $software_versions['Apache/Nginx'] = get_software_version('nginx');
}

$disk_total = disk_total_space('/') ?: 0;
$disk_free = disk_free_space('/') ?: 0;
$disk_used = $disk_total - $disk_free;
$disk_percent = ($disk_total > 0) ? ($disk_used / $disk_total) * 100 : 0;

$ram_usage = get_ram_usage();
$cpu_info = get_cpu_info();
$load_average = get_load_average();

$uptime = null;
if (!IS_WINDOWS && is_readable('/proc/uptime')) {
    $uptime_seconds = (int) explode(' ', file_get_contents('/proc/uptime'))[0];
    $days = floor($uptime_seconds / 86400);
    $hours = floor(($uptime_seconds % 86400) / 3600);
    $minutes = floor(($uptime_seconds % 3600) / 60);
    $uptime = "{$days}d {$hours}h {$minutes}m";
}

$relevant_modules = ['pdo', 'pdo_mysql', 'mysqli', 'openssl', 'curl', 'gd', 'mbstring', 'json', 'xml', 'session', 'fileinfo', 'zip'];
$loaded_extensions = array_intersect(get_loaded_extensions(), $relevant_modules);
sort($loaded_extensions);

$safe_server_vars = [
    'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'N/A',
    'SERVER_PORT' => $_SERVER['SERVER_PORT'] ?? 'N/A',
    'DOCUMENT_ROOT' => substr($_SERVER['DOCUMENT_ROOT'] ?? 'N/A', 0, 50) . '...',
    'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'N/A',
    'HTTPS' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'Sí' : 'No',
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Información del Servidor - <?= htmlspecialchars($config['landing_page']['company_name'] ?? 'Portal') ?></title>
    
    <!-- Preconnect para optimizar fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS Principal - RUTA ABSOLUTA desde la raíz del sitio -->
    <link rel="stylesheet" href="/secmti/assets/css/main.css">
    
    <!-- Estilos específicos de esta página -->
    <style>
        /* Clases de estado de uso */
        .usage-normal { color: #28a745; }
        .usage-moderate { color: #ffc107; }
        .usage-warning { color: #ff9800; }
        .usage-critical { color: #dc3545; }
        
        /* Barras de progreso con gradientes */
        .progress-bar-inner.normal { 
            background: linear-gradient(90deg, #28a745, #20c997); 
        }
        .progress-bar-inner.moderate { 
            background: linear-gradient(90deg, #ffc107, #ffb300); 
        }
        .progress-bar-inner.warning { 
            background: linear-gradient(90deg, #ff9800, #ff5722); 
        }
        .progress-bar-inner.critical { 
            background: linear-gradient(90deg, #dc3545, #c62828); 
        }
        
        /* Badges */
        .info-card .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 500;
            margin-left: 8px;
        }
        .badge.success { background: #d4edda; color: #155724; }
        .badge.warning { background: #fff3cd; color: #856404; }
        .badge.danger { background: #f8d7da; color: #721c24; }
        
        /* Alertas */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            border-left: 4px solid;
        }
        .alert-warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        .alert-info {
            background: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }
    </style>
</head>
<body class="page-info">
    <div class="container">
        <header class="admin-header">
            <h1>🔍 Información del Servidor</h1>
            <p>Panel de diagnóstico técnico | Acceso: <?= htmlspecialchars($_SESSION['username']) ?></p>
            <?php if ($uptime): ?>
                <p style="font-size: 0.9em; opacity: 0.8;">⏱️ Uptime: <?= htmlspecialchars($uptime) ?></p>
            <?php endif; ?>
        </header>

        <div class="content">
            <!-- Alerta de Seguridad -->
            <div class="alert alert-warning">
                ⚠️ <strong>Información Sensible:</strong> Esta página contiene datos técnicos del servidor. 
                El acceso está registrado y monitoreado.
            </div>

            <!-- Información Básica -->
            <div class="section">
                <div class="section-header">💻 Información Básica</div>
                <div class="section-body">
                    <div class="info-grid">
                        <div class="info-card">
                            <strong>Tu IP</strong>
                            <span><?= htmlspecialchars(IP_ADDRESS) ?></span>
                        </div>
                        <div class="info-card">
                            <strong>Servidor Web</strong>
                            <span><?= $server_software ?></span>
                        </div>
                        <div class="info-card">
                            <strong>PHP</strong>
                            <span>v<?= htmlspecialchars($php_version) ?> (<?= htmlspecialchars($php_sapi) ?>)</span>
                        </div>
                        <div class="info-card">
                            <strong>Sistema Operativo</strong>
                            <span><?= $os_info ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Software Instalado -->
            <div class="section">
                <div class="section-header">🛠️ Software Instalado</div>
                <div class="section-body">
                    <div class="info-grid">
                        <?php foreach ($software_versions as $name => $version): ?>
                        <div class="info-card">
                            <strong><?= htmlspecialchars($name) ?></strong>
                            <span><?= $version ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Estado del Sistema -->
            <div class="section">
                <div class="section-header">📊 Estado del Sistema</div>
                <div class="section-body">
                    <!-- CPU -->
                    <div class="info-card">
                        <strong>Procesador</strong>
                        <span><?= $cpu_info ?></span>
                        <?php if ($load_average): ?>
                            <small style="display: block; margin-top: 5px; opacity: 0.7;">
                                Load Average: <?= htmlspecialchars($load_average) ?>
                            </small>
                        <?php endif; ?>
                    </div>

                    <!-- Disco -->
                    <div class="info-card">
                        <strong>Uso de Disco (/)</strong>
                        <span class="usage-<?= get_usage_class($disk_percent) ?>">
                            <?= format_bytes($disk_used) ?> / <?= format_bytes($disk_total) ?>
                            <span class="badge <?= $disk_percent >= 85 ? 'danger' : ($disk_percent >= 70 ? 'warning' : 'success') ?>">
                                <?= round($disk_percent) ?>%
                            </span>
                        </span>
                        <div class="progress-bar">
                            <div class="progress-bar-inner <?= get_usage_class($disk_percent) ?>" 
                                 style="--progress-width: <?= round($disk_percent) ?>%;"></div>
                        </div>
                    </div>

                    <!-- RAM -->
                    <?php if ($ram_usage): ?>
                    <div class="info-card">
                        <strong>Uso de RAM</strong>
                        <span class="usage-<?= get_usage_class($ram_usage['percent']) ?>">
                            <?= format_bytes($ram_usage['used'] * 1024, 1) ?> / <?= format_bytes($ram_usage['total'] * 1024, 1) ?>
                            <span class="badge <?= $ram_usage['percent'] >= 85 ? 'danger' : ($ram_usage['percent'] >= 70 ? 'warning' : 'success') ?>">
                                <?= $ram_usage['percent'] ?>%
                            </span>
                        </span>
                        <div class="progress-bar">
                            <div class="progress-bar-inner <?= get_usage_class($ram_usage['percent']) ?>" 
                                 style="--progress-width: <?= $ram_usage['percent'] ?>%;"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Módulos PHP -->
            <div class="section">
                <div class="section-header">📦 Módulos PHP Principales</div>
                <div class="section-body">
                    <div class="info-card">
                        <span><?= htmlspecialchars(implode(', ', $loaded_extensions)) ?></span>
                    </div>
                </div>
            </div>

            <!-- Variables de Entorno -->
            <div class="section">
                <div class="section-header">⚙️ Variables de Servidor (Filtradas)</div>
                <div class="section-body">
                    <div class="info-grid">
                        <?php foreach ($safe_server_vars as $key => $value): ?>
                        <div class="info-card">
                            <strong><?= htmlspecialchars($key) ?></strong>
                            <span><?= htmlspecialchars($value) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Nota de Seguridad -->
            <div class="alert alert-info">
                <strong>ℹ️ Nota de Seguridad:</strong> 
                Algunas versiones se muestran de forma ofuscada para prevenir ataques dirigidos.
                El acceso a esta página queda registrado en los logs de seguridad.
            </div>
        </div>
    </div>

    <!-- Botón Volver -->
    <a href="index2.php" class="back-btn">← Volver al Portal</a>

    <!-- Footer -->
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
                        <path d="<?= htmlspecialchars($config['footer']['whatsapp_svg_path']) ?>"/>
                    </svg>
                </a>
            <?php endif; ?>
        </div>
        <a href="<?= htmlspecialchars($config['footer']['license_url'] ?? '#') ?>" 
           target="_blank" rel="license">
            Términos y Condiciones
        </a>
    </footer>

    <!-- Script con nonce para CSP -->
    <script nonce="<?= htmlspecialchars($nonce) ?>">
        // Auto-refresh cada 60 segundos
        setTimeout(() => {
            location.reload();
        }, 60000);
    </script>
</body>
</html>

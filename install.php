<?php
/**
 * install.php - Asistente de Instalación Web para el Portal SECMTI
 *
 * Este script guía al usuario a través de un proceso de instalación seguro y por pasos.
 *
 * CARACTERÍSTICAS:
 * - Asistente multi-paso con validación progresiva.
 * - Verificación de requisitos del servidor (PHP, extensiones, permisos).
 * - Generación segura del archivo de configuración `.env`.
 * - Creación de la base de datos e importación del esquema SQL de forma robusta.
 * - Creación del usuario administrador con contraseña hasheada.
 * - Protección CSRF durante todo el proceso.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- CONFIGURACIÓN INICIAL Y SEGURIDAD ---

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$env_file = __DIR__ . '/.env';
$env_template_file = __DIR__ . '/.env.example';
$sql_install_file = __DIR__ . '/database/install.sql';
$step = isset($_SESSION['install_step']) ? (int)$_SESSION['install_step'] : 1;
$errors = [];
$config_data = $_SESSION['config_data'] ?? [];

// Si ya está instalado (existe .env), no mostrar el instalador.
if (file_exists($env_file)) {
    echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Instalador</title><link rel='stylesheet' href='assets/css/main.css'></head><body class='page-manage'><div class='admin-container'><div class='content'><div class='status-message success'>El portal ya parece estar instalado.</div><p>Si deseas reinstalar, por favor, elimina el archivo <code>.env</code> y recarga esta página.</p><a href='index.php' class='back-btn'>Ir a la página principal</a></div></div></div></body></html>";
    exit;
}

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- FUNCIONES AUXILIARES ---

function check_requirements(): array {
    $results = [];
    $results['php_version'] = version_compare(PHP_VERSION, '8.0.0', '>=');
    $results['pdo_mysql'] = extension_loaded('pdo_mysql');
    $results['openssl'] = extension_loaded('openssl');
    $results['mbstring'] = extension_loaded('mbstring');
    $results['is_writable'] = is_writable(__DIR__);
    $results['env_example_exists'] = file_exists(__DIR__ . '/.env.example');
    $results['sql_exists'] = file_exists(__DIR__ . '/database/install.sql');
    return $results;
}

function parse_sql_file(string $file_path): array {
    $content = file_get_contents($file_path);
    if ($content === false) return [];

    // Eliminar comentarios
    $content = preg_replace('!/\*.*?\*/!s', '', $content);
    $content = preg_replace('/^-- .*$/m', '', $content);
    $content = preg_replace('/^#.*$/m', '', $content);

    // Dividir por sentencias
    $statements = explode(';', $content);
    $statements = array_map('trim', $statements);
    return array_filter($statements);
}

$requirements = check_requirements();
$all_requirements_met = !in_array(false, $requirements, true);

if (!$all_requirements_met && $step > 1) {
    $step = 1; // Forzar volver al paso 1 si los requisitos no se cumplen
}

// --- PROCESAMIENTO DEL FORMULARIO ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Error de seguridad (CSRF). Por favor, recarga la página e inténtalo de nuevo.';
    } else {
        $current_step = (int)($_POST['step'] ?? 1);

        if ($current_step === 1 && $all_requirements_met) {
            $step = 2;
        } elseif ($current_step === 2) {
            $config_data['db_host'] = $_POST['db_host'] ?? 'localhost';
            $config_data['db_name'] = trim($_POST['db_name'] ?? '');
            $config_data['db_user'] = trim($_POST['db_user'] ?? '');
            $config_data['db_pass'] = $_POST['db_pass'] ?? '';

            if (empty($config_data['db_name']) || empty($config_data['db_user'])) {
                $errors[] = 'El nombre de la base de datos y el usuario son obligatorios.';
            } else {
                try {
                    $dsn = "mysql:host={$config_data['db_host']};charset=utf8mb4";
                    $pdo = new PDO($dsn, $config_data['db_user'], $config_data['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $_SESSION['config_data'] = $config_data;
                    $step = 3;
                } catch (PDOException $e) {
                    $errors[] = "No se pudo conectar al servidor MySQL. Error: " . htmlspecialchars($e->getMessage());
                }
            }
        } elseif ($current_step === 3) {
            $config_data['admin_user'] = trim($_POST['admin_user'] ?? '');
            $config_data['admin_pass'] = $_POST['admin_pass'] ?? '';
            $config_data['company_name'] = trim($_POST['company_name'] ?? '');

            if (empty($config_data['admin_user']) || empty($config_data['admin_pass']) || empty($config_data['company_name'])) {
                $errors[] = 'Todos los campos de esta sección son obligatorios.';
            } elseif (strlen($config_data['admin_pass']) < 8) {
                $errors[] = 'La contraseña del administrador debe tener al menos 8 caracteres.';
            } else {
                $_SESSION['config_data'] = $config_data;
                $step = 4; // Ir al paso final de instalación

                // --- PROCESO DE INSTALACIÓN ---
                try {
                    // 1. Conectar y crear BD
                    $dsn = "mysql:host={$config_data['db_host']};charset=utf8mb4";
                    $pdo = new PDO($dsn, $config_data['db_user'], $config_data['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config_data['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;");
                    $pdo->exec("USE `{$config_data['db_name']}`;");

                    // 2. Ejecutar script SQL
                    $sql_statements = parse_sql_file($sql_install_file);
                    foreach ($sql_statements as $statement) {
                        $pdo->exec($statement);
                    }

                    // 3. Crear usuario admin
                    $pass_hash = password_hash($config_data['admin_pass'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("REPLACE INTO `users` (id, username, pass_hash, role, full_name, is_active) VALUES (1, ?, ?, 'admin', 'Administrador', 1)");
                    $stmt->execute([$config_data['admin_user'], $pass_hash]);

                    // 4. Crear archivo .env
                    $env_content = file_get_contents($env_template_file);
                    $app_url = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                    $encryption_key = base64_encode(random_bytes(32));

                    $replacements = [
                        'APP_URL=' => 'APP_URL="' . $app_url . '"',
                        'APP_ENCRYPTION_KEY=' => 'APP_ENCRYPTION_KEY=' . $encryption_key,
                        'DB_HOST=' => 'DB_HOST=' . $config_data['db_host'],
                        'DB_NAME=' => 'DB_NAME=' . $config_data['db_name'],
                        'DB_USER=' => 'DB_USER=' . $config_data['db_user'],
                        'DB_PASS=' => 'DB_PASS="' . $config_data['db_pass'] . '"',
                    ];

                    foreach ($replacements as $search => $replace) {
                        $env_content = preg_replace("/^" . preg_quote($search, '/') . ".*$/m", $replace, $env_content);
                    }

                    if (file_put_contents($env_file, $env_content) === false) {
                        throw new Exception("No se pudo escribir el archivo <code>.env</code>. Verifique los permisos de escritura.");
                    }

                } catch (Exception $e) {
                    $errors[] = "Falló la instalación: " . $e->getMessage();
                    $step = 3; // Volver al paso anterior en caso de error
                }
            }
        }
    }
}

$_SESSION['install_step'] = $step;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación del Portal</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        .step-container { border: 1px solid var(--border-color); border-radius: var(--main-radius); margin-bottom: 1.5rem; }
        .step-header { background-color: #f8f9fa; padding: 1rem 1.5rem; font-weight: 600; border-bottom: 1px solid var(--border-color); }
        .step-header.active { background-color: var(--primary-color); color: var(--primary-text-color); }
        .step-header.completed { background-color: var(--success-color); color: var(--success-text-color); }
        .step-body { padding: 1.5rem; }
        .step-container[disabled] { opacity: 0.5; pointer-events: none; }
        .req-list { list-style: none; padding: 0; }
        .req-list li { margin-bottom: 0.5rem; }
        .req-list .icon { display: inline-block; width: 20px; text-align: center; margin-right: 10px; }
        .form-actions { text-align: right; }
        .final-message { text-align: center; padding: 2rem; }
        .final-message h2 { font-size: 2rem; color: var(--success-color); }
        .final-message .warning { color: var(--danger-color); font-weight: bold; border: 2px solid; padding: 1rem; margin-top: 1rem; border-radius: 8px; }
    </style>
</head>
<body class="page-manage">
    <div class="admin-container">
        <header class="admin-header">
            <h1>🚀 Instalación del Portal de Servicios</h1>
        </header>

        <div class="content">
            <?php if (!empty($errors)): ?>
                <div class="status-message error">
                    <strong>Se encontraron errores:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= $error ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($step < 4): ?>
            <form method="POST" action="install.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="step" value="<?= $step ?>">

                <!-- PASO 1: Requisitos -->
                <div class="step-container" <?= $step !== 1 ? 'disabled' : '' ?>>
                    <div class="step-header <?= $step === 1 ? 'active' : 'completed' ?>">Paso 1: Verificación de Requisitos</div>
                    <?php if ($step === 1): ?>
                    <div class="step-body">
                        <p>Comprobando si tu servidor cumple con los requisitos mínimos.</p>
                        <ul class="req-list">
                            <li><span class="icon"><?= $requirements['php_version'] ? '✅' : '❌' ?></span> PHP 8.0 o superior (Tu versión: <?= PHP_VERSION ?>)</li>
                            <li><span class="icon"><?= $requirements['pdo_mysql'] ? '✅' : '❌' ?></span> Extensión PHP: pdo_mysql</li>
                            <li><span class="icon"><?= $requirements['openssl'] ? '✅' : '❌' ?></span> Extensión PHP: openssl (para cifrado)</li>
                            <li><span class="icon"><?= $requirements['mbstring'] ? '✅' : '❌' ?></span> Extensión PHP: mbstring</li>
                            <li><span class="icon"><?= $requirements['is_writable'] ? '✅' : '❌' ?></span> Permisos de escritura en el directorio actual</li>
                            <li><span class="icon"><?= $requirements['env_example_exists'] ? '✅' : '❌' ?></span> Archivo <code>.env.example</code> existe</li>
                            <li><span class="icon"><?= $requirements['sql_exists'] ? '✅' : '❌' ?></span> Archivo <code>database/install.sql</code> existe</li>
                        </ul>
                        <?php if ($all_requirements_met): ?>
                            <div class="status-message success">¡Todo en orden! Puedes continuar.</div>
                            <div class="form-actions">
                                <button type="submit" class="save-btn">Continuar al Paso 2 →</button>
                            </div>
                        <?php else: ?>
                            <div class="status-message error">Debes corregir los errores (❌) para poder continuar.</div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- PASO 2: Base de Datos -->
                <div class="step-container" <?= $step !== 2 ? 'disabled' : '' ?>>
                    <div class="step-header <?= $step === 2 ? 'active' : ($step > 2 ? 'completed' : '') ?>">Paso 2: Configuración de la Base de Datos</div>
                    <?php if ($step === 2): ?>
                    <div class="step-body">
                        <div class="form-group">
                            <label for="db_host">Host de la Base de Datos</label>
                            <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($config_data['db_host'] ?? 'localhost') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="db_name">Nombre de la Base de Datos</label>
                            <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($config_data['db_name'] ?? '') ?>" placeholder="Ej: portal_db" required>
                        </div>
                        <div class="form-group">
                            <label for="db_user">Usuario de la Base de Datos</label>
                            <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($config_data['db_user'] ?? '') ?>" required>
                            <small>Este usuario debe tener permisos para crear bases de datos.</small>
                        </div>
                        <div class="form-group">
                            <label for="db_pass">Contraseña de la Base de Datos</label>
                            <input type="password" id="db_pass" name="db_pass" autocomplete="new-password">
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="save-btn">Verificar y Continuar →</button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- PASO 3: Configuración del Sitio -->
                <div class="step-container" <?= $step !== 3 ? 'disabled' : '' ?>>
                    <div class="step-header <?= $step === 3 ? 'active' : '' ?>">Paso 3: Configuración del Sitio y Administrador</div>
                    <?php if ($step === 3): ?>
                    <div class="step-body">
                        <div class="form-group">
                            <label for="company_name">Nombre de la Compañía / Portal</label>
                            <input type="text" id="company_name" name="company_name" value="<?= htmlspecialchars($config_data['company_name'] ?? '') ?>" placeholder="Ej: Mi Empresa S.A." required>
                        </div>
                        <div class="form-group">
                            <label for="admin_user">Nombre de Usuario Administrador</label>
                            <input type="text" id="admin_user" name="admin_user" value="<?= htmlspecialchars($config_data['admin_user'] ?? 'admin') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="admin_pass">Contraseña (mín. 8 caracteres)</label>
                            <input type="password" id="admin_pass" name="admin_pass" required minlength="8" autocomplete="new-password">
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="save-btn">✨ ¡Instalar Ahora! ✨</button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
            <?php else: ?>
                <!-- PASO 4: Finalización -->
                <div class="final-message">
                    <h2>🎉 ¡Instalación Completada! 🎉</h2>
                    <p>Tu portal ha sido configurado correctamente.</p>
                    <p class="warning">
                        Por razones de seguridad, es <strong>MUY IMPORTANTE</strong> que elimines el archivo <code>install.php</code> de tu servidor ahora mismo.
                    </p>
                    <a href="index.php" class="save-btn" style="display: inline-block; text-decoration: none; margin-top: 1rem;">Ir al Portal</a>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <footer class="footer">
        <strong>Portal de Servicios - Instalador</strong>
    </footer>
</body>
</html>
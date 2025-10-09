<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

$config_file = 'config.php';
$config_template_file = 'config.example.php';
$sql_install_file = 'db/install.sql'; // Ruta al script SQL maestro
$message = '';
$step = 1;

// Si ya está instalado, no mostrar el instalador.
if (file_exists($config_file)) {
    echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Instalador</title><link rel='stylesheet' href='assets/css/main.css'></head><body class='page-manage'><div class='admin-container'><div class='content'><div class='status-message success'>El portal ya parece estar instalado.</div><p>Si deseas reinstalar, por favor, elimina el archivo <code>config.php</code> y recarga esta página.</p><a href='index.php' class='back-btn'>Ir a la página principal</a></div></div></body></html>";
    exit;
}

// Verificar que la plantilla de config exista.
if (!file_exists($config_template_file)) {
    die("Error Crítico: El archivo <code>{$config_template_file}</code> no se encuentra. No se puede continuar con la instalación.");
}

// Verificar que el script SQL de instalación exista.
if (!file_exists($sql_install_file)) {
    die("Error Crítico: El archivo de instalación de la base de datos <code>{$sql_install_file}</code> no se encuentra. No se puede continuar.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_name = $_POST['db_name'] ?? '';
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';
    $admin_user = $_POST['admin_user'] ?? '';
    $admin_pass = $_POST['admin_pass'] ?? '';
    $admin_pass_confirm = $_POST['admin_pass_confirm'] ?? '';
    $company_name = trim($_POST['company_name'] ?? '');
    $footer_line2 = trim($_POST['footer_line2'] ?? '');
    $whatsapp_number = trim($_POST['whatsapp_number'] ?? '');

    // --- Validaciones ---
    if (empty($db_name) || empty($db_user) || empty($admin_user) || empty($admin_pass)) {
        $message = '<div class="status-message error">Todos los campos son obligatorios.</div>';
    } elseif ($admin_pass !== $admin_pass_confirm) {
        $message = '<div class="status-message error">Las contraseñas del administrador no coinciden.</div>';
    } elseif (strlen($admin_pass) < 8) {
        $message = '<div class="status-message error">La contraseña del administrador debe tener al menos 8 caracteres.</div>';
    } elseif (empty($company_name)) {
        $message = '<div class="status-message error">El nombre de la compañía es obligatorio.</div>';
    } else {
        try {
            // 1. Intentar conectar al servidor MySQL
            $dsn_server = "mysql:host={$db_host};charset=utf8mb4";
            $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
            $pdo = new PDO($dsn_server, $db_user, $db_pass, $options);

            // 2. Crear la base de datos si no existe
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            $pdo->exec("USE `{$db_name}`;");

            // 3. Ejecutar el script SQL maestro (db/install.sql)
            $sql_script = file_get_contents($sql_install_file);
            if ($sql_script === false) {
                throw new Exception("No se pudo leer el archivo de instalación SQL.");
            }
            // Eliminar la creación de la base de datos y el USE del script, ya que los manejamos dinámicamente.
            $sql_script = preg_replace('/CREATE DATABASE IF NOT EXISTS `.*?`;/is', '', $sql_script);
            $sql_script = preg_replace('/USE `.*?`;/is', '', $sql_script);
            
            // Ejecutar el script completo. PDO::exec puede manejar múltiples sentencias.
            $pdo->exec($sql_script);

            // 4. Insertar/reemplazar el usuario administrador con la contraseña segura (bcrypt) del formulario.
            // Esto asegura que el usuario del formulario sea el que funcione, sobreescribiendo
            // cualquier usuario por defecto que el script SQL pudiera haber creado.
            $pass_hash = password_hash($admin_pass, PASSWORD_DEFAULT);
            $admin_email = 'admin@' . preg_replace('/[^a-zA-Z0-9.-]/', '', strtolower($company_name)) . '.local';
            $stmt = $pdo->prepare("REPLACE INTO `users` (id, username, pass_hash, role, full_name, email, is_active) VALUES (1, ?, ?, 'admin', 'Administrador Principal', ?, 1)");
            $stmt->execute([$admin_user, $pass_hash, $admin_email]);

            // 5. Crear el archivo config.php
            $config_template = require $config_template_file;
            $config_template['database']['host'] = $db_host;
            $config_template['database']['name'] = $db_name;
            $config_template['database']['user'] = $db_user;
            $config_template['database']['pass'] = $db_pass;

            // Rellenar los datos de personalización
            $config_template['landing_page']['company_name'] = $company_name;
            $config_template['footer']['line1'] = $company_name;
            $config_template['footer']['line2'] = $footer_line2;
            $config_template['footer']['whatsapp_number'] = $whatsapp_number;

            $new_config_content = "<?php\n\nreturn " . var_export($config_template, true) . ";\n";

            if (file_put_contents($config_file, $new_config_content)) {
                $step = 2; // Ir al paso de éxito
            } else {
                throw new Exception("No se pudo escribir el archivo <code>{$config_file}</code>. Verifique los permisos de escritura en el directorio.");
            }

        } catch (PDOException $e) {
            $message = "<div class='status-message error'>Error de base de datos: " . htmlspecialchars($e->getMessage()) . "</div>";
        } catch (Exception $e) {
            $message = "<div class='status-message error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación del Portal</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="page-manage">
    <div class="admin-container">
        <header class="admin-header">
            <h1>🚀 Instalación del Portal de Servicios</h1>
        </header>

        <div class="content">
            <?= $message ?>

            <?php if ($step === 1): ?>
            <div class="status-message warning">
                <strong>¡Atención!</strong> Para continuar, necesitas un usuario de MySQL con privilegios para crear bases de datos (permiso <code>CREATE</code>).
            </div>
            <p>Bienvenido. Este asistente te guiará para configurar tu nuevo portal. Por favor, completa los siguientes campos.</p>

            <form method="POST" action="install.php">
                <!-- Sección de Base de Datos -->
                <div class="section">
                    <div class="section-header active">1. Configuración de la Base de Datos</div>
                    <div class="section-body" style="max-height: 500px;">
                        <div class="section-body-inner">
                            <div class="form-group">
                                <label for="db_host">Host de la Base de Datos</label>
                                <input type="text" id="db_host" name="db_host" value="localhost" required>
                            </div>
                            <div class="form-group">
                                <label for="db_name">Nombre de la Base de Datos</label>
                                <input type="text" id="db_name" name="db_name" placeholder="Ej: portal_db" required>
                            </div>
                            <div class="form-group">
                                <label for="db_user">Usuario de la Base de Datos</label>
                                <input type="text" id="db_user" name="db_user" required>
                            </div>
                            <div class="form-group">
                                <label for="db_pass">Contraseña de la Base de Datos</label>
                                <input type="password" id="db_pass" name="db_pass" autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sección de Usuario Administrador -->
                <div class="section">
                    <div class="section-header active">2. Creación del Usuario Administrador</div>
                    <div class="section-body" style="max-height: 500px;">
                        <div class="section-body-inner">
                            <div class="form-group">
                                <label for="admin_user">Nombre de Usuario Administrador</label>
                                <input type="text" id="admin_user" name="admin_user" required>
                            </div>
                            <div class="form-group">
                                <label for="admin_pass">Contraseña (mín. 8 caracteres)</label>
                                <input type="password" id="admin_pass" name="admin_pass" required minlength="8" autocomplete="new-password">
                            </div>
                            <div class="form-group">
                                <label for="admin_pass_confirm">Confirmar Contraseña</label>
                                <input type="password" id="admin_pass_confirm" name="admin_pass_confirm" required minlength="8" autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sección de Personalización -->
                <div class="section">
                    <div class="section-header active">3. Personalización del Portal</div>
                    <div class="section-body" style="max-height: 500px;">
                        <div class="section-body-inner">
                            <p>Esta información se usará en los títulos y el pie de página.</p>
                            <div class="form-group">
                                <label for="company_name">Nombre de la Compañía / Portal</label>
                                <input type="text" id="company_name" name="company_name" placeholder="Ej: Mi Empresa S.A." required>
                            </div>
                            <div class="form-group">
                                <label for="footer_line2">Línea 2 del Pie de Página</label>
                                <input type="text" id="footer_line2" name="footer_line2" placeholder="Ej: Contacto y Soporte Técnico">
                            </div>
                            <div class="form-group">
                                <label for="whatsapp_number">Número de WhatsApp (Opcional)</label>
                                <input type="text" id="whatsapp_number" name="whatsapp_number" placeholder="Ej: 5491112345678">
                            </div>
                        </div>
                    </div>
                </div>


                <div class="form-actions">
                    <button type="submit" class="save-btn">Instalar</button>
                </div>
            </form>

            <?php elseif ($step === 2): ?>
            <div class="status-message success">¡Instalación completada con éxito!</div>
            <div class="section">
                <div class="section-header active">Pasos Finales</div>
                <div class="section-body" style="max-height: 500px;">
                    <div class="section-body-inner">
                        <p>Tu portal ha sido configurado correctamente.</p>
                        <p style="color: red; font-weight: bold;">
                            Por razones de seguridad, es MUY IMPORTANTE que elimines el archivo <code>install.php</code> de tu servidor ahora mismo.
                        </p>
                        <a href="index2.php" class="save-btn" style="display: inline-block; text-decoration: none; margin-top: 1rem;">Ir al Portal</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <footer class="footer">
        <strong>Portal de Servicios - Instalador</strong>
    </footer>
</body>
</html>
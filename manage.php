<?php
/**
 * manage.php - Panel de Administración del Sitio
 */

require_once 'bootstrap.php';

// ============================================================================
// CONTROL DE ACCESO
// ============================================================================

if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    log_security_event('unauthorized_manage_access', 'Intento de acceso a manage.php sin permisos');
    header('Location: index2.php');
    exit;
}

// Rate limiting
if (!check_rate_limit('manage_access', 30, 60)) { // Aumentado a 30 solicitudes por minuto
    http_response_code(429);
    die('Demasiadas solicitudes. Espera un momento.');
}

$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");

$status_message = '';

// ============================================================================
// FUNCIONES DE VALIDACIÓN
// ============================================================================

/**
 * Valida que una cadena tenga una longitud permitida
 */
function validate_string_length($value, $min, $max, $field_name) {
    $len = mb_strlen($value);
    if ($len < $min || $len > $max) {
        return "El campo '{$field_name}' debe tener entre {$min} y {$max} caracteres (actual: {$len})";
    }
    return null;
}

/**
 * Valida una URL
 */
function validate_url($url, $field_name, $allow_relative = false) {
    if (empty($url)) return null;
    
    // Bloquear esquemas peligrosos
    $dangerous_schemes = ['javascript:', 'data:', 'vbscript:', 'file:'];
    foreach ($dangerous_schemes as $scheme) {
        if (stripos($url, $scheme) === 0) {
            return "El campo '{$field_name}' contiene un esquema de URL no permitido";
        }
    }
    
    // Si es relativa y está permitido, aceptar
    if ($allow_relative && !preg_match('/^https?:\/\//', $url)) {
        return null;
    }
    
    // Validar URL completa
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return "El campo '{$field_name}' no es una URL válida";
    }
    
    return null;
}

/**
 * Valida un número de teléfono
 */
function validate_phone($phone, $field_name) {
    // Remover espacios, guiones, paréntesis
    $clean = preg_replace('/[\s\-\(\)]/', '', $phone);
    
    // Debe tener entre 8 y 15 dígitos (puede incluir +)
    if (!preg_match('/^\+?\d{8,15}$/', $clean)) {
        return "El campo '{$field_name}' no es un teléfono válido";
    }
    
    return null;
}

/**
 * Valida un SVG path
 */
function validate_svg_path($path, $field_name) {
    if (empty($path)) return null;
    
    // Bloquear eventos JS
    $dangerous_patterns = [
        '/on\w+\s*=/i',           // onclick, onload, etc.
        '/<script/i',             // <script>
        '/javascript:/i',         // javascript:
        '/data:text\/html/i',     // data URLs
    ];
    
    foreach ($dangerous_patterns as $pattern) {
        if (preg_match($pattern, $path)) {
            return "El campo '{$field_name}' contiene código potencialmente peligroso";
        }
    }
    
    // Validar que parezca un path SVG válido
    if (!preg_match('/^[MmLlHhVvCcSsQqTtAaZz0-9\s,\.\-]+$/', $path)) {
        return "El campo '{$field_name}' no parece un path SVG válido";
    }
    
    return null;
}

/**
 * Sanitiza una cadena para usar en var_export
 */
function safe_var_export($var, $indent = '') {
    if (is_array($var)) {
        $output = "[\n";
        foreach ($var as $key => $value) {
            $output .= $indent . '    ' . var_export($key, true) . ' => ';
            $output .= safe_var_export($value, $indent . '    ') . ",\n";
        }
        $output .= $indent . ']';
        return $output;
    } elseif (is_string($var)) {
        // Escapar caracteres especiales
        return "'" . addslashes($var) . "'";
    } else {
        return var_export($var, true);
    }
}

/**
 * Crea un backup con timestamp
 */
function create_backup($file) {
    $timestamp = date('Y-m-d_H-i-s');
    $backup_file = $file . '.backup_' . $timestamp;
    
    if (copy($file, $backup_file)) {
        // Mantener solo los últimos 10 backups
        $backups = glob($file . '.backup_*');
        rsort($backups);
        
        foreach (array_slice($backups, 10) as $old_backup) {
            @unlink($old_backup);
        }
        
        return $backup_file;
    }
    
    return false;
}

// ============================================================================
// PROCESAMIENTO DEL FORMULARIO
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        validate_request_csrf();
        $pdo = get_database_connection($config, true);
        $validation_errors = [];
        
        // ================================================================
        // PROCESAR SECCIONES DEL config.php
        // ================================================================
        $new_config = $config; // Start with current config

        // Eliminar la sección 'services' del array de config para que no se guarde en el archivo.
        // Esta sección ahora se gestiona 100% en la base de datos.
        unset($new_config['services']);

        // Ajustes Generales
        $new_config['landing_page']['company_name'] = trim($_POST['company_name'] ?? '');

        // Títulos de Página Principal
        $new_config['landing_page']['sales_title'] = trim($_POST['sales_title'] ?? '');
        $new_config['landing_page']['locations_title'] = trim($_POST['locations_title'] ?? '');
        $new_config['landing_page']['social_title'] = trim($_POST['social_title'] ?? '');
        $new_config['landing_page']['main_sites_title'] = trim($_POST['main_sites_title'] ?? '');

        // Teléfonos
        $new_config['landing_page']['phone_numbers'] = [];
        if (isset($_POST['phone_numbers']) && is_array($_POST['phone_numbers'])) {
            foreach ($_POST['phone_numbers'] as $phone) {
                if (!empty(trim($phone))) $new_config['landing_page']['phone_numbers'][] = trim($phone);
            }
        }

        // Sucursales
        $new_config['landing_page']['branches'] = [];
        if (isset($_POST['branches']) && is_array($_POST['branches'])) {
            foreach ($_POST['branches'] as $branch) {
                if (!empty(trim($branch))) $new_config['landing_page']['branches'][] = trim($branch);
            }
        }

        // Redes Sociales
        $new_config['landing_page']['social_links'] = [];
        if (isset($_POST['social_links']) && is_array($_POST['social_links'])) {
            foreach ($_POST['social_links'] as $id => $link_data) {
                if (!empty(trim($id))) {
                    $new_config['landing_page']['social_links'][trim($id)] = [
                        'label' => trim($link_data['label'] ?? ''),
                        'url' => trim($link_data['url'] ?? ''),
                        'svg_path' => trim($link_data['svg_path'] ?? ''),
                    ];
                }
            }
        }

        // Sitios Principales
        if (isset($_POST['main_sites']) && is_array($_POST['main_sites'])) {
            foreach ($_POST['main_sites'] as $key => $url) {
                if (isset($new_config['landing_page']['main_sites'][$key])) {
                    $new_config['landing_page']['main_sites'][$key]['url'] = trim($url);
                }
            }
        }

        // Footer
        $new_config['footer']['line1'] = trim($_POST['footer_line1'] ?? '');
        $new_config['footer']['line2'] = trim($_POST['footer_line2'] ?? '');
        $new_config['footer']['license_url'] = trim($_POST['footer_license_url'] ?? '');
        $new_config['footer']['whatsapp_number'] = trim($_POST['footer_whatsapp_number'] ?? '');
        $new_config['footer']['whatsapp_svg_path'] = trim($_POST['footer_whatsapp_svg_path'] ?? '');

        // Guardar el archivo config.php
        $config_file = __DIR__ . '/config.php';
        if (!is_writable($config_file)) {
            throw new Exception('El archivo de configuración no es escribible. Verifica los permisos.');
        }

        $new_config_content = "<?php\n" .
            "/**\n * config.php - Configuración Central\n * Generado automáticamente por manage.php\n * Fecha: " . date('Y-m-d H:i:s') . "\n * Usuario: " . ($_SESSION['username'] ?? 'system') . "\n */\n\n" .
            "return " . safe_var_export($new_config) . ";\n";

        if (!file_put_contents($config_file, $new_config_content, LOCK_EX)) {
            throw new Exception('No se pudo escribir en el archivo de configuración.');
        }

        // Recargar la configuración para la sesión actual
        $config = require $config_file;
        log_security_event('config_updated', "Usuario {$_SESSION['username']} actualizó la configuración del sitio");

        // ================================================================
        // PROCESAR SERVICIOS (Base de Datos)
        // ================================================================
        if (isset($_POST['services']) && is_array($_POST['services'])) {
            $submitted_ids = [];
            foreach ($_POST['services'] as $key => $service_data) {
                $service_id = trim($service_data['id'] ?? $key);
                if (empty($service_id)) continue;

                $label = trim($service_data['label'] ?? '');
                $url = trim($service_data['url'] ?? '');
                $category = trim($service_data['category'] ?? 'Otros Servicios');

                if (empty($label) || empty($url) || empty($category)) continue;

                $is_new = strpos($service_id, 'new_') === 0;

                if ($is_new) {
                    $stmt = $pdo->prepare("INSERT INTO services (service_key, label, url, category, requires_login, redirect) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        'svc_' . uniqid(),
                        $label,
                        $url,
                        $category,
                        isset($service_data['requires_login']) ? 1 : 0,
                        isset($service_data['redirect']) ? 1 : 0
                    ]);
                    $submitted_ids[] = $pdo->lastInsertId();
                } else {
                    $stmt = $pdo->prepare("UPDATE services SET label=?, url=?, category=?, requires_login=?, redirect=? WHERE id=?");
                    $stmt->execute([
                        $label,
                        $url,
                        $category,
                        isset($service_data['requires_login']) ? 1 : 0,
                        isset($service_data['redirect']) ? 1 : 0,
                        $service_id
                    ]);
                    $submitted_ids[] = $service_id;
                }
            }

            // Eliminar servicios que no fueron enviados
            $stmt_current = $pdo->query("SELECT id FROM services");
            $current_ids = $stmt_current->fetchAll(PDO::FETCH_COLUMN);
            $ids_to_delete = array_diff($current_ids, $submitted_ids);

            if (!empty($ids_to_delete)) {
                // ¡CORRECCIÓN CLAVE! Asegurarse de que los valores son enteros
                $ids_to_delete = array_map('intval', $ids_to_delete);
                $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
                $stmt_del = $pdo->prepare("DELETE FROM services WHERE id IN ($placeholders)");
                $stmt_del->execute(array_values($ids_to_delete));
            }
        }

        $status_message = '<div class="status-message success">✅ ¡Configuración guardada con éxito!</div>';

    } catch (Exception $e) {
        $status_message = '<div class="status-message error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        log_security_event('manage_save_failed', 'Error en manage.php: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($validation_errors)) {
    // No hacer nada aquí, la lógica ya se ejecutó arriba.
    // Esto previene la ejecución duplicada.
} else if (!empty($validation_errors)) {
    $status_message = '<div class="status-message error">';
    $status_message .= '<strong>❌ Se encontraron los siguientes errores:</strong><ul>';
    foreach ($validation_errors as $error) {
        $status_message .= '<li>' . htmlspecialchars($error) . '</li>';
    }
    $status_message .= '</ul></div>';
}

?>
<?php
$pdo = get_database_connection($config, true);
if (!$pdo) {
    die("Error fatal: No se pudo conectar a la base de datos.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚙️ Panel de Administración - <?= htmlspecialchars($config['landing_page']['company_name'] ?? 'Portal') ?></title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/manage.css">
</head>
<body class="page-manage">
    <div class="admin-container">
        <header class="admin-header">
            <h1>⚙️ Panel de Administración</h1>
            <p>Usuario: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong> | Rol: <strong><?= htmlspecialchars($_SESSION['user_role']) ?></strong></p>
        </header>

        <div class="content">
            <?= $status_message ?>

            <form method="POST" action="manage.php" id="configForm">
                <!-- Sección de Ajustes Generales -->
                <div class="section">
                    <?= csrf_field() ?>
                    <div class="section-header">Ajustes Generales</div>
                    <div class="section-body">
                        <div class="section-body-inner">
                            <div class="form-group">
                                <label for="company_name">Nombre del Servidor / Compañía</label>
                                <input type="text" id="company_name" name="company_name" value="<?= htmlspecialchars($config['landing_page']['company_name'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sección de Página Principal -->
                <div class="section">
                    <div class="section-header">Contenido de la Página Principal (index.php)</div>
                    <div class="section-body">
                        <div class="section-body-inner">
                            <div class="form-group">
                                <label for="sales_title">Título de Ventas/Contacto</label>
                                <input type="text" id="sales_title" name="sales_title" value="<?= htmlspecialchars($config['landing_page']['sales_title'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="locations_title">Título de Sucursales</label>
                                <input type="text" id="locations_title" name="locations_title" value="<?= htmlspecialchars($config['landing_page']['locations_title'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label for="social_title">Título de Redes Sociales</label>
                                <input type="text" id="social_title" name="social_title" value="<?= htmlspecialchars($config['landing_page']['social_title'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label for="main_sites_title">Título de Sitios Principales</label>
                                <input type="text" id="main_sites_title" name="main_sites_title" value="<?= htmlspecialchars($config['landing_page']['main_sites_title'] ?? '') ?>">
                            </div>

                            <!-- Teléfonos (lista dinámica) -->
                            <div class="form-group repeatable-list" id="phone-list">
                                <label>Teléfonos</label>
                                <?php foreach ($config['landing_page']['phone_numbers'] as $phone): ?>
                                <div class="repeatable-item">
                                    <input type="text" name="phone_numbers[]" value="<?= htmlspecialchars($phone) ?>" placeholder="Ej: (011) 1234-5678">
                                    <button type="button" class="delete-item-btn">Eliminar</button>
                                </div>
                                <?php endforeach; ?>
                                <button type="button" class="add-item-btn" data-target="phone-list" data-name="phone_numbers[]" data-placeholder="Nuevo teléfono">Añadir Teléfono</button>
                            </div>

                            <!-- Sucursales (lista dinámica) -->
                            <div class="form-group repeatable-list" id="branch-list">
                                <label>Sucursales</label>
                                <?php foreach ($config['landing_page']['branches'] as $branch): ?>
                                <div class="repeatable-item">
                                    <input type="text" name="branches[]" value="<?= htmlspecialchars($branch) ?>" placeholder="Ej: Calle Falsa 123 - Ciudad">
                                    <button type="button" class="delete-item-btn">Eliminar</button>
                                </div>
                                <?php endforeach; ?>
                                <button type="button" class="add-item-btn" data-target="branch-list" data-name="branches[]" data-placeholder="Nueva sucursal">Añadir Sucursal</button>
                            </div>

                            <!-- Redes Sociales (dinámico) -->
                            <div class="form-group">
                                <label>Redes Sociales</label>
                                <div class="table-container">
                                    <table id="social-links-table">
                                        <thead>
                                            <tr>
                                                <th>ID (ej: facebook)</th>
                                                <th>Etiqueta (ej: Facebook)</th>
                                                <th>URL</th>
                                                <th>Icono SVG (path)</th>
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($config['landing_page']['social_links'] as $id => $link): ?>
                                            <tr>
                                                <td><input type="text" name="social_links[<?= $id ?>][id]" value="<?= htmlspecialchars($id) ?>" readonly class="readonly-id"></td>
                                                <td><input type="text" name="social_links[<?= $id ?>][label]" value="<?= htmlspecialchars($link['label']) ?>" required></td>
                                                <td><input type="text" name="social_links[<?= $id ?>][url]" value="<?= htmlspecialchars($link['url']) ?>" required></td>
                                                <td><textarea name="social_links[<?= $id ?>][svg_path]" rows="2"><?= htmlspecialchars($link['svg_path']) ?></textarea></td>
                                                <td><button type="button" class="delete-item-btn">Eliminar</button></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="button" id="add-social-link-btn" class="add-btn">Añadir Red Social</button>
                            </div>

                            <!-- Sitios Principales -->
                            <div class="form-group">
                                <label>Sitios Principales (URLs)</label>
                                <?php foreach ($config['landing_page']['main_sites'] as $key => $site): ?>
                                <div class="form-group-inline">
                                    <label for="link_<?= $key ?>"><?= htmlspecialchars($site['label']) ?></label>
                                    <input type="text" id="link_<?= $key ?>" name="main_sites[<?= $key ?>]" value="<?= htmlspecialchars($site['url']) ?>">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sección de Pie de Página -->
                <div class="section">
                    <div class="section-header">Contenido del Pie de Página (Global)</div>
                    <div class="section-body">
                        <div class="section-body-inner">
                            <div class="form-group">
                                <label for="footer_line1">Pie de Página - Línea 1</label>
                                <input type="text" id="footer_line1" name="footer_line1" value="<?= htmlspecialchars($config['footer']['line1'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="footer_line2">Pie de Página - Línea 2 (Texto de contacto)</label>
                                <input type="text" id="footer_line2" name="footer_line2" value="<?= htmlspecialchars($config['footer']['line2'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="footer_whatsapp_number">Número de WhatsApp (con código de país)</label>
                                <input type="text" id="footer_whatsapp_number" name="footer_whatsapp_number" value="<?= htmlspecialchars($config['footer']['whatsapp_number'] ?? '') ?>" placeholder="Ej: 5491112345678">
                            </div>
                            <div class="form-group">
                                <label for="footer_license_url">URL de Licencia/Términos</label>
                                <input type="text" id="footer_license_url" name="footer_license_url" value="<?= htmlspecialchars($config['footer']['license_url'] ?? '') ?>" placeholder="https://... o license.php">
                            </div>
                            <div class="form-group">
                                <label for="footer_whatsapp_svg_path">Icono SVG para WhatsApp (solo el atributo 'd' de la etiqueta path)</label>
                                <textarea id="footer_whatsapp_svg_path" name="footer_whatsapp_svg_path" rows="4"><?= htmlspecialchars($config['footer']['whatsapp_svg_path'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sección de Botones de Servicio -->
                <div class="section">
                    <div class="section-header">Botones de Servicios (Portal Interno)</div>
                    <div class="section-body">
                        <div class="section-body-inner">
                            <div class="table-container">
                                <table id="services-table">
                                    <thead>
                                        <tr>
                                            <th>ID del Botón</th>
                                            <th>Etiqueta (Texto)</th>
                                            <th>URL de Destino</th>
                                            <th>Categoría</th>
                                            <th>Login?</th>
                                            <th>Redir?</th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $db_services = $pdo->query("SELECT * FROM services ORDER BY category, sort_order")->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($db_services as $service): 
                                        ?>
                                        <tr>
                                            <td><input type="text" name="services[<?= $service['id'] ?>][id]" value="<?= htmlspecialchars($service['id']) ?>" readonly class="readonly-id"></td>
                                            <td><input type="text" name="services[<?= $service['id'] ?>][label]" value="<?= htmlspecialchars($service['label']) ?>" required></td>
                                            <td><input type="text" name="services[<?= $service['id'] ?>][url]" value="<?= htmlspecialchars($service['url']) ?>" required></td>
                                            <td><input type="text" name="services[<?= $service['id'] ?>][category]" value="<?= htmlspecialchars($service['category'] ?? '') ?>" placeholder="Ej: Accesos LAN"></td>
                                            <td class="checkbox-cell"><input type="checkbox" name="services[<?= $service['id'] ?>][requires_login]" value="1" <?= !empty($service['requires_login']) ? 'checked' : '' ?>></td>
                                            <td class="checkbox-cell"><input type="checkbox" name="services[<?= $service['id'] ?>][redirect]" value="1" <?= !empty($service['redirect']) ? 'checked' : '' ?>></td>
                                            <td><button type="button" class="delete-service-btn">Eliminar</button></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" id="add-service-btn" class="add-btn">Añadir Nuevo Servicio</button>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" id="saveConfigBtn" class="save-btn">
                        💾 Guardar Cambios
                    </button>
                    <a href="index2.php" class="cancel-btn">❌ Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <a href="index2.php" class="back-btn">← Volver al Portal</a>

    <?php require_once 'templates/footer.php'; ?>

    <script src="assets/js/manage.js" nonce="<?= htmlspecialchars($nonce) ?>"></script>
</body>
</html>
<?php
/**
 * manage.php - Panel de Administración del Sitio
 * 
 * Permite a los administradores configurar todos los aspectos del portal.
 * 
 * SEGURIDAD:
 * - Solo accesible por admins
 * - CSRF protection
 * - Validación estricta de todos los inputs
 * - Backups múltiples con timestamps
 * - Logging completo de cambios
 * - Rollback automático en caso de error
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
if (!check_rate_limit('manage_access', 10, 60)) {
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
        // Crear copia profunda del config para modificar
        $new_config = $config;
        
        // ================================================================
        // VALIDAR Y PROCESAR: Ajustes Generales
        // ================================================================
        
        $company_name = trim($_POST['company_name'] ?? '');
        if ($error = validate_string_length($company_name, 3, 100, 'Nombre de Empresa')) {
            $validation_errors[] = $error;
        } else {
            $new_config['landing_page']['company_name'] = $company_name;
        }
        
        // ================================================================
        // VALIDAR Y PROCESAR: Página Principal
        // ================================================================
        
        // Títulos
        $titles = [
            'sales_title' => 'Título de Ventas',
            'locations_title' => 'Título de Sucursales',
            'social_title' => 'Título de Redes Sociales',
            'main_sites_title' => 'Título de Sitios Principales'
        ];
        
        foreach ($titles as $key => $label) {
            $value = trim($_POST[$key] ?? '');
            if ($error = validate_string_length($value, 3, 50, $label)) {
                $validation_errors[] = $error;
            } else {
                $new_config['landing_page'][$key] = $value;
            }
        }
        
        // Teléfonos
        $new_config['landing_page']['phone_numbers'] = [];
        if (isset($_POST['phone_numbers']) && is_array($_POST['phone_numbers'])) {
            foreach ($_POST['phone_numbers'] as $phone) {
                $phone = trim($phone);
                if (empty($phone)) continue;
                
                if (count($new_config['landing_page']['phone_numbers']) >= 10) {
                    $validation_errors[] = "Máximo 10 teléfonos permitidos";
                    break;
                }
                
                if ($error = validate_phone($phone, 'Teléfono')) {
                    $validation_errors[] = $error;
                } else {
                    $new_config['landing_page']['phone_numbers'][] = $phone;
                }
            }
        }
        
        // Sucursales
        $new_config['landing_page']['branches'] = [];
        if (isset($_POST['branches']) && is_array($_POST['branches'])) {
            foreach ($_POST['branches'] as $branch) {
                $branch = trim($branch);
                if (empty($branch)) continue;
                
                if (count($new_config['landing_page']['branches']) >= 20) {
                    $validation_errors[] = "Máximo 20 sucursales permitidas";
                    break;
                }
                
                if ($error = validate_string_length($branch, 5, 200, 'Sucursal')) {
                    $validation_errors[] = $error;
                } else {
                    $new_config['landing_page']['branches'][] = $branch;
                }
            }
        }
        
        // Redes Sociales
        $new_config['landing_page']['social_links'] = [];
        if (isset($_POST['social_links']) && is_array($_POST['social_links'])) {
            foreach ($_POST['social_links'] as $link_data) {
                $id = trim($link_data['id'] ?? '');
                if (empty($id)) continue;
                
                if (count($new_config['landing_page']['social_links']) >= 10) {
                    $validation_errors[] = "Máximo 10 redes sociales permitidas";
                    break;
                }
                
                // Validar ID
                if (!preg_match('/^[a-z0-9_]+$/', $id)) {
                    $validation_errors[] = "ID de red social '{$id}' inválido (solo minúsculas, números y guiones bajos)";
                    continue;
                }
                
                $label = trim($link_data['label'] ?? '');
                $url = trim($link_data['url'] ?? '');
                $svg_path = trim($link_data['svg_path'] ?? '');
                
                // Validaciones
                if ($error = validate_string_length($label, 2, 30, "Etiqueta de '{$id}'")) {
                    $validation_errors[] = $error;
                    continue;
                }
                
                if ($error = validate_url($url, "URL de '{$id}'", false)) {
                    $validation_errors[] = $error;
                    continue;
                }
                
                if ($error = validate_svg_path($svg_path, "SVG path de '{$id}'")) {
                    $validation_errors[] = $error;
                    continue;
                }
                
                $new_config['landing_page']['social_links'][$id] = [
                    'label' => $label,
                    'url' => $url,
                    'svg_path' => $svg_path,
                ];
            }
        }
        
        // Sitios Principales
        if (isset($_POST['main_sites']) && is_array($_POST['main_sites'])) {
            foreach ($_POST['main_sites'] as $key => $url) {
                if (isset($new_config['landing_page']['main_sites'][$key])) {
                    $url = trim($url);
                    
                    if ($error = validate_url($url, "URL de '{$key}'", true)) {
                        $validation_errors[] = $error;
                    } else {
                        $new_config['landing_page']['main_sites'][$key]['url'] = $url;
                    }
                }
            }
        }
        
        // ================================================================
        // VALIDAR Y PROCESAR: Footer
        // ================================================================
        
        $footer_fields = [
            'footer_line1' => ['label' => 'Línea 1 del footer', 'min' => 5, 'max' => 200],
            'footer_line2' => ['label' => 'Línea 2 del footer', 'min' => 5, 'max' => 200],
            'footer_license_url' => ['label' => 'URL de licencia', 'min' => 0, 'max' => 500],
        ];
        
        foreach ($footer_fields as $key => $rules) {
            $field_key = str_replace('footer_', '', $key);
            $value = trim($_POST[$key] ?? '');
            
            if ($rules['min'] > 0 && empty($value)) {
                $validation_errors[] = "{$rules['label']} es obligatorio";
                continue;
            }
            
            if (!empty($value)) {
                if ($error = validate_string_length($value, $rules['min'], $rules['max'], $rules['label'])) {
                    $validation_errors[] = $error;
                    continue;
                }
            }
            
            $new_config['footer'][$field_key] = $value;
        }
        
        // WhatsApp
        $whatsapp = trim($_POST['footer_whatsapp_number'] ?? '');
        if (!empty($whatsapp)) {
            if ($error = validate_phone($whatsapp, 'WhatsApp')) {
                $validation_errors[] = $error;
            } else {
                $new_config['footer']['whatsapp_number'] = $whatsapp;
            }
        }
        
        $whatsapp_svg = trim($_POST['footer_whatsapp_svg_path'] ?? '');
        if (!empty($whatsapp_svg)) {
            if ($error = validate_svg_path($whatsapp_svg, 'SVG de WhatsApp')) {
                $validation_errors[] = $error;
            } else {
                $new_config['footer']['whatsapp_svg_path'] = $whatsapp_svg;
            }
        }
        
        // ================================================================
        // VALIDAR Y PROCESAR: Servicios
        // ================================================================
        
        $new_config['services'] = [];
        if (isset($_POST['services']) && is_array($_POST['services'])) {
            foreach ($_POST['services'] as $key => $service_data) {
                $service_id = trim($service_data['id'] ?? $key);
                
                if (empty($service_id)) continue;
                
                if (count($new_config['services']) >= 50) {
                    $validation_errors[] = "Máximo 50 servicios permitidos";
                    break;
                }
                
                // Validar ID
                if (!preg_match('/^[a-z0-9_-]+$/i', $service_id)) {
                    $validation_errors[] = "ID de servicio '{$service_id}' inválido (solo letras, números, guiones y guiones bajos)";
                    continue;
                }
                
                $label = trim($service_data['label'] ?? '');
                $url = trim($service_data['url'] ?? '');
                $category = trim($service_data['category'] ?? 'Otros Servicios');
                
                // Validaciones
                if ($error = validate_string_length($label, 2, 50, "Etiqueta de '{$service_id}'")) {
                    $validation_errors[] = $error;
                    continue;
                }
                
                if ($error = validate_url($url, "URL de '{$service_id}'", true)) {
                    $validation_errors[] = $error;
                    continue;
                }
                
                if ($error = validate_string_length($category, 2, 50, "Categoría de '{$service_id}'")) {
                    $validation_errors[] = $error;
                    continue;
                }
                
                $new_config['services'][$service_id] = [
                    'label' => $label,
                    'url' => $url,
                    'category' => $category,
                    'requires_login' => isset($service_data['requires_login']),
                    'redirect' => isset($service_data['redirect']),
                ];
            }
        }
        
        // ================================================================
        // GUARDAR CONFIGURACIÓN (solo si no hay errores)
        // ================================================================
        
        if (empty($validation_errors)) {
            $config_file = __DIR__ . '/config.php';
            
            // Verificar permisos
            if (!is_writable($config_file)) {
                $status_message = '<div class="status-message error">❌ El archivo de configuración no es escribible. Verifica los permisos.</div>';
            } else {
                // Crear backup
                $backup_file = create_backup($config_file);
                
                if (!$backup_file) {
                    $status_message = '<div class="status-message error">❌ No se pudo crear el backup. Operación cancelada.</div>';
                } else {
                    // Preparar contenido
                    $new_config_content = "<?php\n" .
                        "/**\n" .
                        " * config.php - Configuración Central\n" .
                        " * Generado automáticamente por manage.php\n" .
                        " * Fecha: " . date('Y-m-d H:i:s') . "\n" .
                        " * Usuario: " . $_SESSION['username'] . "\n" .
                        " */\n\n" .
                        "return " . safe_var_export($new_config) . ";\n";
                    
                    // Intentar escribir
                    if (file_put_contents($config_file, $new_config_content, LOCK_EX)) {
                        
                        // Verificar que el archivo sea válido
                        try {
                            $test_config = require $config_file;
                            
                            if (!is_array($test_config)) {
                                throw new Exception('Config no es un array');
                            }
                            
                            // Éxito
                            $config = $test_config;
                            
                            log_security_event(
                                'config_updated',
                                "Usuario {$_SESSION['username']} actualizó la configuración del sitio"
                            );
                            
                            $status_message = '<div class="status-message success">✅ ¡Configuración guardada con éxito! Backup creado: ' . basename($backup_file) . '</div>';
                            
                        } catch (Exception $e) {
                            // Rollback
                            copy($backup_file, $config_file);
                            
                            log_security_event(
                                'config_save_failed',
                                "Fallo al guardar configuración. Rollback ejecutado. Error: " . $e->getMessage()
                            );
                            
                            $status_message = '<div class="status-message error">❌ Error al validar la configuración guardada. Se restauró el backup.</div>';
                        }
                        
                    } else {
                        $status_message = '<div class="status-message error">❌ No se pudo escribir en el archivo de configuración.</div>';
                    }
                }
            }
        } else {
            // Hay errores de validación
            $status_message = '<div class="status-message error">';
            $status_message .= '<strong>❌ Se encontraron los siguientes errores:</strong><ul>';
            foreach ($validation_errors as $error) {
                $status_message .= '<li>' . htmlspecialchars($error) . '</li>';
            }
            $status_message .= '</ul></div>';
        }
    } catch (Exception $e) {
        $status_message = '<div class="status-message error">❌ Error de seguridad: ' . htmlspecialchars($e->getMessage()) . '</div>';
        log_security_event('csrf_validation_failed', 'Token inválido en manage.php');
    }
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
                    <div class="section-header">Botones de Servicios, Agregue sitios locales o remotos (Portal)</div>
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
                                        <?php foreach ($config['services'] as $id => $service): ?>
                                        <tr>
                                            <td><input type="text" name="services[<?= $id ?>][id]" value="<?= htmlspecialchars($id) ?>" readonly class="readonly-id"></td>
                                            <td><input type="text" name="services[<?= $id ?>][label]" value="<?= htmlspecialchars($service['label']) ?>" required></td>
                                            <td><input type="text" name="services[<?= $id ?>][url]" value="<?= htmlspecialchars($service['url']) ?>" required></td>
                                            <td><input type="text" name="services[<?= $id ?>][category]" value="<?= htmlspecialchars($service['category'] ?? '') ?>" placeholder="Ej: Accesos LAN"></td>
                                            <td class="checkbox-cell"><input type="checkbox" name="services[<?= $id ?>][requires_login]" value="1" <?= !empty($service['requires_login']) ? 'checked' : '' ?>></td>
                                            <td class="checkbox-cell"><input type="checkbox" name="services[<?= $id ?>][redirect]" value="1" <?= !empty($service['redirect']) ? 'checked' : '' ?>></td>
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
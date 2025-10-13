<?php
// manage.php - Página para administrar la configuración del sitio.

// Incluir el archivo de inicialización central.
require_once 'bootstrap.php';

$config_file = 'config.php';
$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'nonce-{$nonce}';");

// Verificar autenticación y rol de administrador.
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    // Si no es admin, redirigir a la página de login (o a index2.php si prefieres)
    header('Location: login.php');
    exit;
}

$status_message = '';

// --- MANEJO DEL GUARDADO DE LA CONFIGURACIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF primero
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $status_message = '<div class="status-message error">Error de validación de seguridad. Por favor, intente guardar de nuevo.</div>';
    } else {
        $can_save = true;

        $lp_config =& $config['landing_page'];
        $lp_config['company_name'] = trim($_POST['company_name'] ?? $lp_config['company_name']);

        // --- Contenido de la Página Principal ---
        $lp_config['sales_title'] = trim($_POST['sales_title'] ?? $lp_config['sales_title']);
        $lp_config['locations_title'] = trim($_POST['locations_title'] ?? $lp_config['locations_title']);
        $lp_config['social_title'] = trim($_POST['social_title'] ?? $lp_config['social_title']);
        $lp_config['main_sites_title'] = trim($_POST['main_sites_title'] ?? $lp_config['main_sites_title']);

        // Teléfonos
        $lp_config['phone_numbers'] = [];
        if (isset($_POST['phone_numbers']) && is_array($_POST['phone_numbers'])) {
            foreach (array_map('trim', $_POST['phone_numbers']) as $phone) {
                if (!empty($phone)) $lp_config['phone_numbers'][] = $phone;
            }
        }

        // Sucursales
        $lp_config['branches'] = [];
        if (isset($_POST['branches']) && is_array($_POST['branches'])) {
            foreach (array_map('trim', $_POST['branches']) as $branch) {
                if (!empty($branch)) $lp_config['branches'][] = $branch;
            }
        }

        // Redes Sociales (dinámico)
        $lp_config['social_links'] = [];
        if (isset($_POST['social_links']) && is_array($_POST['social_links'])) {
            foreach ($_POST['social_links'] as $link_data) {
                $id = trim($link_data['id'] ?? '');
                if (empty($id)) continue;
                $lp_config['social_links'][$id] = [
                    'label' => trim($link_data['label'] ?? 'Sin Etiqueta'),
                    'url' => trim($link_data['url'] ?? '#'),
                    'svg_path' => trim($link_data['svg_path'] ?? ''),
                ];
            }
        }

        // Sitios Principales (se mantiene estático por ahora)
        if (isset($_POST['main_sites']) && is_array($_POST['main_sites'])) {
            foreach ($_POST['main_sites'] as $key => $url) {
                if (isset($lp_config['main_sites'][$key])) $lp_config['main_sites'][$key]['url'] = trim($url);
            }
        }

        // Actualizar configuración del pie de página
        $footer_config =& $config['footer'];
        $footer_config['line1'] = trim($_POST['footer_line1'] ?? $footer_config['line1'] ?? '');
        $footer_config['line2'] = trim($_POST['footer_line2'] ?? $footer_config['line2'] ?? '');
        $footer_config['whatsapp_number'] = trim($_POST['footer_whatsapp_number'] ?? $footer_config['whatsapp_number'] ?? '');
        $footer_config['license_url'] = trim($_POST['footer_license_url'] ?? $footer_config['license_url'] ?? '');
        $footer_config['whatsapp_svg_path'] = trim($_POST['footer_whatsapp_svg_path'] ?? $footer_config['whatsapp_svg_path'] ?? '');

        // 3. Actualizar la lista de servicios
        $config['services'] = []; // Limpiar para reconstruir desde el POST
        if (isset($_POST['services']) && is_array($_POST['services'])) {
            foreach ($_POST['services'] as $key => $service_data) {
                // Para servicios existentes, la clave ($key) y el id del campo son iguales.
                // Para servicios nuevos, la clave es 'nuevo_...' y el id lo define el usuario en el campo de texto.
                $service_id = trim($service_data['id'] ?? $key);

                if (empty($service_id)) {
                    continue; // Ignorar servicios sin un ID válido
                }

                // Usamos el ID (del campo de texto para los nuevos, o la clave para los existentes)
                // como la clave final en el array de configuración.
                $config['services'][$service_id] = [
                    'label'          => trim($service_data['label'] ?? 'Sin Etiqueta'),
                    'url'            => trim($service_data['url'] ?? '#'),
                    // Se añade la categoría, con 'Otros Servicios' como valor por defecto si está vacío.
                    'category'       => trim($service_data['category'] ?? 'Otros Servicios'),
                    'requires_login' => isset($service_data['requires_login']), // checkbox value is '1' if checked
                    'redirect'       => isset($service_data['redirect']),       // checkbox value is '1' if checked
                ];
            }
        }

        // Ya no necesitamos la sección 'login' en el archivo de configuración.
        unset($config['login']);

        // 4. Escribir la nueva configuración de vuelta al archivo
        $new_config_content = "<?php\n" .
            "/**\n * /config.php - Archivo de Configuración Central\n * Este archivo debe devolver un array con toda la configuración de la aplicación.\n * No debe ejecutar lógica, solo definir datos.\n */\n\n" .
            "return " . var_export($config, true) . ";\n";

        if ($can_save) {
            if (file_exists($config_file)) {
                copy($config_file, $config_file . '.bak');
            }

            // Escribir el nuevo contenido
            if (file_put_contents($config_file, $new_config_content)) {
                $status_message = '<div class="status-message success">¡Configuración guardada con éxito!</div>';
            } else {
                $status_message = '<div class="status-message error">Error: No se pudo escribir en el archivo de configuración (<code>' . htmlspecialchars($config_file) . '</code>). Verifique los permisos.</div>';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Sitio</title>
    <base href="/secmti/">
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="page-manage">
    <div class="admin-container">
        <header class="admin-header">
            <h1>⚙️ Administrar Configuración</h1>
            <p>Edita los parámetros principales de tu portal.</p>
        </header>

        <div class="content">
            <?= $status_message ?>

            <form method="POST" action="manage.php">
                <!-- Sección de Ajustes Generales -->
                <div class="section">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
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
                    <button type="submit" class="save-btn">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <a href="index2.php" class="back-btn">
        ← Volver al Portal de Servicios
    </a>

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
        // --- Lógica para secciones colapsables (acordeón) ---
        document.querySelectorAll('.section-header').forEach(header => {
            header.addEventListener('click', () => {
                header.classList.toggle('active');
                const body = header.nextElementSibling;
                if (body.style.maxHeight) {
                    body.style.maxHeight = null;
                } else {
                    body.style.maxHeight = body.scrollHeight + "px";
                }
            });
        });

        // --- Lógica para listas dinámicas ---
        const contentDiv = document.querySelector('.content');
        document.querySelectorAll('.add-item-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const targetListId = this.dataset.target;
                const inputName = this.dataset.name;
                const placeholder = this.dataset.placeholder || '';
                const list = document.getElementById(targetListId);
                
                const newItem = document.createElement('div');
                newItem.classList.add('repeatable-item');
                newItem.innerHTML = `
                    <input type="text" name="${inputName}" value="" placeholder="${placeholder}" />
                    <button type="button" class="delete-item-btn">Eliminar</button>
                `;
                list.insertBefore(newItem, this);
                newItem.querySelector('input').focus();
            });
        });

        contentDiv.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('delete-item-btn')) {
                e.preventDefault();
                e.target.closest('.repeatable-item').remove();
            }
        });

        // --- Lógica para la tabla de servicios ---
        const servicesTableBody = document.querySelector('#services-table tbody');
        servicesTableBody.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('delete-service-btn')) {
                e.preventDefault();
                e.target.closest('tr').remove();
            }
        });

        const addServiceBtn = document.getElementById('add-service-btn');
        addServiceBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const newId = 'nuevo_' + Date.now();
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td><input type="text" name="services[${newId}][id]" placeholder="ej: miBoton" required></td>
                <td><input type="text" name="services[${newId}][label]" placeholder="ej: Mi Botón" required></td>
                <td><input type="text" name="services[${newId}][url]" placeholder="https://... o info.php" required></td>
                <td><input type="text" name="services[${newId}][category]" placeholder="Ej: Accesos WAN" required></td>
                <td class="checkbox-cell"><input type="checkbox" name="services[${newId}][requires_login]" value="1" checked></td>
                <td class="checkbox-cell"><input type="checkbox" name="services[${newId}][redirect]" value="1"></td>
                <td><button type="button" class="delete-service-btn">Eliminar</button></td>
            `;
            servicesTableBody.appendChild(newRow);
            newRow.querySelector('input').focus();
        });

        // --- Lógica para la tabla de redes sociales ---
        const socialLinksTableBody = document.querySelector('#social-links-table tbody');
        socialLinksTableBody.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('delete-item-btn')) {
                e.preventDefault();
                e.target.closest('tr').remove();
            }
        });

        const addSocialLinkBtn = document.getElementById('add-social-link-btn');
        addSocialLinkBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const newId = 'nuevo_' + Date.now();
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td><input type="text" name="social_links[${newId}][id]" placeholder="ej: tiktok" required></td>
                <td><input type="text" name="social_links[${newId}][label]" placeholder="TikTok" required></td>
                <td><input type="text" name="social_links[${newId}][url]" placeholder="https://www.tiktok.com/..." required></td>
                <td><textarea name="social_links[${newId}][svg_path]" rows="2" placeholder="<path d='...' />"></textarea></td>
                <td><button type="button" class="delete-item-btn">Eliminar</button></td>
            `;
            socialLinksTableBody.appendChild(newRow);
            newRow.querySelector('input').focus();
        });


    });
    </script>
</body>
</html>
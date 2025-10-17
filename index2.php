<?php
// index2.php - Pagina principal con acceso seguro

// Incluir el archivo de inicialización central.
// Este se encarga de la configuración, sesión, cabeceras de seguridad e IP.
require_once 'bootstrap.php';

// PROTEGER PÁGINA: Si el usuario no está logueado, redirigir a la página de login.
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Generar un 'nonce' para los scripts y estilos en línea.
$nonce = base64_encode(random_bytes(16));

// La cabecera CSP es específica para esta página.
// Se permite 'self' y el CDN de SortableJS para scripts, y se usa el nonce para scripts y estilos en línea.
// Se elimina 'unsafe-inline' de style-src para cumplir con las mejores prácticas de CSP cuando se usa un nonce.
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'nonce-{$nonce}'; style-src 'self' 'nonce-{$nonce}';");

// La conexión a la base de datos se obtiene a través de bootstrap.php
$pdo = get_database_connection($config, false); // false: no es crítico si falla, la página puede mostrarse parcialmente.

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Portal de servicios de <?= htmlspecialchars($config['landing_page']['company_name'] ?? 'SECM') ?>" />
    <meta name="robots" content="noindex, nofollow" />
    <title>Portal de Servicios - <?= htmlspecialchars($config['landing_page']['company_name'] ?? 'SECM') ?></title>
    <base href="/secmti/"> <!-- Asegúrate de que esta ruta base sea correcta para tu entorno -->
    <!-- 1. Añadir la librería SortableJS -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js" nonce="<?= htmlspecialchars($nonce) ?>"></script>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/index2.css"> <!-- NUEVA LÍNEA -->
</head>
<body class="page-index2">
    <div class="container" role="main">
        <div class="portal-header">
            <span>Bienvenido, <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Usuario') ?></strong></span>
            <?php if (($_SESSION['user_role'] ?? 'user') === 'admin'): ?>
            <div class="edit-mode-controls">
                <button id="edit-layout-btn" class="action-btn">✏️ Organizar Botones</button>
                <button id="save-layout-btn" class="action-btn save-btn hidden">💾 Guardar Orden</button>
            </div>
            <?php endif; ?>
            <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
        </div>

        <?php require_once 'templates/navbar.php'; ?>

        <?php 
        // Widget de estadísticas
        if (file_exists('templates/dashboard_stats.php')) {
            include 'templates/dashboard_stats.php';
        }
        ?>

        <!-- La barra de navegación ya se incluye desde 'templates/navbar.php', no es necesario duplicarla. -->

        <?php
        // Agrupar servicios por categoría, manteniendo el ID como clave
        $services_from_db = [];
        if ($pdo) {
            $query = "SELECT * FROM services WHERE is_active = 1";
            if (($_SESSION['user_role'] ?? 'user') !== 'admin') {
                $query .= " AND (requires_role IS NULL OR requires_role = 'user')";
            }
            $query .= " ORDER BY category, sort_order";
            $services_from_db = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
        }

        $grouped_services = [];
        foreach ($services_from_db as $service) {
            $category = $service['category'] ?? 'Otros Servicios';
            $grouped_services[$category][] = $service;
        }

        // Ordenar categorías: LAN primero, WAN segundo, luego sucursales alfabéticamente
        // Esta lógica de ordenamiento personalizado se puede mover a la tabla `service_categories` en el futuro.
        uksort($grouped_services, function($a, $b) {
            // Lista de prioridades para categorías. Un número más bajo significa mayor prioridad.
            // Usar slugs (identificadores) es más robusto que nombres de visualización.
            $priority = [
                'accesos-lan' => 1,
                'accesos-wan' => 2,
                'pedraza-central' => 10,
                'piso-7' => 11,
                'hotel-san-miguel' => 20,
                'hotel-lyon' => 21,
                'hotel-villa-de-merlo' => 22,
                'pan-lactal' => 30,
            ];
            
            // Convertir nombres de categoría a slugs para la comparación
            $slugA = strtolower(str_replace(' ', '-', $a));
            $slugB = strtolower(str_replace(' ', '-', $b));

            // Obtener la prioridad de cada categoría, o 99 si no está en la lista.
            $priorityA = $priority[$slugA] ?? 99;
            $priorityB = $priority[$slugB] ?? 99;
            
            if ($priorityA !== $priorityB) {
                return $priorityA - $priorityB;
            }
            
            // Si tienen la misma prioridad (o ninguna), ordenar alfabéticamente por el nombre original.
            return strcmp($a, $b);
        });
        ?>

        <div class="sections-container">
            <?php foreach ($grouped_services as $category_name => $services): ?>
                <?php
                    // Determinar el tipo de categoría para estilos específicos
                    $category_slug = strtolower(str_replace(' ', '-', $category_name));
                    $category_type = '';
                    if (stripos($category_name, 'lan') !== false) {
                        $category_type = 'data-category="lan"';
                    } elseif (stripos($category_name, 'wan') !== false) {
                        $category_type = 'data-category="wan"';
                    } elseif (stripos($category_name, 'hotel') !== false ||
                              stripos($category_name, 'sucursal') !== false) {
                        $category_type = 'data-category="sucursal"';
                    }
                ?>
                <section class="service-section"
                         id="category-<?= htmlspecialchars($category_slug) ?>"
                         <?= $category_type ?>>
                    <h2 class="section-title">
                        <button class="section-toggle-btn" aria-expanded="true" aria-controls="body-<?= htmlspecialchars($category_slug) ?>">
                            <span class="section-title-text"><?= htmlspecialchars($category_name) ?></span>
                            <span class="section-badge"><?= count($services) ?></span>
                        </button>
                    </h2>
                    <nav class="menu section-body"                         
                         data-category="<?= htmlspecialchars($category_slug) ?>">
                        <?php foreach ($services as $servicio): ?>
                            <?php
                                $target = !empty($servicio['redirect']) ? '_self' : '_blank';
                                echo '<a href="' . htmlspecialchars($servicio['url']) . '" 
                                         class="menu-button" 
                                         target="' . $target . '" 
                                         data-service-id="' . htmlspecialchars($servicio['id']) . '">'
                                     . htmlspecialchars($servicio['label']) .
                                     '</a>';
                            ?>
                        <?php endforeach; ?>
                    </nav>
                </section>
            <?php endforeach; ?>
        </div>

        <!-- Pie de página unificado -->
        <footer class="footer footer-no-border">
            <strong><?= htmlspecialchars($config['footer']['line1'] ?? '') ?></strong><br>
            <div class="footer-contact-line">
                <span><?= htmlspecialchars($config['footer']['line2'] ?? '') ?></span>
                <?php if (!empty($config['footer']['whatsapp_number']) &&
                          !empty($config['footer']['whatsapp_svg_path'])): ?><a href="https://wa.me/<?= htmlspecialchars($config['footer']['whatsapp_number']) ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="footer-whatsapp-link"
                       aria-label="Contactar por WhatsApp">
                        <svg class="icon" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="<?= htmlspecialchars($config['footer']['whatsapp_svg_path']) ?>"/>
                        </svg>
                        <span><?= htmlspecialchars($config['footer']['whatsapp_number']) ?></span>
                    </a><?php endif; ?>
        </div>
            <a href="<?= htmlspecialchars($config['footer']['license_url'] ?? '#') ?>"
               target="_blank"
               rel="license">
                Términos y Condiciones (Licencia GNU GPL v3)
            </a>
        </footer>
    </div>

    <script nonce="<?= htmlspecialchars($nonce) ?>">
    document.addEventListener('DOMContentLoaded', function () {
        const editBtn = document.getElementById('edit-layout-btn');
        const saveBtn = document.getElementById('save-layout-btn');
        const sectionsContainer = document.querySelector('.sections-container');
        let sortableInstances = [];
        let isEditMode = false;

        // Funcionalidad para colapsar secciones
        document.querySelectorAll('.section-toggle-btn').forEach(button => {
            button.addEventListener('click', () => {
                const section = button.closest('.service-section');
                const body = section.querySelector('.section-body');
                const isExpanded = button.getAttribute('aria-expanded') === 'true';

                button.setAttribute('aria-expanded', !isExpanded);
                body.style.maxHeight = isExpanded ? '0px' : body.scrollHeight + 'px';
                section.classList.toggle('collapsed', isExpanded);
            });
        });

        // Funcionalidad para organizar botones (modo edición)
        function toggleEditMode() {
            isEditMode = !isEditMode;
            document.body.classList.toggle('edit-mode-active', isEditMode);

            if (isEditMode) {
                editBtn.textContent = '🔒 Bloquear';
                // Inicializar SortableJS para las secciones
                new Sortable(sectionsContainer, {
                    animation: 150,
                    handle: '.section-title',
                    ghostClass: 'sortable-ghost'
                });

                document.querySelectorAll('.menu').forEach(menu => {
                    // Inicializar SortableJS para los botones dentro de cada menú
                    const sortable = new Sortable(menu, {
                        group: 'shared-services', // Permite arrastrar entre menús
                        animation: 150,
                        ghostClass: 'sortable-ghost',
                        onEnd: () => saveBtn.classList.remove('hidden') // Mostrar botón de guardar al mover algo
                    });
                    sortableInstances.push(sortable);
                });
            } else {
                editBtn.textContent = '✏️ Organizar Botones';
                saveBtn.classList.add('hidden');
                // Destruir instancias para que no se pueda arrastrar
                sortableInstances.forEach(s => s.destroy());
                sortableInstances = [];
            }
        }

        async function saveLayout() {
            const newLayout = {};
            document.querySelectorAll('.service-section').forEach(section => {
                const categorySlug = section.querySelector('.menu').dataset.category;
                newLayout[categorySlug] = [];
                section.querySelectorAll('.menu-button').forEach(button => {
                    newLayout[categorySlug].push(button.dataset.serviceId);
                });
            });

            try {
                const response = await fetch('api/organizer.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ layout: newLayout, csrf_token: '<?= htmlspecialchars($_SESSION['csrf_token']) ?>' })
                });
                const result = await response.json();
                if (result.success) {
                    alert('¡Orden guardado con éxito!');
                    toggleEditMode(); // Salir del modo edición
                } else {
                    throw new Error(result.message || 'Error desconocido al guardar.');
                }
            } catch (error) {
                alert('Error al guardar el orden: ' + error.message);
            }
        }

        if (editBtn) editBtn.addEventListener('click', toggleEditMode);
        if (saveBtn) saveBtn.addEventListener('click', saveLayout);
    });
    </script>
</body>
</html>
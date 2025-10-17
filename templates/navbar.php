<?php
/**
 * navbar.php - Barra de navegación principal del portal (index2.php).
 */

$main_nav_links = [
    'home' => [
        'label' => '🏠 Inicio (Página Pública)',
        'url' => 'index.php',
    ],
    'manage' => [
        'label' => '⚙️ Configuración General',
        'url' => 'manage.php',
        'requires_role' => 'admin'
    ],
    'datacenter_manage' => [
        'label' => '🏢 Gestión de Infraestructura',
        'url' => 'datacenter_view.php', // Apunta a la página unificada
        'requires_role' => 'admin'
    ],
    'pc_equipment_manage' => [
        'label' => '💻 Parque Informático',
        'url' => 'parque_informatico.php',
        'requires_role' => 'admin'
    ],
    'hosting_manage' => [
        'label' => '🌐 Gestión de Hosting',
        'url' => 'hosting_manager.php',
        'requires_role' => 'admin'
    ],
    'mytop' => [
        'label' => '🗄️ Servicios MySQL',
        'url' => 'mytop.php',
    ],
    'diag' => [
        'label' => '📊 Información del Servidor',
        'url' => 'diag_x9k2.php',
    ],
];

// Renderizar la barra de navegación
echo '<nav class="main-navbar">';

// Botón especial para administradores
if (($_SESSION['user_role'] ?? 'user') === 'admin') {
    echo '<a href="users_manager.php" class="navbar-button">👥 Gestionar Usuarios</a>';
    echo '<a href="activity_log.php" class="navbar-button">📜 Actividad Reciente</a>';
}

foreach ($main_nav_links as $link) {
    // Comprobar si el enlace requiere un rol específico y si el usuario lo cumple
    if (isset($link['requires_role']) && ($_SESSION['user_role'] ?? 'user') !== $link['requires_role']) {
        continue; // No mostrar este enlace
    }
    echo '<a href="' . htmlspecialchars($link['url']) . '" class="navbar-button">' . htmlspecialchars($link['label']) . '</a>';
}
echo '</nav>';
?>

<?php
/**
 * navbar.php - Barra de navegación principal del portal (index2.php).
 */

$user_role = $_SESSION['user_role'] ?? 'user';

$main_nav_links = [
    'home' => [
        'label' => '🏠 Inicio (Página Pública)',
        'url' => 'index.php',
        'roles' => ['admin', 'user']
    ],
    'manage' => [
        'label' => '⚙️ Configuración General',
        'url' => 'manage.php',
        'roles' => ['admin']
    ],
    'datacenter_manage' => [
        'label' => '🏢 Gestión de Infraestructura',
        'url' => 'datacenter_view.php',
        'roles' => ['admin', 'user']
    ],
    'pc_equipment_manage' => [
        'label' => '💻 Parque Informático',
        'url' => 'parque_informatico.php',
        'roles' => ['admin', 'user']
    ],
    'hosting_manage' => [
        'label' => '🌐 Gestión de Cuentas',
        'url' => ($user_role === 'admin') ? 'hosting_manager.php' : 'hosting_view.php',
        'roles' => ['admin', 'user']
    ],
    'mytop' => [
        'label' => '🗄️ Servicios MySQL',
        'url' => 'mytop.php',
        'roles' => ['admin']
    ],
    'diag' => [
        'label' => '📊 Información del Servidor',
        'url' => 'diag_x9k2.php',
        'roles' => ['admin']
    ],
];

// Renderizar la barra de navegación
echo '<nav class="main-navbar">';

// Botón especial para administradores
if ($user_role === 'admin') {
    echo '<a href="users_manager.php" class="navbar-button">👥 Gestionar Usuarios</a>';
    echo '<a href="activity_log.php" class="navbar-button">📜 Actividad Reciente</a>';
}

foreach ($main_nav_links as $link) {
    // Comprobar si el enlace requiere un rol específico y si el usuario lo cumple
    if (isset($link['roles']) && in_array($user_role, $link['roles'])) {
        echo '<a href="' . htmlspecialchars($link['url']) . '" class="navbar-button">' . htmlspecialchars($link['label']) . '</a>';
    }
}
echo '</nav>';
?>

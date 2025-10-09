<?php
// logout.php - Cierra la sesión del usuario de forma segura.

// Incluir el archivo de inicialización central.
// Este se encarga de la configuración y de iniciar la sesión.
require_once 'bootstrap.php';

// 1. Limpiar todas las variables de la sesión actual.
$_SESSION = [];

// 2. Eliminar la cookie de sesión del navegador.
// Es una buena práctica eliminar la cookie explícitamente.
// Leemos el nombre de la cookie desde la configuración para asegurar que borramos la correcta.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    $session_name = $config['session']['name'] ?? session_name(); // Usar nombre de config o el por defecto
    setcookie($session_name, '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Finalmente, destruir la sesión en el servidor.
session_destroy();

// 4. Redirigir al usuario a la página de inicio de sesión.
header('Location: login.php?status=logged_out');
exit;

<?php
/**
 * api/organizer.php
 * Endpoint para guardar el nuevo orden de los servicios.
 */

require_once '../bootstrap.php';

header('Content-Type: application/json');

// 1. Verificar autenticación y rol de administrador
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// 2. Validar token CSRF
if (!validate_csrf_token($input['csrf_token'] ?? null, false)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Error de validación de seguridad.']);
    exit;
}

$new_layout = $input['layout'] ?? null;
if (!$new_layout) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se recibieron datos de la nueva estructura.']);
    exit;
}

$config_file = '../config.php';

// 3. Reconstruir el array de servicios de forma segura
$all_services = $config['services']; // Copia de todos los servicios existentes
$ordered_services = [];
$processed_ids = [];

foreach ($new_layout as $category_slug => $service_ids) {
    // Convertir slug de nuevo a un nombre de categoría legible (simple reemplazo)
    $category_name = ucwords(str_replace('-', ' ', $category_slug));

    foreach ($service_ids as $service_id) {
        if (isset($all_services[$service_id])) {
            $ordered_services[$service_id] = $all_services[$service_id];
            // Actualizar la categoría del servicio
            $ordered_services[$service_id]['category'] = $category_name;
            $processed_ids[] = $service_id;
        }
    }
}

// 4. Añadir los servicios que no estaban en el layout (para no perderlos)
foreach ($all_services as $id => $service) {
    if (!in_array($id, $processed_ids)) {
        $ordered_services[$id] = $service;
    }
}

// 5. Actualizar y guardar el archivo de configuración
$config['services'] = $ordered_services;

/**
 * Función segura para exportar el array de configuración a un string PHP.
 * Evita los problemas de sintaxis de var_export.
 */
function safe_config_export(array $config_array): string {
    $content = "<?php\n" .
        "/**\n * /config.php - Archivo de Configuración Central\n * Este archivo debe devolver un array con toda la configuración de la aplicación.\n * No debe ejecutar lógica, solo definir datos.\n */\n\n" .
        "return " . var_export($config_array, true) . ";\n";
    return $content;
}

$new_config_content = safe_config_export($config);

// Crear un backup antes de sobreescribir
if (file_exists($config_file)) {
    if (!copy($config_file, $config_file . '.bak')) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'No se pudo crear el backup del archivo de configuración (' . $config_file . '.bak).']);
        exit;
    }
}

if (file_put_contents($config_file, $new_config_content)) {
    echo json_encode(['success' => true, 'message' => 'Orden guardado correctamente.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo escribir en el archivo de configuración (' . $config_file . ').']);
}
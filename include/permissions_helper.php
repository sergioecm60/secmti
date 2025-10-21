<?php
/**
 * includes/permissions_helper.php
 * Funciones auxiliares para gestionar permisos de locaciones por usuario
 */

/**
 * Obtiene las locaciones permitidas para un usuario.
 * Los admins tienen acceso a todas las locaciones.
 * Los usuarios solo a las asignadas.
 *
 * @param PDO $pdo Conexión a la base de datos.
 * @param int $user_id ID del usuario.
 * @param string $user_role Rol del usuario ('admin' o 'user').
 * @return array|null Array de IDs de locaciones permitidas, o null si tiene acceso a todas.
 */
function get_user_allowed_locations(PDO $pdo, int $user_id, string $user_role): ?array {
    // Los administradores tienen acceso a todas las locaciones.
    if ($user_role === 'admin') {
        return null; // null significa "todas las locaciones".
    }

    // Para usuarios normales, obtener las locaciones asignadas.
    $stmt = $pdo->prepare("SELECT location_id FROM user_locations WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $locations = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return $locations ?: []; // Retornar array vacío si no tiene locaciones asignadas.
}

/**
 * Verifica si un usuario tiene permiso para ver una locación específica.
 *
 * @param PDO $pdo Conexión a la base de datos.
 * @param int $user_id ID del usuario.
 * @param string $user_role Rol del usuario.
 * @param int $location_id ID de la locación a verificar.
 * @return bool True si tiene permiso, false si no.
 */
function user_can_view_location(PDO $pdo, int $user_id, string $user_role, int $location_id): bool {
    // Los admins pueden ver todas las locaciones.
    if ($user_role === 'admin') {
        return true;
    }

    // Verificar si el usuario tiene asignada esta locación.
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_locations WHERE user_id = ? AND location_id = ?");
    $stmt->execute([$user_id, $location_id]);

    return $stmt->fetchColumn() > 0;
}

/**
 * Genera una cláusula WHERE SQL para filtrar por locaciones permitidas.
 *
 * @param array|null $allowed_locations Array de IDs de locaciones o null para todas.
 * @param string $location_column Nombre de la columna de location_id en la consulta.
 * @return array ['sql' => string, 'params' => array] Fragmento SQL y parámetros.
 */
function get_location_filter_sql(?array $allowed_locations, string $location_column = 'location_id'): array {
    // Si es null (admin), no aplicar filtro.
    if ($allowed_locations === null) {
        return ['sql' => '', 'params' => []];
    }

    // Si el array está vacío, no mostrar nada.
    if (empty($allowed_locations)) {
        return ['sql' => " AND 1=0", 'params' => []]; // Condición que nunca se cumple.
    }

    // Generar el IN clause.
    $placeholders = implode(',', array_fill(0, count($allowed_locations), '?'));
    $sql = " AND {$location_column} IN ({$placeholders})";

    return ['sql' => $sql, 'params' => $allowed_locations];
}

/**
 * Verifica si el usuario actual tiene permisos de administrador.
 *
 * @return bool True si es admin, false si no.
 */
function is_admin(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Verifica si el usuario actual es un usuario regular (no admin).
 *
 * @return bool True si es usuario regular, false si no.
 */
function is_regular_user(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'user';
}

/**
 * Obtiene información completa de las locaciones del usuario para mostrar.
 *
 * @param PDO $pdo Conexión a la base de datos.
 * @param int $user_id ID del usuario.
 * @param string $user_role Rol del usuario.
 * @return array Array con información completa de las locaciones.
 */
function get_user_locations_info(PDO $pdo, int $user_id, string $user_role): array {
    if ($user_role === 'admin') {
        // Retornar todas las locaciones.
        $stmt = $pdo->query("SELECT id, name, address, notes FROM dc_locations ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Para usuarios regulares, solo sus locaciones asignadas.
    $stmt = $pdo->prepare("
        SELECT l.id, l.name, l.address, l.notes 
        FROM dc_locations l
        INNER JOIN user_locations ul ON l.id = ul.location_id
        WHERE ul.user_id = ?
        ORDER BY l.name
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
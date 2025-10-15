<?php
/**
 * license.php - Muestra el archivo de licencia de forma segura y eficiente.
 *
 * CARACTERÍSTICAS:
 * - Envía el contenido como texto plano para evitar la interpretación de HTML/JS.
 * - Utiliza cabeceras de caché (ETag, Last-Modified) para optimizar la entrega.
 * - Responde con 304 Not Modified si el cliente ya tiene la versión más reciente.
 * - Usa readfile() para ser más eficiente en el uso de memoria.
 */

// 1. Definir la ruta al archivo de licencia.
$licenseFile = __DIR__ . '/LICENSE.txt';

// 2. Comprobar si el archivo existe y es legible.
if (!file_exists($licenseFile) || !is_readable($licenseFile)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Error: El archivo LICENSE.txt no se encontró en el servidor.";
    exit;
}

// 3. Obtener información para el caché.
$lastModified = filemtime($licenseFile);
$etag = md5_file($licenseFile);

// 4. Comprobar las cabeceras del cliente para ver si podemos usar el caché.
$ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';

if ((!empty($ifModifiedSince) && strtotime($ifModifiedSince) >= $lastModified) ||
    (!empty($ifNoneMatch) && trim($ifNoneMatch, '"') === $etag)) {
    http_response_code(304); // Not Modified
    exit;
}

// 5. Si no se puede usar el caché, enviar el archivo con las cabeceras correctas.
header('Content-Type: text/plain; charset=utf-8');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
header('ETag: "' . $etag . '"');

// 6. Usar readfile() para enviar el contenido directamente al buffer de salida.
readfile($licenseFile);
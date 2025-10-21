<?php
/**
 * migrate_passwords.php - Script para re-cifrar contraseñas después de un cambio de APP_ENCRYPTION_KEY.
 *
 * IMPORTANTE:
 * 1. Haz un backup de tu base de datos ANTES de ejecutar este script.
 * 2. Coloca este archivo en la raíz de tu proyecto (junto a bootstrap.php).
 * 3. Ejecútalo desde el navegador.
 * 4. Una vez terminado, ELIMINA este archivo del servidor por seguridad.
 */

require_once 'bootstrap.php';

// --- Medidas de Seguridad ---
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    die('Acceso denegado. Debes ser administrador.');
}

$new_key_b64 = $_ENV['APP_ENCRYPTION_KEY'] ?? '';
if (empty($new_key_b64)) {
    die('Error: La nueva APP_ENCRYPTION_KEY no está definida en tu archivo .env');
}

$tables_to_migrate = [
    'dc_servers' => 'password',
    'dc_credentials' => 'password',
    'dc_hosting_accounts' => 'password',
    'dc_hosting_ftp_accounts' => 'password',
    'dc_hosting_emails' => 'password',
];

$output = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['old_key'])) {
    $old_key_b64 = trim($_POST['old_key']);
    $output .= "<h2>Iniciando migración...</h2>";

    try {
        $pdo = get_database_connection($config, true);
        $total_updated = 0;

        foreach ($tables_to_migrate as $table => $column) {
            $output .= "<h4>Procesando tabla: <code>{$table}</code></h4>";
            
            $stmt_select = $pdo->query("SELECT id, `{$column}` FROM `{$table}` WHERE `{$column}` IS NOT NULL AND `{$column}` != ''");
            $rows = $stmt_select->fetchAll(PDO::FETCH_ASSOC);
            $updated_in_table = 0;

            if (empty($rows)) {
                $output .= "<p style='color: #888;'>No hay contraseñas que migrar en esta tabla.</p>";
                continue;
            }

            $stmt_update = $pdo->prepare("UPDATE `{$table}` SET `{$column}` = ? WHERE id = ?");

            foreach ($rows as $row) {
                $id = $row['id'];
                $encrypted_pass = $row[$column];

                // 1. Descifrar con la clave ANTIGUA
                $decrypted_pass = decrypt_with_key($encrypted_pass, $old_key_b64);

                if ($decrypted_pass === false) {
                    $output .= "<p style='color: orange;'>ADVERTENCIA: No se pudo descifrar la contraseña para el ID {$id} en la tabla `{$table}`. Es posible que ya estuviera cifrada con la nueva clave o que la clave antigua sea incorrecta. Se omite.</p>";
                    continue;
                }

                // 2. Cifrar con la clave NUEVA
                $new_encrypted_pass = encrypt_with_key($decrypted_pass, $new_key_b64);

                if ($new_encrypted_pass === false) {
                    $output .= "<p style='color: red;'>ERROR: No se pudo re-cifrar la contraseña para el ID {$id} en la tabla `{$table}`. Se omite.</p>";
                    continue;
                }

                // 3. Actualizar en la base de datos
                $stmt_update->execute([$new_encrypted_pass, $id]);
                $updated_in_table++;
            }
            $output .= "<p style='color: green;'>Se actualizaron {$updated_in_table} contraseñas en `{$table}`.</p>";
            $total_updated += $updated_in_table;
        }

        $output .= "<h3 style='color: blue;'>¡Migración completada! Se actualizaron un total de {$total_updated} contraseñas.</h3>";
        $output .= "<p style='font-weight: bold; color: red;'>Ahora puedes probar la función de copiar contraseñas. Si todo funciona, por favor, ELIMINA ESTE ARCHIVO (migrate_passwords.php) del servidor.</p>";

    } catch (Exception $e) {
        $output .= "<h3 style='color: red;'>Error durante la migración:</h3>";
        $output .= "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    }
}

/** Funciones de cifrado locales para usar claves específicas */
function encrypt_with_key(string $password, string $key_b64): string|false {
    $key = base64_decode($key_b64);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($password, 'aes-256-cbc', $key, 0, $iv);
    return $encrypted === false ? false : base64_encode($iv . $encrypted);
}

function decrypt_with_key(string $encrypted_password, string $key_b64): string|false {
    $key = base64_decode($key_b64);
    $data = base64_decode($encrypted_password);
    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    if (mb_strlen($data, '8bit') < $iv_length) return false;
    $iv = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);
    return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Migración de Contraseñas</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; margin: 20px; background: #f4f4f4; }
        .container { max-width: 800px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"] { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .warning { background: #fff3cd; border: 1px solid #ffeeba; padding: 15px; border-radius: 4px; color: #856404; margin-bottom: 20px; }
        .output { margin-top: 20px; background: #e9ecef; padding: 15px; border-radius: 4px; max-height: 400px; overflow-y: auto; }
        code { background: #d1ecf1; padding: 2px 4px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Asistente de Migración de Contraseñas</h1>
        <div class="warning">
            <strong>¡IMPORTANTE!</strong>
            <ol>
                <li><strong>Haga un backup de su base de datos</strong> antes de continuar.</li>
                <li>Este script solucionará el problema de descifrado si su <code>APP_ENCRYPTION_KEY</code> ha cambiado.</li>
                <li>Una vez finalizado, <strong>elimine este archivo</strong> del servidor.</li>
            </ol>
        </div>

        <form method="POST" action="">
            <div style="margin-bottom: 15px;">
                <label for="old_key">Clave de Cifrado ANTIGUA (OLD_APP_ENCRYPTION_KEY):</label>
                <input type="text" id="old_key" name="old_key" required placeholder="Pegue aquí la clave antigua que estaba en su .env">
                <small>Esta es la clave con la que se cifraron las contraseñas que ahora no funcionan.</small>
            </div>
            <div style="margin-bottom: 15px;">
                <label>Clave de Cifrado NUEVA (actual en .env):</label>
                <input type="text" value="<?= htmlspecialchars($new_key_b64) ?>" readonly style="background: #eee;">
            </div>
            <button type="submit">Iniciar Migración</button>
        </form>

        <?php if (!empty($output)): ?>
            <div class="output">
                <?= $output ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
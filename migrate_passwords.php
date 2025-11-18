<?php
/**
 * migrate_passwords.php
 *
 * Este script migra las contraseñas de la base de datos al nuevo formato de hash.
 *
 * IMPORTANTE:
 * 1. Haga una copia de seguridad de su tabla `users` antes de ejecutar este script.
 * 2. Este script debe ejecutarse desde la línea de comandos (CLI) o ser accedido por un administrador.
 * 3. Elimine o restrinja el acceso a este archivo después de su uso.
 */

require_once 'bootstrap.php';

// Permitir la ejecución solo desde CLI o si el usuario es admin
if (php_sapi_name() !== 'cli' && ($_SESSION['user_role'] ?? '') !== 'admin') {
    die('Acceso no autorizado.');
}

echo "<pre>"; // Formato para salida legible en navegador

use SecMTI\Util\Encryption;

echo "Iniciando script de migración de contraseñas...\n";
echo "=================================================\n\n";

try {
    $pdo = \SecMTI\Core\Registry::get('pdo');
    $encryption = new Encryption(APP_ENCRYPTION_KEY);

    $stmt = $pdo->query("SELECT id, username, pass_hash FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($users)) {
        echo "No se encontraron usuarios. No hay nada que hacer.\n";
        exit;
    }

    $migrated_count = 0;
    $rehashed_count = 0;
    $skipped_count = 0;
    $failed_count = 0;

    foreach ($users as $user) {
        $user_id = $user['id'];
        $username = $user['username'];
        $current_hash = $user['pass_hash'];

        echo "Procesando usuario: {$username} (ID: {$user_id})... ";

        if (empty($current_hash)) {
            echo "[ADVERTENCIA] La contraseña está vacía. Se omite.\n";
            $failed_count++;
            continue;
        }

        $hash_info = password_get_info($current_hash);

        // Escenario 1: La contraseña NO es un hash reconocido por password_verify.
        // Probablemente está cifrada con el método antiguo (AES-CBC).
        if ($hash_info['algo'] === 0) {
            echo "Formato no hash detectado. Intentando descifrar y migrar... ";

            $plaintext_pass = $encryption->decrypt($current_hash);

            if ($plaintext_pass !== false) {
                $new_hash = password_hash($plaintext_pass, PASSWORD_DEFAULT);
                
                $update_stmt = $pdo->prepare("UPDATE users SET pass_hash = ? WHERE id = ?");
                $update_stmt->execute([$new_hash, $user_id]);

                echo "[ÉXITO] Migrado correctamente.\n";
                $migrated_count++;
            } else {
                echo "[FALLO] No se pudo descifrar la contraseña. Requiere reseteo manual.\n";
                $failed_count++;
            }
        }
        // Escenario 2: La contraseña YA es un hash válido.
        else {
            // Comprobar si el hash necesita ser actualizado (p. ej., si el costo o el algoritmo han cambiado).
            if (password_needs_rehash($current_hash, PASSWORD_DEFAULT)) {
                echo "Hash obsoleto detectado. Intentando re-hashear... ";
                
                // Esto solo es posible si conocemos la contraseña original, lo cual no es el caso.
                // La re-hasheo se debe hacer en el momento del login.
                // Aquí solo podemos notificar.
                echo "[INFO] Se recomienda re-hashear en el próximo inicio de sesión.\n";
                $skipped_count++;

            } else {
                echo "[OK] El hash ya está actualizado. No se necesita acción.\n";
                $skipped_count++;
            }
        }
    }

    echo "\n=================================================\n";
    echo "Resumen de la migración:\n";
    echo "-------------------------------------------------\n";
    echo "Usuarios migrados exitosamente: {$migrated_count}\n";
    echo "Usuarios que ya estaban actualizados: {$skipped_count}\n";
    echo "Usuarios que fallaron y requieren acción manual: {$failed_count}\n";
    echo "-------------------------------------------------\n";
    echo "Proceso completado.\n";

} catch (Exception $e) {
    echo "\n\nERROR CRÍTICO: " . $e->getMessage() . "\n";
    die("El script se detuvo debido a un error.\n");
}

echo "</pre>";


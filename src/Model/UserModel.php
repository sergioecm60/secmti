<?php

namespace SecMTI\Model;

use PDO;
use DateTime;

/**
 * UserModel
 *
 * Gestiona todas las operaciones de la base de datos relacionadas con los usuarios.
 * Abstrae la lógica del controlador (API) para una mejor organización.
 */
class UserModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca un usuario por su nombre de usuario.
     *
     * @param string $username
     * @return array|null Retorna los datos del usuario o null si no se encuentra.
     */
    public function findByUsername(string $username): ?array
    { 
        // Corrección: Se añade $this y el campo 'failed_login_attempts' a la consulta.
        $stmt = $this->pdo->prepare("SELECT id, username, pass_hash, role, failed_login_attempts, lockout_until FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    /**
     * Maneja un intento de inicio de sesión fallido, incrementando el contador y bloqueando si es necesario.
     *
     * @param array $userData Los datos del usuario.
     * @param int $maxAttempts Número máximo de intentos antes del bloqueo.
     * @param int $lockoutMinutes Duración del bloqueo en minutos.
     */
    public function handleFailedLogin(array $userData, int $maxAttempts, int $lockoutMinutes): void
    {
        $newAttempts = $userData['failed_login_attempts'] + 1;

        if ($newAttempts >= $maxAttempts) {
            $sql = "UPDATE users SET failed_login_attempts = ?, lockout_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?";
            $params = [$newAttempts, $lockoutMinutes, $userData['id']];
        } else {
            $sql = "UPDATE users SET failed_login_attempts = ? WHERE id = ?";
            $params = [$newAttempts, $userData['id']];
        }

        $this->pdo->prepare($sql)->execute($params);
    }

    /**
     * Maneja un inicio de sesión exitoso, reiniciando los intentos fallidos y actualizando el último login.
     *
     * @param int $userId
     */
    public function handleSuccessfulLogin(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET failed_login_attempts = 0, lockout_until = NULL, last_login = NOW() WHERE id = ?"
        );
        $stmt->execute([$userId]);
    }
}
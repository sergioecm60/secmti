<?php

namespace SecMTI\Core;

class CsrfToken
{
    public function __construct()
    {
        // CSRF tokens are session-dependent, so we need to ensure session is started.
        // SessionManager should handle session_start(), so we don't need it here.
    }

    public function generate(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function get(): ?string
    {
        return $_SESSION['csrf_token'] ?? null;
    }

    public function validate(?string $token, bool $throw_exception = true): bool
    {
        $session_token = $_SESSION['csrf_token'] ?? null;

        // Validar que ambos tokens existan y sean iguales
        $is_valid = !empty($token) && !empty($session_token) && hash_equals($session_token, $token);

        if (!$is_valid && $throw_exception) {
            throw new \Exception('Token CSRF inválido o faltante', 403);
        }

        return $is_valid;
    }

    public function validateRequest(bool $throw_exception = true): bool
    {
        // Intentar obtener el token del header o POST
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;

        return $this->validate($token, $throw_exception);
    }

    public function field(): string
    {
        $token = $this->generate();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

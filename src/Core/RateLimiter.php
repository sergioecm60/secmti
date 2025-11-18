<?php

namespace SecMTI\Core;

class RateLimiter
{
    private string $ipAddress;

    public function __construct(string $ipAddress)
    {
        $this->ipAddress = $ipAddress;
    }

    /**
     * Rate limiting básico basado en sesión/IP
     *
     * @param string $action Nombre de la acción a limitar
     * @param int $max_attempts Intentos máximos permitidos
     * @param int $window_seconds Ventana de tiempo en segundos
     * @return bool True si está dentro del límite
     */
    public function check(string $action, int $max_attempts = 5, int $window_seconds = 300): bool
    {
        $key = "rate_limit_{$action}";
        $now = time();

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['attempts' => [], 'blocked_until' => 0];
        }

        // Si está bloqueado, verificar si ya pasó el tiempo
        if ($_SESSION[$key]['blocked_until'] > $now) {
            return false;
        }

        // Limpiar intentos antiguos
        $_SESSION[$key]['attempts'] = array_filter(
            $_SESSION[$key]['attempts'],
            fn($timestamp) => ($now - $timestamp) < $window_seconds
        );

        // Verificar límite
        if (count($_SESSION[$key]['attempts']) >= $max_attempts) {
            $_SESSION[$key]['blocked_until'] = $now + $window_seconds;
            error_log("Rate limit exceeded for action '$action' from IP: " . $this->ipAddress);
            return false;
        }

        // Registrar intento
        $_SESSION[$key]['attempts'][] = $now;
        return true;
    }
}

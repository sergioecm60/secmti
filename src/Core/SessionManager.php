<?php

namespace SecMTI\Core;

class SessionManager
{
    private array $config;
    private string $ipAddress; // To store the client IP address

    public function __construct(array $config, string $ipAddress)
    {
        $this->config = $config;
        $this->ipAddress = $ipAddress;
    }

    public function init(): void
    {
        // Configurar nombre de sesión
        $session_name = $this->config['session']['name'] ?? 'PORTAL_SESSID';
        session_name($session_name);

        // Extraer dominio para cookies
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if (strpos($host, ':') !== false) {
            $host = explode(':', $host)[0];
        }

        // En desarrollo (localhost/IPs), dominio vacío. En producción, el host.
        $is_local = in_array($host, ['localhost', '127.0.0.1']) ||
                    filter_var($host, FILTER_VALIDATE_IP) !== false;
        $domain = $is_local ? '' : $host;

        // Configurar parámetros de cookie de sesión
        $cookie_lifetime = $this->config['session']['cookie_lifetime'] ?? 0;
        $is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

        $cookie_params = [
            'lifetime' => $cookie_lifetime,
            'path' => '/',
            'domain' => $domain,
            'secure' => $is_https, // Solo HTTPS en producción
            'httponly' => true, // Prevenir acceso desde JavaScript
            'samesite' => 'Strict' // Strict es más seguro, Lax si hay problemas con redirects
        ];

        session_set_cookie_params($cookie_params);

        // Iniciar sesión
        session_start();

        // Inicializar sesión nueva si no existe
        if (!isset($_SESSION['created_at'])) {
            $_SESSION['created_at'] = time();
            $_SESSION['fingerprint'] = $this->generateFingerprint();
        }
    }

    public function validateSession(): void
    {
        $current_page = basename($_SERVER['PHP_SELF']);
        $public_pages = ['login.php', 'recover_password.php', 'reset_password.php', 'migrate_passwords.php']; // Added migrate_passwords.php
        $is_public_page = in_array($current_page, $public_pages);

        // Solo aplicar validaciones de sesión si el usuario está logueado y no es una página pública
        if (isset($_SESSION['user_id']) && !$is_public_page) {

            // Validar fingerprint para detectar session hijacking
            if (!$this->validateFingerprint()) {
                error_log('SECURITY: Possible session hijacking detected for user_id: ' . $_SESSION['user_id'] . ' from IP: ' . $this->ipAddress);
                $this->destroy();
                header('Location: login.php?status=security_error');
                exit;
            }

            // Validar timeout de sesión
            $timeout = $this->config['session']['timeout_seconds'] ?? 1800; // 30 min por defecto
            if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
                error_log('Session timeout for user_id: ' . $_SESSION['user_id']);
                $this->destroy();
                header('Location: login.php?status=session_expired');
                exit;
            }

            // Validar tiempo máximo absoluto de sesión (prevenir sesiones eternas)
            $max_lifetime = $this->config['session']['max_lifetime_seconds'] ?? 28800; // 8 horas
            if (isset($_SESSION['created_at']) && (time() - $_SESSION['created_at']) > $max_lifetime) {
                error_log('Max session lifetime reached for user_id: ' . $_SESSION['user_id']);
                $this->destroy();
                header('Location: login.php?status=session_expired');
                exit;
            }

            // Actualizar timestamp de última actividad
            $_SESSION['last_activity'] = time();

            // Regenerar ID de sesión periódicamente (cada 15 minutos)
            $regenerate_interval = $this->config['session']['regenerate_interval'] ?? 900;
            $last_regeneration = $_SESSION['last_regeneration'] ?? $_SESSION['created_at'] ?? time();

            if ((time() - $last_regeneration) > $regenerate_interval) {
                $this->regenerateId(true);
                $_SESSION['last_regeneration'] = time();
            }
        }
    }

    private function generateFingerprint(): string
    {
        $components = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
        ];
        return hash('sha256', implode('|', $components));
    }

    private function validateFingerprint(): bool
    {
        if (!isset($_SESSION['fingerprint'])) {
            return true; // Primera validación, aceptar
        }

        $current_fingerprint = $this->generateFingerprint();
        return hash_equals($_SESSION['fingerprint'], $current_fingerprint);
    }

    public function regenerateId(bool $delete_old_session = true): void
    {
        // Guardar datos importantes antes de regenerar
        $data_to_preserve = [];
        $preserve_keys = ['user_id', 'user_role', 'username', 'user_email'];

        foreach ($preserve_keys as $key) {
            if (isset($_SESSION[$key])) {
                $data_to_preserve[$key] = $_SESSION[$key];
            }
        }

        // Regenerar ID de sesión
        session_regenerate_id($delete_old_session);

        // Restaurar datos preservados
        foreach ($data_to_preserve as $key => $value) {
            $_SESSION[$key] = $value;
        }

        // Actualizar timestamp y fingerprint
        $_SESSION['last_activity'] = time();
        $_SESSION['fingerprint'] = $this->generateFingerprint();
        $_SESSION['created_at'] = $_SESSION['created_at'] ?? time();

        // Regenerar token CSRF (this will be moved to CsrfToken class later)
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public function destroy(): void
    {
        $_SESSION = [];

        // Eliminar cookie de sesión
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}

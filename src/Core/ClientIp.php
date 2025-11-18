<?php

namespace SecMTI\Core;

class ClientIp
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Obtiene la IP real del cliente de forma segura
     * Solo confía en proxies configurados explícitamente
     *
     * @return string IP validada o 'unknown'
     */
    public function get(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Solo confiar en X-Forwarded-For si el request viene de un proxy confiable
        $trusted_proxies = $this->config['security']['trusted_proxies'] ?? [];

        if (!empty($trusted_proxies) && in_array($ip, $trusted_proxies, true)) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if (!empty($forwarded)) {
                $ips = array_map('trim', explode(',', $forwarded));
                // Tomar la PRIMERA IP pública de la cadena (la IP del cliente real)
                foreach ($ips as $forwarded_ip) {
                    $validated = filter_var(
                        $forwarded_ip,
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                    );
                    if ($validated !== false) {
                        $ip = $validated;
                        break;
                    }
                }
            }
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ?: 'unknown';
    }
}

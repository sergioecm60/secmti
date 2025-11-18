<?php

namespace SecMTI\Core;

class SecurityHeaders
{
    private array $config;
    private bool $isHttps;
    private bool $isProduction;

    public function __construct(array $config, bool $isHttps, bool $isProduction)
    {
        $this->config = $config;
        $this->isHttps = $isHttps;
        $this->isProduction = $isProduction;
    }

    public function sendHeaders(): void
    {
        // Control de referrer
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // XSS Protection (legacy, pero no hace daño)
        header('X-XSS-Protection: 1; mode=block');

        // Permissions Policy (reemplaza Feature-Policy)
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        // HSTS (solo en HTTPS)
        if ($this->isHttps && $this->isProduction) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }

        // Las cabeceras más comunes se establecen aquí.
        // Páginas específicas pueden añadir o sobrescribir las suyas.
        header('Content-Type: text/html; charset=utf-8');
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');

        // NOTA: La cabecera Content-Security-Policy (CSP) es muy específica
        // y se sigue gestionando en cada página individualmente para mayor control
        // (ej: index.php, index2.php, diag_x9k2.php, etc.).
    }
}

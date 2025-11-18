<?php

namespace SecMTI\Core;

class SecurityLogger
{
    private string $logFile;
    private string $ipAddress;

    public function __construct(string $logFile, string $ipAddress)
    {
        $this->logFile = $logFile;
        $this->ipAddress = $ipAddress;
    }

    /**
     * Log de eventos de seguridad
     *
     * @param string $event Tipo de evento
     * @param string $details Detalles del evento
     */
    public function log(string $event, string $details = ''): void
    {
        $log_dir = dirname($this->logFile);

        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0750, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $user_id = $_SESSION['user_id'] ?? 'anonymous';

        $message = "[{$timestamp}] {$event} | User: {$user_id} | IP: {$this->ipAddress} | {$details}\n";
        @error_log($message, 3, $this->logFile);
    }
}


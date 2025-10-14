<?php
/**
 * config.example.php - Template de Configuración
 * 
 * INSTRUCCIONES:
 * 1. Copiar este archivo a config.php
 * 2. Reemplazar los valores de ejemplo con valores reales
 * 3. NUNCA commitear config.php al repositorio
 */

return [
    'security' => [
        'encryption_key' => '', // Generar con: openssl rand -base64 32
    ],
    
    'database' => [
        'host' => 'localhost',
        'name' => 'nombre_base_datos',
        'user' => 'usuario_db',
        'pass' => 'contraseña_segura_aqui',
    ],
    
    // Nuevo: Analytics
    'analytics' => [
        'google_id' => '', // GA4: G-XXXXXXXXXX o vacío para deshabilitar
    ],

    // ... resto igual pero SIN credenciales reales
];
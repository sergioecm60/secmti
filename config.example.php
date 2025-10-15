<?php
/**
 * /config.example.php - Archivo de Configuración de Ejemplo
 * 
 * INSTRUCCIONES:
 * 1. Copiar este archivo como config.php:
 *    cp config.example.php config.php
 * 
 * 2. Editar config.php con tus datos reales
 * 
 * 3. NUNCA subir config.php a Git (está en .gitignore)
 * 
 * IMPORTANTE: Este es solo un template. Los valores aquí son ejemplos.
 */

return array (
  // ============================================================================
  // SEGURIDAD
  // ============================================================================
  'security' => array (
    'encryption_key' => 'GENERA_UNA_CLAVE_ALEATORIA_DE_44_CARACTERES_BASE64',
    // Genera tu propia clave con: base64_encode(random_bytes(32))
    'max_login_attempts' => 5,
    'lockout_minutes' => 15,
  ),
  
  'session' => array (
    'timeout_seconds' => 1800,  // 30 minutos
    'name' => 'PORTAL_SESSID',
  ),

  // ============================================================================
  // BASE DE DATOS
  // ============================================================================
  'database' => array (
    'host' => 'localhost',
    'name' => 'portal_db',
    'user' => 'TU_USUARIO_MYSQL',        // ← CAMBIAR
    'pass' => 'TU_PASSWORD_MYSQL',       // ← CAMBIAR
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_spanish_ci',
  ),

  // ============================================================================
  // PÁGINA DE ATERRIZAJE (index.php)
  // ============================================================================
  'landing_page' => array (
    'company_name' => 'Nombre de tu Empresa',
    'logo_path' => 'assets/images/logo.png',
    
    'sales_title' => 'Contacto',
    'phone_numbers' => array (
      '(011) 1234-5678',
      '0800-XXX-XXXX',
    ),
    
    'locations_title' => 'Sucursales',
    'branches' => array (
      'Dirección sucursal 1, Ciudad, Provincia',
      'Dirección sucursal 2, Ciudad, Provincia',
    ),
    
    'social_title' => 'Encontranos en:',
    'social_links' => array (
      'facebook' => array (
        'label' => 'Facebook',
        'url' => 'https://www.facebook.com/tu-pagina',
        'svg_path' => 'M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v2.385z',
      ),
      'instagram' => array (
        'label' => 'Instagram',
        'url' => 'https://www.instagram.com/tu-perfil',
        'svg_path' => 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.85s-.011 3.584-.069 4.85c-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07s-3.584-.012-4.85-.07c-3.252-.148-4.771-1.691-4.919-4.919-.058-1.265-.069-1.645-.069-4.85s.011-3.584.069-4.85c.149-3.225 1.664 4.771 4.919-4.919 1.266.058 1.644.07 4.85.07zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948s.014 3.667.072 4.947c.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072s3.667-.014 4.947-.072c4.358-.2 6.78-2.618 6.98-6.98.059-1.281.073-1.689.073-4.948s-.014-3.667-.072-4.947c-.2-4.358-2.618-6.78-6.98-6.98-1.281-.058-1.689-.072-4.948-.072zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.162 6.162 6.162 6.162-2.759 6.162-6.162-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4s1.791-4 4-4 4 1.79 4 4-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44 1.441-.645 1.441-1.44-.645-1.44-1.441-1.44z',
      ),
    ),
    
    'main_sites_title' => 'Sites',
    'main_sites' => array (
      'website' => array (
        'url' => 'https://www.tuempresa.com',
        'label' => 'Sitio Web Oficial',
        'svg_path' => 'M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z',
      ),
      'intranet' => array (
        'url' => 'index2.php',
        'label' => 'Acceso Intranet',
        'svg_path' => 'M20 8h-3V6h3v2zm-5 0h-3V6h3v2zm-5 0H7V6h3v2zm10 4h-3v-2h3v2zm-5 0h-3v-2h3v2zm-5 0H7v-2h3v2zm10 4h-3v-2h3v2zm-5 0h-3v-2h3v2zm-5 0H7v-2h3v2zM4 18h3v-2H4v2zm5 0h3v-2H9v2zm5 0h3v-2h-3v2zm5-14H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2z',
      ),
    ),
  ),

  // ============================================================================
  // SERVICIOS DEL PORTAL (index2.php)
  // ============================================================================
  'services' => array (
    'servicio_ejemplo_1' => array (
      'label' => 'Servidor Local',
      'url' => 'http://192.168.0.10',
      'category' => 'LAN',
      'requires_login' => true,
      'redirect' => false,
    ),
    'servicio_ejemplo_2' => array (
      'label' => 'Panel Web Externo',
      'url' => 'https://panel.tuempresa.com:8443',
      'category' => 'WAN',
      'requires_login' => true,
      'redirect' => false,
    ),
  ),

  // ============================================================================
  // FOOTER
  // ============================================================================
  'footer' => array (
    'line1' => 'SECMTI - Soluciones TI',
    'line2' => 'By Sergio Cabrera | Copyleft (C) ' . date('Y'),
    'license_url' => 'license.php',
    'whatsapp_number' => '+54911XXXXXXXX',  // ← CAMBIAR
    'whatsapp_svg_path' => 'M19.11 4.93C17.22 3.04 14.69 2 12 2C6.48 2 2 6.48 2 12c0 1.77.46 3.45 1.27 4.95L2 22l5.05-1.27c1.5.81 3.18 1.27 4.95 1.27h.01c5.52 0 10-4.48 10-10c0-2.69-1.04-5.22-2.93-7.11zM12 20.01c-1.61 0-3.14-.4-4.51-1.13l-.32-.19l-3.34.84l.86-3.27l-.22-.34c-.8-1.4-1.22-2.99-1.22-4.63c0-4.42 3.58-8 8-8s8 3.58 8 8c0 4.42-3.58 8-8 8.01zm4.49-6.18c-.27-.13-1.59-.78-1.84-.88c-.25-.09-.43-.13-.62.13c-.19.27-.7.88-.86 1.06c-.16.19-.32.21-.59.08c-.27-.13-1.15-.42-2.19-1.35c-.81-.73-1.35-1.64-1.51-1.92c-.16-.27-.02-.43.12-.56c.12-.12.27-.32.41-.48c.14-.16.19-.27.29-.46c.1-.19.05-.36-.02-.48c-.08-.13-.62-1.49-.84-2.04c-.23-.55-.46-.48-.62-.48c-.15 0-.32-.03-.49-.03c-.17 0-.43.05-.66.32c-.22.27-.86.84-.86 2.04c0 1.2.88 2.36 1 2.53c.12.18 1.73 2.64 4.2 3.72c.59.26 1.05.41 1.41.53c.59.19 1.13.16 1.56.1c.48-.07 1.59-.65 1.81-1.27c.22-.62.22-1.16.16-1.27c-.07-.12-.25-.19-.52-.32z',
  ),
);
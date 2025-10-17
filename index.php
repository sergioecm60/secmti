<?php
/**
 * index.php - Landing Page Corporativa
 * 
 * Página de presentación pública de la empresa con información
 * de contacto, sucursales y enlaces a redes sociales.
 * 
 * CARACTERÍSTICAS:
 * - SEO optimizado con Open Graph y Schema.org
 * - Accesibilidad WCAG 2.1 AA
 * - Performance optimizada
 * - Cache agresivo para assets estáticos
 */

// Incluir el archivo de inicialización central.
require_once 'bootstrap.php';

// ============================================================================
// VALIDACIÓN DE CONFIGURACIÓN
// ============================================================================

// Validar que existan las claves necesarias
$required_keys = ['company_name', 'branches', 'phone_numbers', 'social_links', 'main_sites'];
foreach ($required_keys as $key) {
    if (!isset($config['landing_page'][$key])) {
        error_log("CRITICAL: Clave 'landing_page.{$key}' faltante en config");
        http_response_code(503);
        die('Error de configuración del sitio.');
    }
}

// ============================================================================
// CABECERAS HTTP
// ============================================================================

// Sobrescribir solo las cabeceras específicas de esta página
// Las demás ya vienen de bootstrap.php

// Cache agresivo para landing page (1 día)
header("Cache-Control: public, max-age=86400, stale-while-revalidate=604800");
header("Expires: " . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');

// ETag para validación de caché
$etag = md5_file(__FILE__);
header("ETag: \"{$etag}\"");

// Verificar If-None-Match para 304 Not Modified
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && 
    trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') === $etag) {
    http_response_code(304);
    exit;
}

// CSP específica para landing page
$csp = "default-src 'self'; " .
       "script-src 'self'; " .
       "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
       "font-src 'self' https://fonts.gstatic.com; " .
       "img-src 'self' data: https:; " .
       "connect-src 'self'; " .
       "frame-ancestors 'none';";
header("Content-Security-Policy: {$csp}");

// ============================================================================
// DATOS DE LA PÁGINA
// ============================================================================

$company_name = htmlspecialchars($config['landing_page']['company_name'], ENT_QUOTES, 'UTF-8');
$page_title = $company_name . ' - Soluciones TI';
$page_description = "Contacto y sucursales de {$company_name}. Soluciones en tecnología de la información.";
$canonical_url = (defined('BASE_URL') ? BASE_URL : 'https://' . $_SERVER['HTTP_HOST']) . '/';

// Schema.org LocalBusiness JSON-LD
$schema_org = [
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => $config['landing_page']['company_name'],
    'url' => $canonical_url,
    'logo' => $canonical_url . ($config['landing_page']['logo_path'] ?? 'assets/images/logo.png'),
    'contactPoint' => [
        '@type' => 'ContactPoint',
        'telephone' => $config['landing_page']['phone_numbers'][0] ?? '',
        'contactType' => 'customer service',
        'areaServed' => 'AR',
        'availableLanguage' => 'Spanish'
    ],
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => $config['landing_page']['branches'][0] ?? '',
        'addressCountry' => 'AR'
    ],
    'sameAs' => array_column($config['landing_page']['social_links'], 'url')
];

// --- Carga dinámica desde .env para teléfonos y sucursales ---
$phone_numbers_str = $_ENV['PHONE_NUMBERS'] ?? '';
$config['landing_page']['phone_numbers'] = !empty($phone_numbers_str) ? array_map('trim', explode(',', $phone_numbers_str)) : [];

$branches_str = $_ENV['BRANCHES'] ?? '';
$config['landing_page']['branches'] = !empty($branches_str) ? array_map('trim', explode(',', $branches_str)) : [];

// Actualizar schema con los datos cargados
$schema_org['contactPoint']['telephone'] = $config['landing_page']['phone_numbers'][0] ?? '';
$schema_org['address']['streetAddress'] = $config['landing_page']['branches'][0] ?? '';
?>
<!DOCTYPE html>
<html lang="es" prefix="og: https://ogp.me/ns#">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- SEO Básico -->
    <title><?= $page_title ?></title>
    <meta name="description" content="<?= htmlspecialchars($page_description) ?>">
    <meta name="keywords" content="tecnología, IT, sistemas, <?= $company_name ?>">
    <meta name="author" content="<?= $company_name ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= htmlspecialchars($canonical_url) ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($canonical_url) ?>">
    <meta property="og:title" content="<?= $page_title ?>">
    <meta property="og:description" content="<?= htmlspecialchars($page_description) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($canonical_url . ($config['landing_page']['logo_path'] ?? 'assets/images/logo.png')) ?>">
    <meta property="og:locale" content="es_AR">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= htmlspecialchars($canonical_url) ?>">
    <meta name="twitter:title" content="<?= $page_title ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($page_description) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($canonical_url . ($config['landing_page']['logo_path'] ?? 'assets/images/logo.png')) ?>">
    
    <!-- Favicon -->
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="assets/images/favicon.ico" type="image/x-icon">
    
    <!-- DNS Prefetch & Preconnect -->
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Preload Critical Resources -->
    <link rel="preload" href="assets/css/main.css" as="style">
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" as="style">
    
    <!-- Stylesheets -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/landing.css">
    
    <!-- Theme Style (si existe) -->
    <?php 
    $theme_file = __DIR__ . '/templates/theme_style.php';
    if (file_exists($theme_file) && is_readable($theme_file)) {
        try {
            require_once $theme_file;
        } catch (Exception $e) {
            error_log("Error cargando theme_style.php: " . $e->getMessage());
        }
    }
    ?>
    
    <!-- Structured Data (Schema.org) -->
    <script type="application/ld+json">
    <?= json_encode($schema_org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
    </script>
    
    <!-- PWA y Configuración Móvil -->
    <meta name="theme-color" content="#4b6cb7">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($config['landing_page']['company_name'] ?? 'SecMTI') ?>">
</head>
<body class="page-landing">
    <!-- Skip to main content (Accesibilidad) -->
    <a href="#main-content" class="skip-link">Saltar al contenido principal</a>
    
    <main id="main-content" class="container" role="main">
        <!-- Header con Logo -->
        <header class="header info-section" role="banner">
            <?php if (!empty($config['landing_page']['logo_path'])): ?>
                <img src="<?= htmlspecialchars($config['landing_page']['logo_path']) ?>" 
                     alt="<?= $company_name ?> - Logo" 
                     class="company-logo"
                     width="200" 
                     height="80">
            <?php endif; ?>
            <h1><?= $company_name ?></h1>
        </header>

        <!-- Sucursales -->
        <section class="locations info-section" aria-labelledby="locations-heading">
            <h2 id="locations-heading">
                <?= htmlspecialchars($config['landing_page']['locations_title'] ?? 'Sucursales') ?>
            </h2>
            <address itemscope itemtype="https://schema.org/PostalAddress">
                <?php foreach ($config['landing_page']['branches'] as $branch): ?>
                    <p>
                        <span aria-hidden="true">🌍</span>
                        <span itemprop="streetAddress"><?= htmlspecialchars($branch) ?></span>
                    </p>
                <?php endforeach; ?>
            </address>
        </section>

        <!-- Contacto -->
        <section class="contact info-section" aria-labelledby="contact-heading">
            <h2 id="contact-heading">
                <?= htmlspecialchars($config['landing_page']['sales_title'] ?? 'Contacto') ?>
            </h2>
            <?php foreach ($config['landing_page']['phone_numbers'] as $phone): ?>
                <p>
                    <span aria-hidden="true">📞</span>
                    <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $phone)) ?>" 
                       itemprop="telephone">
                        <?= htmlspecialchars($phone) ?>
                    </a>
                </p>
            <?php endforeach; ?>
        </section>

        <!-- Contenedor para las secciones en paralelo -->
        <div class="parallel-sections">
            <!-- Redes Sociales -->
            <section class="social-icons info-section" aria-labelledby="social-heading">
                <h2 id="social-heading">
                    <?= htmlspecialchars($config['landing_page']['social_title'] ?? 'Redes Sociales') ?>
                </h2>
                <nav aria-label="Redes sociales">
                    <div class="icon-wrapper" role="list">
                        <?php foreach ($config['landing_page']['social_links'] as $name => $link): ?>
                            <a href="<?= htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') ?>" 
                               class="social-link"
                               aria-label="<?= htmlspecialchars($link['label']) ?>"
                               role="listitem"
                               <?php if ($name !== 'email'): ?>
                                   target="_blank" 
                                   rel="noopener noreferrer"
                               <?php endif; ?>>
                                <svg class="icon" 
                                     role="img" 
                                     xmlns="http://www.w3.org/2000/svg" 
                                     width="24" 
                                     height="24" 
                                     viewBox="0 0 24 24" 
                                     fill="currentColor"
                                     aria-hidden="true">
                                    <title><?= htmlspecialchars($link['label']) ?></title>
                                    <path d="<?= htmlspecialchars($link['svg_path'], ENT_QUOTES, 'UTF-8') ?>"/>
                                </svg>
                                <span class="sr-only"><?= htmlspecialchars($link['label']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </nav>
            </section>

            <!-- Sitios Principales -->
            <section class="main-sites info-section" aria-labelledby="sites-heading">
                <h2 id="sites-heading">
                    <?= htmlspecialchars($config['landing_page']['main_sites_title'] ?? 'Sitios Principales') ?>
                </h2>
                <nav aria-label="Sitios principales">
                    <div class="icon-wrapper" role="list">
                        <?php foreach ($config['landing_page']['main_sites'] as $name => $site): ?>
                            <a href="<?= htmlspecialchars($site['url'], ENT_QUOTES, 'UTF-8') ?>" 
                               class="site-link"
                               aria-label="<?= htmlspecialchars($site['label']) ?>"
                               role="listitem"
                               <?php if ($name !== 'intranet'): ?>
                                   target="_blank" 
                                   rel="noopener noreferrer"
                               <?php endif; ?>>
                                <svg class="icon" 
                                     role="img" 
                                     xmlns="http://www.w3.org/2000/svg" 
                                     width="24" 
                                     height="24" 
                                     viewBox="0 0 24 24" 
                                     fill="currentColor"
                                     aria-hidden="true">
                                    <title><?= htmlspecialchars($site['label']) ?></title>
                                    <path d="<?= htmlspecialchars($site['svg_path'], ENT_QUOTES, 'UTF-8') ?>"/>
                                </svg>
                                <span class="sr-only"><?= htmlspecialchars($site['label']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </nav>
            </section>
        </div>
    </main>
    
    <!-- Footer -->
    <?php 
    $footer_file = __DIR__ . '/templates/footer.php';
    if (file_exists($footer_file) && is_readable($footer_file)) {
        require_once $footer_file;
    }
    ?>

    <!-- Google Analytics (opcional, agregar en config) -->
    <?php if (!empty($config['analytics']['google_id'])): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($config['analytics']['google_id']) ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '<?= htmlspecialchars($config['analytics']['google_id']) ?>');
    </script>
    <?php endif; ?>
</body>
</html>
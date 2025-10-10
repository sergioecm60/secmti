<?php
// index.php - Página de presentación de la empresa

// Incluir el archivo de inicialización central.
require_once 'bootstrap.php';

// Establecer cabeceras de seguridad y de contenido.
header('Content-Type: text/html; charset=utf-8');
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Cache-Control: public, max-age=3600");
// CSP para esta página: permite estilos y fuentes de Google.
header("Content-Security-Policy: default-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index, follow">
    <meta name="description" content="Página de contacto y sucursales de <?= htmlspecialchars($config['landing_page']['company_name']) ?>">
    <title><?= htmlspecialchars($config['landing_page']['company_name']) ?></title>
    <base href="/secmti/">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/landing.css">
    <?php if (file_exists('templates/theme_style.php')) require_once 'templates/theme_style.php'; ?>
</head>
<body class="page-info-theme">
    <main class="container">
        <header class="header info-section">
            <h1><?= htmlspecialchars($config['landing_page']['company_name']) ?></h1>
        </header>

        <section class="locations info-section">
            <h2><?= htmlspecialchars($config['landing_page']['locations_title']) ?></h2>
            <address>
                <?php foreach ($config['landing_page']['branches'] as $branch): ?>
                    <p>🌍 <?= htmlspecialchars($branch) ?></p>
                <?php endforeach; ?>
            </address>
        </section>

        <section class="contact info-section">
            <h2><?= htmlspecialchars($config['landing_page']['sales_title']) ?></h2>
            <?php foreach ($config['landing_page']['phone_numbers'] as $phone): ?>
                <p>📞 <?= htmlspecialchars($phone) ?></p>
            <?php endforeach; ?>
        </section>

        <!-- Contenedor para las secciones en paralelo -->
        <div class="parallel-sections">
            <section class="social-icons info-section">
                <h2><?= htmlspecialchars($config['landing_page']['social_title']) ?></h2>
                <div class="icon-wrapper">
                    <?php foreach ($config['landing_page']['social_links'] as $name => $link): ?>
                        <a href="<?= htmlspecialchars($link['url']) ?>" 
                           aria-label="<?= htmlspecialchars($link['label']) ?>" 
                           <?php if ($name !== 'email'): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>>
                            <svg class="icon" role="img" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="<?= $link['svg_path'] ?>"/>
                            </svg>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="main-sites info-section">
                <h2><?= htmlspecialchars($config['landing_page']['main_sites_title'] ?? 'Sitios Principales') ?></h2>
                <div class="icon-wrapper">
                    <?php foreach ($config['landing_page']['main_sites'] as $name => $site): ?>
                        <a href="<?= htmlspecialchars($site['url']) ?>" 
                           aria-label="<?= htmlspecialchars($site['label']) ?>" 
                           <?php if ($name !== 'intranet'): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>>
                            <svg class="icon" role="img" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="<?= $site['svg_path'] ?>"/>
                            </svg>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </main>
    
    <?php require_once 'templates/footer.php'; ?>

</body>
</html>
<?php
// index2.php - Pagina principal con acceso seguro

// Incluir el archivo de inicialización central.
// Este se encarga de la configuración, sesión, cabeceras de seguridad e IP.
require_once 'bootstrap.php';

// La cabecera CSP es específica para esta página.
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self';");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Servidor Intranet Grupo Pedraza - Soluciones Linux personalizadas" />
    <meta name="robots" content="noindex, nofollow" />
    <title>Servidor Intranet Grupo Pedraza</title>
    <base href="/secmti/">
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="page-index2">
    <div class="container" role="main">
        <div class="main-title">
            Bienvenido al Servidor <?= htmlspecialchars($config['landing_page']['company_name'] ?? 'Intranet') ?><br>
            <span><?= htmlspecialchars($config['landing_page']['company_name'] ?? 'Mi Compañía') ?></span><br>
            Este servidor tiene activada la seguridad y rastreo por IP.
        </div>

        <div class="ip">
            Su IP es <strong><?= htmlspecialchars(IP_ADDRESS, ENT_QUOTES, 'UTF-8') ?></strong>
        </div>

        <img src="<?= htmlspecialchars($config['landing_page']['logo_path'] ?? '') ?>" alt="Logo de <?= htmlspecialchars($config['landing_page']['company_name'] ?? '') ?>" class="logo" onerror="this.style.display='none'" />

        <!-- Menú de servicios -->
        <nav class="menu" aria-label="Menu de servicios">
            <?php
            $servicios = $config['services'];
            foreach ($servicios as $id => $servicio) {
                echo '<button id="' . htmlspecialchars($id) . '">' . htmlspecialchars($servicio['label']) . '</button>';
            }
            ?>
        </nav>

        <!-- Botón para volver a la página principal -->
        <a href="index.php" class="back-to-home-btn">← Volver a la Página Principal</a>

        <!-- Pie de página unificado -->
        <footer class="footer">
            <strong><?= htmlspecialchars($config['footer']['line1'] ?? '') ?></strong><br>
            <div class="footer-contact-line">
                <span><?= htmlspecialchars($config['footer']['line2'] ?? '') ?></span>
                <?php if (!empty($config['footer']['whatsapp_number']) && !empty($config['footer']['whatsapp_svg_path'])): ?>
                    <a href="https://wa.me/<?= htmlspecialchars($config['footer']['whatsapp_number']) ?>" target="_blank" rel="noopener noreferrer" class="footer-whatsapp-link" aria-label="Contactar por WhatsApp" tabindex="0">
                        <svg class="icon" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="<?= $config['footer']['whatsapp_svg_path'] ?>"/>
                        </svg>
                        <span><?= htmlspecialchars($config['footer']['whatsapp_number']) ?></span>
                    </a>
                <?php endif; ?>
            </div>
            <a href="<?= htmlspecialchars($config['footer']['license_url'] ?? '#') ?>" target="_blank" rel="license">Términos y Condiciones (Licencia GNU GPL v3)</a>
        </footer>
    </div>

    <!-- Modal de Login -->
    <div id="loginModal" aria-hidden="true" role="dialog">
        <div class="login-modal">
            <h3 class="login-modal-title">Acceso protegido</h3>
            <input id="usuarioInput" class="login-modal-input" type="text" placeholder="Usuario" autocomplete="username" autofocus />
            <input id="passInput" class="login-modal-input" type="password" placeholder="Contraseña" autocomplete="current-password" />
            <!-- Elementos del Captcha -->
            <div id="captchaQuestion" class="login-modal-captcha-question"></div>
            <input id="captchaInput" class="login-modal-input" type="text" placeholder="Respuesta del Captcha" autocomplete="off" />
            <!-- Fin de Elementos del Captcha -->
            <button id="loginBtn" class="login-modal-button">Ingresar</button>
            <div id="loginError" class="login-modal-error" role="alert">Usuario o contraseña incorrectos.</div>
        </div>
    </div>

    <!-- Datos para JavaScript -->
    <script type="application/json" id="page-data">
        <?= json_encode([
            'isUserLoggedIn' => isset($_SESSION['acceso_info']) && $_SESSION['acceso_info'] === true,
            'services' => $config['services'] ?? []
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
    </script>

    <!-- Script principal -->
    <script src="assets/js/main.js" defer></script>
</body>
</html>
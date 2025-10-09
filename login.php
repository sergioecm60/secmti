<?php
// login.php - Página de inicio de sesión para el portal.

require_once 'bootstrap.php';

// Si el usuario ya está logueado, redirigir al portal.
if (!empty($_SESSION['user_id'])) {
    header('Location: index2.php');
    exit;
}

$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self';");

?>
<!DOCTYPE html>
<html lang="es" data-theme="dark"> <!-- Iniciar en tema oscuro por defecto -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Portal - <?= htmlspecialchars($config['landing_page']['company_name'] ?? 'SECM') ?></title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="modern-login-body">
    <main class="login-main-content">
        <div class="login-container">
            <div class="login-header">
                <img src="<?= htmlspecialchars($config['landing_page']['logo_path'] ?? 'assets/images/logo.png') ?>" alt="Logo de <?= htmlspecialchars($config['landing_page']['company_name'] ?? 'SECM') ?>" class="login-logo">
                <p><?= htmlspecialchars($config['landing_page']['company_name'] ?? 'SECM') ?></p>
                <small>Portal de Servicios Seguros</small>
            </div>

            <div id="login-error" class="login-error" role="alert"></div>

            <form id="loginForm">
                <div class="form-group">
                    <label for="username">👤 Usuario</label>
                    <input type="text" id="username" name="username" required placeholder="Ingrese su usuario" autocomplete="username" autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">🔒 Contraseña</label>
                    <input type="password" id="password" name="password" required placeholder="Ingrese su contraseña" autocomplete="current-password">
                </div>
                
                <div class="form-group">
                    <label for="captcha">🤖 Verificación</label>
                    <div class="captcha-container">
                        <span id="captcha-question" class="captcha-question">Cargando...</span>
                        <input type="text" id="captcha" name="captcha" class="captcha-input" required placeholder="Respuesta" autocomplete="off">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-login">🔐 Iniciar Sesión</button>
            </form>
        </div>
    </main>

    <footer class="footer">
        <strong><?= htmlspecialchars($config['footer']['line1'] ?? '') ?></strong><br>
        <div class="footer-contact-line">
            <span><?= htmlspecialchars($config['footer']['line2'] ?? '') ?></span>
            <?php if (!empty($config['footer']['whatsapp_number']) && !empty($config['footer']['whatsapp_svg_path'])): ?>
                <a href="https://wa.me/<?= htmlspecialchars($config['footer']['whatsapp_number']) ?>" target="_blank" rel="noopener noreferrer" class="footer-whatsapp-link" aria-label="Contactar por WhatsApp" tabindex="0">
                    <svg class="icon" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="<?= $config['footer']['whatsapp_svg_path'] ?>"/>
                    </svg>
                </a>
            <?php endif; ?>
        </div>
        <a href="<?= htmlspecialchars($config['footer']['license_url'] ?? '#') ?>" target="_blank" rel="license">Términos y Condiciones</a>
    </footer>

    <script nonce="<?= htmlspecialchars($nonce) ?>">
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('loginForm');
            const errorDiv = document.getElementById('login-error');
            const captchaQuestionSpan = document.getElementById('captcha-question');

            // Ocultar logo si no se puede cargar
            const logo = document.querySelector('.login-logo');
            if (logo) {
                logo.addEventListener('error', function() {
                    this.classList.add('hidden');
                });
            }

            async function loadCaptcha() {
                try {
                    const response = await fetch('api/auth.php?action=get_captcha');
                    const data = await response.json();
                    if (data.question) {
                        captchaQuestionSpan.textContent = data.question;
                    } else {
                        captchaQuestionSpan.textContent = 'Error al cargar.';
                    }
                } catch (e) {
                    captchaQuestionSpan.textContent = 'Error de red.';
                }
            }

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                errorDiv.classList.remove('active');

                const formData = new FormData(form);
                const data = {
                    username: formData.get('username'),
                    password: formData.get('password'),
                    captcha: formData.get('captcha')
                };

                try {
                    const response = await fetch('api/auth.php?action=login', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();

                    if (response.ok && result.success) {
                        window.location.href = result.redirect || 'index2.php';
                    } else {
                        errorDiv.textContent = result.message || 'Ha ocurrido un error.';
                        errorDiv.classList.add('active');
                        loadCaptcha(); // Cargar nueva pregunta
                    }
                } catch (error) {
                    errorDiv.textContent = 'Error de conexión. Intente de nuevo.';
                    errorDiv.classList.add('active');
                }
            });

            loadCaptcha();
        });
    </script>
</body>
</html>
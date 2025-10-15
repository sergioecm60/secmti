<?php
// license.php - Versión mejorada: segura, con estilo y cambio de idioma

// Determinar idioma (por parámetro o por cookie)
$lang = $_GET['lang'] ?? $_COOKIE['license_lang'] ?? 'es';
if (!in_array($lang, ['es', 'en'])) {
    $lang = 'es';
}
setcookie('license_lang', $lang, time() + 31536000, '/', '', true, true); // HTTP-only, secure

// Ruta al archivo corto (solo aviso de licencia)
$licenseFile = __DIR__ . '/LICENSE.' . $lang . '.txt';

if (!file_exists($licenseFile) || !is_readable($licenseFile)) {
    http_response_code(404);
    die('Archivo de licencia no encontrado.');
}

$content = htmlspecialchars(file_get_contents($licenseFile), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Licencia - SECMTI</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #212529;
            line-height: 1.6;
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-top: 1rem;
        }
        h1 {
            color: #0d6efd;
            margin-bottom: 1.5rem;
        }
        pre {
            white-space: pre-wrap;
            background: #f1f3f5;
            padding: 1.5rem;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .btn-group {
            margin: 1.5rem 0;
        }
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #e9ecef;
            color: #495057;
            text-decoration: none;
            border-radius: 6px;
            margin-right: 0.5rem;
            transition: all 0.2s;
        }
        .btn.active {
            background: #0d6efd;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
        }
        footer {
            margin-top: 2rem;
            text-align: center;
            color: #6c757d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <h1>📜 Licencia del Software</h1>

    <div class="btn-group">
        <a href="?lang=es" class="btn <?= $lang === 'es' ? 'active' : '' ?>">Español</a>
        <a href="?lang=en" class="btn <?= $lang === 'en' ? 'active' : '' ?>">English</a>
    </div>

    <div class="card">
        <pre><?= $content ?></pre>
    </div>

    <footer>
        <p>SECMTI &copy; 2025 — Software Libre bajo <a href="https://www.gnu.org/licenses/gpl-3.0.html" target="_blank">GNU GPL v3</a></p>
    </footer>
</body>
</html>
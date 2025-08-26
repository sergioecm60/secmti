<?php
// c:\laragon\www\create_hash.php
// Script INTERACTIVO para generar un hash de contraseña seguro para config.php

$page_title = "Generador de Hash de Contraseña";
$hash_result = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['password'])) {
    $password = $_POST['password'];
    $hash_result = password_hash($password, PASSWORD_DEFAULT);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <style>
        body { font-family: sans-serif; background-color: #f4f4f9; color: #333; margin: 20px; }
        .container { max-width: 600px; margin: 40px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2 { color: #0056b3; }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        input[type="password"], input[type="text"] { width: calc(100% - 22px); padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
        button { background-color: #0056b3; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin-top: 10px; }
        button:hover { background-color: #004494; }
        .result { background: #e9ecef; padding: 15px; border-radius: 4px; margin-top: 20px; word-wrap: break-word; }
        .warning { color: #d9534f; font-weight: bold; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1><?= $page_title ?></h1>
        <p>Usa este formulario para crear un hash seguro para tu contraseña.</p>
        
        <form method="POST" action="">
            <label for="password">Ingresa la contraseña:</label>
            <input type="password" id="password" name="password" required autofocus>
            <button type="submit">Generar Hash</button>
        </form>

        <?php if ($hash_result): ?>
            <div class="result">
                <h2>¡Hash Generado!</h2>
                <p>Copia el siguiente texto y pégalo en tu archivo <code>config.php</code>, en el valor de <strong>'pass_hash'</strong>.</p>
                <pre><strong><?= htmlspecialchars($hash_result) ?></strong></pre>
            </div>
        <?php endif; ?>

        <p class="warning">
            ¡MUY IMPORTANTE! Una vez que hayas copiado el hash, borra este archivo (<code>create_hash.php</code>) del servidor por seguridad.
        </p>
    </div>
</body>
</html>
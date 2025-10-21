<?php
// hosting_manager.php - Gestor de servidores de hosting (cPanel/WHM)

require_once 'bootstrap.php';

$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'nonce-{$nonce}'; img-src 'self' data:;");

// Verificar autenticación y rol de administrador.
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = get_database_connection($config, true);
$status_message = '';

// --- MANEJO DE ACCIONES POST (GUARDAR, ELIMINAR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        validate_request_csrf();
        $pdo->beginTransaction();
            $action = $_POST['action'] ?? '';

            if ($action === 'save_host') {
                $host_id = $_POST['host_id'] ?? null;
                $hostname = trim($_POST['hostname'] ?? '');
                $label = trim($_POST['label'] ?? '');
                $webmail_port = (int)($_POST['webmail_port'] ?? 2096);
                $cpanel_port = (int)($_POST['cpanel_port'] ?? 2083);
                $notes = trim($_POST['notes'] ?? '');

                if (empty($hostname) || empty($label)) {
                    throw new Exception("El Hostname y la Etiqueta son obligatorios.");
                }

                if (empty($host_id) || strpos($host_id, 'new_') === 0) {
                    $stmt = $pdo->prepare("INSERT INTO dc_hosting_servers (hostname, label, webmail_port, cpanel_port, notes) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$hostname, $label, $webmail_port, $cpanel_port, $notes]);
                    $host_id = $pdo->lastInsertId();
                    $status_message = '<div class="status-message success">Servidor de hosting creado.</div>';
                } else {
                    $stmt = $pdo->prepare("UPDATE dc_hosting_servers SET hostname=?, label=?, webmail_port=?, cpanel_port=?, notes=? WHERE id=?");
                    $stmt->execute([$hostname, $label, $webmail_port, $cpanel_port, $notes, $host_id]);
                    $status_message = '<div class="status-message success">Servidor de hosting actualizado.</div>';
                }

                // --- Guardar Cuentas FTP ---
                $ftp_accounts = $_POST['ftp_accounts'] ?? [];
                $submitted_ftp_ids = [];
                foreach ($ftp_accounts as $ftp_id_key => $ftp_data) {
                    if (empty($ftp_data['username'])) continue;

                    if (strpos($ftp_id_key, 'new_') === 0) {
                        if (empty($ftp_data['password'])) continue;
                        $stmt_ftp = $pdo->prepare("INSERT INTO dc_hosting_ftp_accounts (server_id, username, password, notes) VALUES (?, ?, ?, ?)");
                        $stmt_ftp->execute([$host_id, $ftp_data['username'], encrypt_password($ftp_data['password']), $ftp_data['notes'] ?? '']);
                        $submitted_ftp_ids[] = $pdo->lastInsertId();
                    } else {
                        $stmt_ftp = $pdo->prepare("UPDATE dc_hosting_ftp_accounts SET username=?, notes=? WHERE id=?");
                        $stmt_ftp->execute([$ftp_data['username'], $ftp_data['notes'] ?? '', $ftp_id_key]);
                        if (!empty($ftp_data['password'])) {
                            $stmt_pass = $pdo->prepare("UPDATE dc_hosting_ftp_accounts SET password=? WHERE id=?");
                            $stmt_pass->execute([encrypt_password($ftp_data['password']), $ftp_id_key]);
                        }
                        $submitted_ftp_ids[] = $ftp_id_key;
                    }
                }
                
                if (!empty($host_id) && strpos($host_id, 'new_') !== 0) {
                    $stmt_current_ids = $pdo->prepare("SELECT id FROM dc_hosting_ftp_accounts WHERE server_id = ?");
                    $stmt_current_ids->execute([$host_id]);
                    $current_ids = $stmt_current_ids->fetchAll(PDO::FETCH_COLUMN);
                    $ids_to_delete = array_diff($current_ids, $submitted_ftp_ids);

                    if (!empty($ids_to_delete)) {
                        $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
                        $stmt_del = $pdo->prepare("DELETE FROM dc_hosting_ftp_accounts WHERE id IN ($placeholders)");
                        $stmt_del->execute($ids_to_delete);
                    }
                }

                // --- Guardar Cuentas cPanel ---
                $cpanel_accounts = $_POST['cpanel_accounts'] ?? [];
                $submitted_cpanel_ids = [];
                foreach ($cpanel_accounts as $cpanel_id_key => $cpanel_data) {
                    if (empty($cpanel_data['username'])) continue;

                    if (strpos($cpanel_id_key, 'new_') === 0) {
                        if (empty($cpanel_data['password'])) continue;
                        $stmt_cpanel = $pdo->prepare("INSERT INTO dc_hosting_accounts (server_id, username, password, domain, label, notes) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt_cpanel->execute([$host_id, $cpanel_data['username'], encrypt_password($cpanel_data['password']), $cpanel_data['domain'] ?? '', $cpanel_data['label'] ?? '', $cpanel_data['notes'] ?? '']);
                        $submitted_cpanel_ids[] = $pdo->lastInsertId();
                    } else {
                        $stmt_cpanel = $pdo->prepare("UPDATE dc_hosting_accounts SET username=?, domain=?, label=?, notes=? WHERE id=?");
                        $stmt_cpanel->execute([$cpanel_data['username'], $cpanel_data['domain'] ?? '', $cpanel_data['label'] ?? '', $cpanel_data['notes'] ?? '', $cpanel_id_key]);
                        if (!empty($cpanel_data['password'])) {
                            $stmt_pass = $pdo->prepare("UPDATE dc_hosting_accounts SET password=? WHERE id=?");
                            $stmt_pass->execute([encrypt_password($cpanel_data['password']), $cpanel_id_key]);
                        }
                        $submitted_cpanel_ids[] = $cpanel_id_key;
                    }
                }
                
                if (!empty($host_id) && strpos($host_id, 'new_') !== 0) {
                    $stmt_current_ids = $pdo->prepare("SELECT id FROM dc_hosting_accounts WHERE server_id = ?");
                    $stmt_current_ids->execute([$host_id]);
                    $current_ids = $stmt_current_ids->fetchAll(PDO::FETCH_COLUMN);
                    $ids_to_delete = array_diff($current_ids, $submitted_cpanel_ids);

                    if (!empty($ids_to_delete)) {
                        $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
                        $stmt_del = $pdo->prepare("DELETE FROM dc_hosting_accounts WHERE id IN ($placeholders)");
                        $stmt_del->execute($ids_to_delete);
                    }
                }

                // --- Guardar Cuentas de Email ---
                $email_accounts = $_POST['email_accounts'] ?? [];
                $submitted_email_ids = [];
                foreach ($email_accounts as $email_id_key => $email_data) {
                    if (empty($email_data['email_address'])) continue;

                    if (strpos($email_id_key, 'new_') === 0) {
                        if (empty($email_data['password'])) continue;
                        $stmt_email = $pdo->prepare("INSERT INTO dc_hosting_emails (server_id, email_address, password, notes) VALUES (?, ?, ?, ?)");
                        $stmt_email->execute([$host_id, $email_data['email_address'], encrypt_password($email_data['password']), $email_data['notes'] ?? '']);
                        $submitted_email_ids[] = $pdo->lastInsertId();
                    } else {
                        $stmt_email = $pdo->prepare("UPDATE dc_hosting_emails SET email_address=?, notes=? WHERE id=?");
                        $stmt_email->execute([$email_data['email_address'], $email_data['notes'] ?? '', $email_id_key]);
                        if (!empty($email_data['password'])) {
                            $stmt_pass = $pdo->prepare("UPDATE dc_hosting_emails SET password=? WHERE id=?");
                            $stmt_pass->execute([encrypt_password($email_data['password']), $email_id_key]);
                        }
                        $submitted_email_ids[] = $email_id_key;
                    }
                }
                
                if (!empty($host_id) && strpos($host_id, 'new_') !== 0) {
                    $stmt_current_ids = $pdo->prepare("SELECT id FROM dc_hosting_emails WHERE server_id = ?");
                    $stmt_current_ids->execute([$host_id]);
                    $current_ids = $stmt_current_ids->fetchAll(PDO::FETCH_COLUMN);
                    $ids_to_delete = array_diff($current_ids, $submitted_email_ids);

                    if (!empty($ids_to_delete)) {
                        $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
                        $stmt_del = $pdo->prepare("DELETE FROM dc_hosting_emails WHERE id IN ($placeholders)");
                        $stmt_del->execute($ids_to_delete);
                    }
                }

            } elseif ($action === 'delete_host') {
                $host_id = $_POST['host_id'] ?? 0;
                
                $pdo->prepare("DELETE FROM dc_hosting_emails WHERE server_id = ?")->execute([$host_id]);
                $pdo->prepare("DELETE FROM dc_hosting_ftp_accounts WHERE server_id = ?")->execute([$host_id]);
                $pdo->prepare("DELETE FROM dc_hosting_accounts WHERE server_id = ?")->execute([$host_id]);
                $pdo->prepare("DELETE FROM dc_hosting_servers WHERE id = ?")->execute([$host_id]);
                
                $status_message = '<div class="status-message success">Servidor de hosting eliminado.</div>';
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $status_message = '<div class="status-message error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
}

// --- CARGA DE DATOS ---
$hosting_servers = [];
try {
    $stmt = $pdo->query("SELECT * FROM dc_hosting_servers ORDER BY label");
    $servers_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $server_ids = array_column($servers_raw, 'id');

    $all_accounts = [];
    $all_emails = [];
    $all_ftp = [];

    if (!empty($server_ids)) {
        $in_sql = implode(',', array_fill(0, count($server_ids), '?'));
        
        $stmt_accounts = $pdo->prepare("SELECT * FROM dc_hosting_accounts WHERE server_id IN ($in_sql) ORDER BY label");
        $stmt_accounts->execute($server_ids);
        foreach ($stmt_accounts->fetchAll(PDO::FETCH_ASSOC) as $acc) {
            $all_accounts[$acc['server_id']][] = $acc;
        }

        $stmt_emails = $pdo->prepare("SELECT * FROM dc_hosting_emails WHERE server_id IN ($in_sql) ORDER BY email_address");
        $stmt_emails->execute($server_ids);
        foreach ($stmt_emails->fetchAll(PDO::FETCH_ASSOC) as $email) {
            $all_emails[$email['server_id']][] = $email;
        }

        $stmt_ftp = $pdo->prepare("SELECT * FROM dc_hosting_ftp_accounts WHERE server_id IN ($in_sql) ORDER BY username");
        $stmt_ftp->execute($server_ids);
        foreach ($stmt_ftp->fetchAll(PDO::FETCH_ASSOC) as $ftp) {
            $all_ftp[$ftp['server_id']][] = $ftp;
        }
    }

    foreach ($servers_raw as $server) {
        $server['accounts'] = $all_accounts[$server['id']] ?? [];
        $server['emails'] = $all_emails[$server['id']] ?? [];
        $server['ftp_accounts'] = $all_ftp[$server['id']] ?? [];
        $hosting_servers[] = $server;
    }

} catch (Exception $e) {
    $status_message = '<div class="status-message error">Error al cargar los datos.</div>';
    error_log('Hosting Manager Error: ' . $e->getMessage());
}

$total_servers = count($hosting_servers);
$total_accounts = array_sum(array_map(fn($s) => count($s['accounts']), $hosting_servers));
$total_emails = array_sum(array_map(fn($s) => count($s['emails']), $hosting_servers));
$total_ftp = array_sum(array_map(fn($s) => count($s['ftp_accounts']), $hosting_servers));

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚙️ Gestión de Hosting - Portal SECMTI</title>
    <link rel="stylesheet" href="./assets/css/main.css">
    <link rel="stylesheet" href="./assets/css/datacenter.css">
    <link rel="stylesheet" href="./assets/css/hosting.css">
    <style nonce="<?= htmlspecialchars($nonce) ?>">
        .header-subtitle {
            font-size: 0.9em;
            opacity: 0.8;
            margin: 0.5rem 0;
        }
        .stats-compact {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 0.75rem;
        }
        .stat-badge {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            white-space: nowrap;
        }
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
        }
        .empty-state-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
        .servers-container {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        }
        .hosting-card {
            background: var(--container-background);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .hosting-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .hosting-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
        }
        .hosting-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0 0 0.25rem 0;
        }
        .hosting-hostname {
            font-size: 0.875rem;
            opacity: 0.9;
        }
        .hosting-stats {
            display: flex;
            justify-content: space-around;
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            display: block;
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        .stat-label {
            font-size: 0.75rem;
            color: #666;
            text-transform: uppercase;
        }
        .hosting-actions {
            padding: 1rem 1.5rem;
            display: flex;
            gap: 0.5rem;
        }
        .btn-hosting {
            flex: 1;
            padding: 0.75rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-edit {
            background: var(--primary-color);
            color: white;
        }
        .btn-edit:hover {
            opacity: 0.9;
        }
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        .btn-delete:hover {
            opacity: 0.9;
        }
        .modal-form-grid {
            display: grid;
            gap: 1rem;
        }
        .dynamic-section {
            background: rgba(0,0,0,0.02);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-bottom: 1rem;
        }
        .dynamic-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .dynamic-item {
            background: white;
            padding: 1rem;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            margin-bottom: 0.75rem;
        }
        .dynamic-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        .btn-remove {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
        }
        .btn-add {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
        }
        /* Estilos mejorados para modal scrolleable */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            z-index: 10000;
            overflow-y: auto;
            padding: 1rem;
        }
        .modal-overlay.active {
            display: block;
        }
        .modal-container {
            background: white;
            border-radius: 12px;
            max-width: 800px;
            min-height: 200px;
            margin: 2rem auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            z-index: 1;
            border-radius: 12px 12px 0 0;
        }
        .modal-title {
            margin: 0;
            font-size: 1.5rem;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: #666;
            line-height: 1;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.2s;
        }
        .modal-close:hover {
            background: rgba(0,0,0,0.1);
        }
        .modal-body {
            padding: 1.5rem;
            background: white; /* Añadido para evitar transparencias */
        }
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            position: sticky;
            bottom: 0;
            background: white;
            border-radius: 0 0 12px 12px;
        }
        .modal-fieldset {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .modal-fieldset legend {
            font-weight: 600;
            padding: 0 0.5rem;
            font-size: 1rem;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="page-manage">
    <div class="admin-container admin-container-full-width">
        <!-- Header Compacto Moderno -->
        <div class="compact-header">
            <div class="header-left">
                <h1>⚙️ Gestión de Hosting</h1>
                <p class="header-subtitle">Administración de servidores cPanel/WHM</p>
                <span class="stats-compact">
                    <span class="stat-badge">🖥️ <?= $total_servers ?> Servidores</span>
                    <span class="stat-badge">👤 <?= $total_accounts ?> Cuentas</span>
                    <span class="stat-badge">✉️ <?= $total_emails ?> Emails</span>
                    <span class="stat-badge">🔒 <?= $total_ftp ?> FTP</span>
                </span>
            </div>
            <div class="header-actions">
                <button type="button" id="addHostBtn" class="btn-action btn-primary">+ Agregar Servidor</button>
                <a href="hosting_view.php" class="btn-action btn-secondary">👁️ Vista Usuario</a>
            </div>
        </div>

        <?= $status_message ?>
        <?= csrf_field() ?>

        <div class="content">
            <?php if (empty($hosting_servers)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🌐</div>
                    <h2>No hay servidores de hosting configurados</h2>
                    <p>Comience agregando un servidor con el botón "+ Agregar Servidor"</p>
                </div>
            <?php else: ?>
                <div class="servers-container">
                    <?php foreach ($hosting_servers as $server): ?>
                    <div class="hosting-card">
                        <div class="hosting-header">
                            <h2 class="hosting-title">🌐 <?= htmlspecialchars($server['label']) ?></h2>
                            <div class="hosting-hostname"><?= htmlspecialchars($server['hostname']) ?></div>
                        </div>
                        
                        <div class="hosting-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?= count($server['accounts']) ?></span>
                                <span class="stat-label">cPanel</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?= count($server['ftp_accounts']) ?></span>
                                <span class="stat-label">FTP</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?= count($server['emails']) ?></span>
                                <span class="stat-label">Email</span>
                            </div>
                        </div>

                        <div class="hosting-actions">
                            <button type="button" class="btn-hosting btn-edit edit-btn" 
                                    data-host='<?= htmlspecialchars(json_encode($server), ENT_QUOTES) ?>'>
                                ✏️ Editar
                            </button>
                            <button type="button" class="btn-hosting btn-delete delete-btn" 
                                    data-host-id="<?= $server['id'] ?>" 
                                    data-host-label="<?= htmlspecialchars($server['label']) ?>">
                                🗑️ Eliminar
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <a href="index2.php" class="back-btn">← Volver al Portal</a>

    <?php require_once 'templates/footer.php'; ?>
    
    <script src="assets/js/modal-system.js" nonce="<?= htmlspecialchars($nonce) ?>"></script>
    <script src="assets/js/hosting-manager.js" nonce="<?= htmlspecialchars($nonce) ?>"></script>
</body>
</html>
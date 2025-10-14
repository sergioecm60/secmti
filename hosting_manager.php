<?php
// hosting_manager.php - Gestor de servidores de hosting (cPanel/WHM)

require_once 'bootstrap.php';

$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self'; img-src 'self' data:;");

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
                        // Para cuentas nuevas, la contraseña es obligatoria
                        if (empty($ftp_data['password'])) continue;
                        $stmt_ftp = $pdo->prepare("INSERT INTO dc_hosting_ftp_accounts (server_id, username, password, notes) VALUES (?, ?, ?, ?)");
                        $stmt_ftp->execute([$host_id, $ftp_data['username'], encrypt_password($ftp_data['password'], $config), $ftp_data['notes'] ?? '']);
                        $submitted_ftp_ids[] = $pdo->lastInsertId(); // <-- ¡LA CORRECCIÓN CLAVE!
                    } else {
                        $stmt_ftp = $pdo->prepare("UPDATE dc_hosting_ftp_accounts SET username=?, notes=? WHERE id=?");
                        $stmt_ftp->execute([$ftp_data['username'], $ftp_data['notes'] ?? '', $ftp_id_key]);
                        if (!empty($ftp_data['password'])) {
                            $stmt_pass = $pdo->prepare("UPDATE dc_hosting_ftp_accounts SET password=? WHERE id=?");
                            $stmt_pass->execute([encrypt_password($ftp_data['password'], $config), $ftp_id_key]);
                        }
                        $submitted_ftp_ids[] = $ftp_id_key;
                    }
                }
                // Eliminar cuentas FTP que ya no están en el formulario
                if (!empty($host_id) && strpos($host_id, 'new_') !== 0) {
                    if (empty($submitted_ftp_ids)) {
                        $stmt_del = $pdo->prepare("DELETE FROM dc_hosting_ftp_accounts WHERE server_id = ?");
                        $stmt_del->execute([$host_id]);
                    } else {
                        $placeholders = implode(',', array_fill(0, count($submitted_ftp_ids), '?'));
                        $stmt_del = $pdo->prepare("DELETE FROM dc_hosting_ftp_accounts WHERE server_id = ? AND id NOT IN ($placeholders)");
                        $stmt_del->execute(array_merge([$host_id], $submitted_ftp_ids));
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
                        $stmt_cpanel->execute([$host_id, $cpanel_data['username'], encrypt_password($cpanel_data['password'], $config), $cpanel_data['domain'] ?? '', $cpanel_data['label'] ?? '', $cpanel_data['notes'] ?? '']);
                        $submitted_cpanel_ids[] = $pdo->lastInsertId(); // <-- ¡LA CORRECCIÓN CLAVE!
                    } else {
                        $stmt_cpanel = $pdo->prepare("UPDATE dc_hosting_accounts SET username=?, domain=?, label=?, notes=? WHERE id=?");
                        $stmt_cpanel->execute([$cpanel_data['username'], $cpanel_data['domain'] ?? '', $cpanel_data['label'] ?? '', $cpanel_data['notes'] ?? '', $cpanel_id_key]);
                        if (!empty($cpanel_data['password'])) {
                            $stmt_pass = $pdo->prepare("UPDATE dc_hosting_accounts SET password=? WHERE id=?");
                            $stmt_pass->execute([encrypt_password($cpanel_data['password'], $config), $cpanel_id_key]);
                        }
                        $submitted_cpanel_ids[] = $cpanel_id_key;
                    }
                }
                if (!empty($host_id) && strpos($host_id, 'new_') !== 0) {
                    // Obtener todos los IDs de cPanel para este servidor ANTES de borrar
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
                        $stmt_email->execute([$host_id, $email_data['email_address'], encrypt_password($email_data['password'], $config), $email_data['notes'] ?? '']);
                        $submitted_email_ids[] = $pdo->lastInsertId(); // <-- ¡LA CORRECCIÓN CLAVE!
                    } else {
                        $stmt_email = $pdo->prepare("UPDATE dc_hosting_emails SET email_address=?, notes=? WHERE id=?");
                        $stmt_email->execute([$email_data['email_address'], $email_data['notes'] ?? '', $email_id_key]);
                        if (!empty($email_data['password'])) {
                            $stmt_pass = $pdo->prepare("UPDATE dc_hosting_emails SET password=? WHERE id=?");
                            $stmt_pass->execute([encrypt_password($email_data['password'], $config), $email_id_key]);
                        }
                        $submitted_email_ids[] = $email_id_key;
                    }
                }
                if (!empty($host_id) && strpos($host_id, 'new_') !== 0) {
                    // Obtener todos los IDs de email para este servidor ANTES de borrar
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
                $stmt = $pdo->prepare("DELETE FROM dc_hosting_servers WHERE id = ?");
                $stmt->execute([$host_id]);
                $status_message = '<div class="status-message success">Servidor de hosting eliminado.</div>';
            }

            $pdo->commit();
            // Redirigir para evitar reenvío de formulario
            header('Location: ' . $_SERVER['PHP_SELF'] . '?status=' . urlencode(strip_tags($status_message)));
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $status_message = '<div class="status-message error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            // Loguear el error para depuración
            error_log('Hosting Manager Save Error: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
        }
}

// Mostrar mensaje de estado si viene de una redirección
if (isset($_GET['status'])) {
    $status_message = '<div class="status-message success">' . htmlspecialchars($_GET['status']) . '</div>';
}

// --- CARGA DE DATOS PARA LA VISTA ---
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
    $status_message = '<div class="status-message error">Error al cargar los datos de hosting.</div>';
    error_log('Hosting Manager Error: ' . $e->getMessage());
}

ob_start();
?>
<div class="form-group">
    <label for="hostLabel">Etiqueta Descriptiva *</label>
    <input type="text" name="label" id="hostLabel" required placeholder="Ej: Hosting Clientes A" form="hostForm">
</div>
<div class="form-group">
    <label for="hostHostname">Hostname (Dominio del servidor) *</label>
    <input type="text" name="hostname" id="hostHostname" required placeholder="Ej: vps.midominio.com" form="hostForm">
</div>
<div class="form-grid">
    <div class="form-group">
        <label for="hostCpanelPort">Puerto cPanel/WHM</label>
        <input type="number" name="cpanel_port" id="hostCpanelPort" value="2083" form="hostForm">
    </div>
    <div class="form-group">
        <label for="hostWebmailPort">Puerto Webmail</label>
        <input type="number" name="webmail_port" id="hostWebmailPort" value="2096" form="hostForm">
    </div>
</div>
<div class="form-group">
    <label for="hostNotes">Notas</label>
    <textarea name="notes" id="hostNotes" rows="3" form="hostForm"></textarea>
</div>
<?php
$tab_general_content = ob_get_clean();

ob_start();
?>
<h3>👤 Cuentas cPanel</h3>
<div class="dynamic-tab-content">
    <div class="scrollable-list" id="cpanelAccountsContainer"></div>
    <div class="fixed-actions">
        <button type="button" class="add-btn" id="addCpanelAccountBtn">+ Agregar Cuenta cPanel</button>
    </div>
</div>
<?php
$tab_cpanel_content = ob_get_clean();

ob_start();
?>
<h3>🔒 Cuentas FTP</h3>
<div class="dynamic-tab-content">
    <div class="scrollable-list" id="ftpAccountsContainer"></div>
    <div class="fixed-actions">
        <button type="button" class="add-btn" id="addFtpAccountBtn">+ Agregar Cuenta FTP</button>
    </div>
</div>
<?php
$tab_ftp_content = ob_get_clean();

ob_start();
?>
<h3>✉️ Cuentas de Email</h3>
<div class="dynamic-tab-content">
    <input type="search" id="emailSearchInput" class="sub-search-input" placeholder="🔍 Buscar en cuentas de email...">
    <div class="scrollable-list" id="emailAccountsContainer"></div>
    <div class="fixed-actions">
        <button type="button" class="add-btn" id="addEmailAccountBtn">+ Agregar Cuenta de Email</button>
    </div>
</div>
<?php
$tab_email_content = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Hosting</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/datacenter.css"> <!-- Reutilizamos estilos -->
</head>
<body class="page-manage">
    <div class="admin-container">
        <header class="admin-header">
            <h1>🌐 Gestión de Hosting (cPanel/WHM)</h1>
            <p>Administra tus servidores de hosting y sus cuentas.</p>
        </header>

        <div class="content">
            <?= $status_message ?>

            <div class="server-list">
                <?php if (empty($hosting_servers)): ?>
                    <p>No hay servidores de hosting configurados. ¡Añade el primero!</p>
                <?php else: ?>
                    <?php foreach ($hosting_servers as $server): ?>
                    <div class="server-card">
                        <div class="server-header">
                            <div class="server-title">
                                <span class="server-icon">🌐</span>
                                <strong><?= htmlspecialchars($server['label']) ?></strong>
                                <small>(<?= htmlspecialchars($server['hostname']) ?>)</small>
                            </div>
                            <div class="server-actions">
                                <button type="button" class="action-btn view-btn" data-host-data='<?= htmlspecialchars(json_encode($server)) ?>'>👁️ Ver</button>
                                <button type="button" class="action-btn edit-btn" data-host-data='<?= htmlspecialchars(json_encode($server)) ?>'>✏️ Editar</button>
                                <form method="POST" class="delete-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="action" value="delete_host">
                                    <input type="hidden" name="host_id" value="<?= $server['id'] ?>">
                                    <button type="submit" class="action-btn delete-btn">Eliminar</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <button type="button" id="addHostBtn" class="add-btn">+ Agregar Servidor de Hosting</button>
        </div>
    </div>

    <a href="index2.php" class="back-btn">← Volver al Portal</a>

    <!-- El modal ahora se renderiza dentro de un único formulario -->
    <form method="POST" id="hostForm">
        <input type="hidden" name="action" value="save_host">
        <?= csrf_field() ?>
        <input type="hidden" name="host_id" id="hostId">
        
        <?php
        echo render_modal([
            'id' => 'hostModal',
            'title' => 'Gestionar Servidor de Hosting',
            'size' => 'xl',
            'tabs' => [
                ['id' => 'tab-general', 'label' => 'General', 'content' => $tab_general_content],
                ['id' => 'tab-cpanel', 'label' => 'cPanel', 'content' => $tab_cpanel_content],
                ['id' => 'tab-ftp', 'label' => 'FTP', 'content' => $tab_ftp_content],
                ['id' => 'tab-email', 'label' => 'Email', 'content' => $tab_email_content],
            ],
            'form_id' => 'hostForm', // Sigue siendo necesario para el botón de submit
            'submit_text' => 'Guardar'
        ]);
        ?>
    </form>

    <script src="assets/js/modal-system.js" nonce="<?= htmlspecialchars($nonce) ?>"></script>
    <script nonce="<?= htmlspecialchars($nonce) ?>">
    document.addEventListener('DOMContentLoaded', function() {
        const addHostBtn = document.getElementById('addHostBtn');
        const serverList = document.querySelector('.server-list');

        window.openHostModal = function(hostData = null, isReadOnly = false) {
            const form = document.getElementById('hostForm');
            const modal = document.getElementById('hostModal');
            const modalTitle = modal.querySelector('.modal-title');
            form.reset();

            // Resetear pestañas a la primera
            const firstTab = modal.querySelector('.modal-tab');
            if(firstTab) modalManager.switchTab(firstTab);
            
            // Limpiar contenedores dinámicos
            document.getElementById('ftpAccountsContainer').innerHTML = '';
            document.getElementById('cpanelAccountsContainer').innerHTML = '';
            document.getElementById('emailAccountsContainer').innerHTML = '';

            if (hostData) {
                modalTitle.textContent = isReadOnly ? 'Ver Servidor de Hosting' : 'Editar Servidor de Hosting';
                document.getElementById('hostId').value = hostData.id;
                document.getElementById('hostLabel').value = hostData.label;
                document.getElementById('hostHostname').value = hostData.hostname;
                document.getElementById('hostCpanelPort').value = hostData.cpanel_port;
                document.getElementById('hostWebmailPort').value = hostData.webmail_port;
                document.getElementById('hostNotes').value = hostData.notes || '';

                // Poblar cuentas FTP
                const ftpContainer = document.getElementById('ftpAccountsContainer');
                (hostData.ftp_accounts || []).forEach(ftp => {
                    ftpContainer.appendChild(createFtpAccountElement(ftp));
                });

                // Poblar cuentas cPanel
                const cpanelContainer = document.getElementById('cpanelAccountsContainer');
                (hostData.accounts || []).forEach(acc => {
                    cpanelContainer.appendChild(createCpanelAccountElement(acc));
                });

                // Poblar cuentas de Email
                const emailContainer = document.getElementById('emailAccountsContainer');
                (hostData.emails || []).forEach(email => {
                    emailContainer.appendChild(createEmailAccountElement(email));
                });
            } else {
                modalTitle.textContent = 'Agregar Servidor de Hosting';
                document.getElementById('hostId').value = 'new_' + Date.now();
            }
            modalManager.open('hostModal');
        }
        
        // Función para establecer el modo de solo lectura en el modal
        function setReadOnly(isReadOnly) {
            const form = document.getElementById('hostForm');
            form.querySelectorAll('input, textarea, select, button').forEach(el => {
                // No deshabilitar los botones de las pestañas, el de cancelar o el de cerrar.
                if (!el.classList.contains('modal-tab') && !el.closest('.modal-footer .cancel-btn')) {
                    el.disabled = isReadOnly;
                }
            });
            document.querySelector('#hostModal .save-btn').style.display = isReadOnly ? 'none' : '';
        }

        // --- Asignación de Eventos ---
        addHostBtn.addEventListener('click', () => openHostModal());

        serverList.addEventListener('click', function(e) {
            const editBtn = e.target.closest('.edit-btn');
            const viewBtn = e.target.closest('.view-btn');

            if (editBtn) {
                const hostData = JSON.parse(editBtn.dataset.hostData);
                openHostModal(hostData, false);
                setReadOnly(false);
            } else if (viewBtn) {
                const hostData = JSON.parse(viewBtn.dataset.hostData);
                openHostModal(hostData, true);
                setReadOnly(true);
            }
        });

        document.querySelectorAll('.delete-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('¿Estás seguro de que quieres eliminar este servidor de hosting y todas sus cuentas?')) {
                    e.preventDefault();
                }
            });
        });

        // --- Lógica para Cuentas FTP dinámicas ---
        function createFtpAccountElement(ftpData = {}) {
            const ftpId = ftpData.id || 'new_ftp_' + Date.now();
            const div = document.createElement('div');
            div.className = 'form-grid ftp-item';
            div.innerHTML = `
                <input type="hidden" name="ftp_accounts[${ftpId}][id]" value="${ftpData.id || ''}" form="hostForm">
                <input type="text" name="ftp_accounts[${ftpId}][username]" placeholder="Usuario FTP" value="${ftpData.username || ''}" required autocomplete="username" form="hostForm">
                <input type="password" name="ftp_accounts[${ftpId}][password]" placeholder="${ftpData.id ? 'Nueva Contraseña (opcional)' : 'Contraseña (requerida)'}" value="" ${!ftpData.id ? 'required' : ''} autocomplete="new-password" form="hostForm">
                <input type="text" name="ftp_accounts[${ftpId}][notes]" placeholder="Notas (opcional)" value="${ftpData.notes || ''}" autocomplete="off" form="hostForm">
                <button type="button" class="delete-btn ftp-delete-btn">✕</button>
            `;
            return div;
        }

        document.getElementById('addFtpAccountBtn').addEventListener('click', function() {
            const container = document.getElementById('ftpAccountsContainer');
            container.appendChild(createFtpAccountElement());
        });

        // --- Lógica para Cuentas cPanel dinámicas ---
        function createCpanelAccountElement(cpanelData = {}) {
            const cpanelId = cpanelData.id || 'new_cpanel_' + Date.now();
            const div = document.createElement('div');
            div.className = 'form-grid cpanel-item';
            div.innerHTML = `
                <input type="hidden" name="cpanel_accounts[${cpanelId}][id]" value="${cpanelData.id || ''}" form="hostForm">
                <input type="text" name="cpanel_accounts[${cpanelId}][label]" placeholder="Etiqueta (ej: Cliente X)" value="${cpanelData.label || ''}" autocomplete="off" form="hostForm">
                <input type="text" name="cpanel_accounts[${cpanelId}][username]" placeholder="Usuario cPanel" value="${cpanelData.username || ''}" required autocomplete="username" form="hostForm">
                <input type="password" name="cpanel_accounts[${cpanelId}][password]" placeholder="${cpanelData.id ? 'Nueva Contraseña (opcional)' : 'Contraseña (requerida)'}" value="" ${!cpanelData.id ? 'required' : ''} autocomplete="new-password" form="hostForm">
                <input type="text" name="cpanel_accounts[${cpanelId}][domain]" placeholder="Dominio" value="${cpanelData.domain || ''}" autocomplete="url" form="hostForm">
                <button type="button" class="delete-btn cpanel-delete-btn">✕</button>
            `;
            return div;
        }

        document.getElementById('addCpanelAccountBtn').addEventListener('click', () => {
            document.getElementById('cpanelAccountsContainer').appendChild(createCpanelAccountElement());
        });

        // --- Lógica para Cuentas de Email dinámicas ---
        function createEmailAccountElement(emailData = {}) {
            const emailId = emailData.id || 'new_email_' + Date.now();
            const div = document.createElement('div');
            div.className = 'form-grid email-item';
            div.innerHTML = `
                <input type="hidden" name="email_accounts[${emailId}][id]" value="${emailData.id || ''}" form="hostForm">
                <input type="email" name="email_accounts[${emailId}][email_address]" placeholder="Dirección de email" value="${emailData.email_address || ''}" required autocomplete="email" form="hostForm">
                <input type="password" name="email_accounts[${emailId}][password]" placeholder="${emailData.id ? 'Nueva Contraseña (opcional)' : 'Contraseña (requerida)'}" value="" ${!emailData.id ? 'required' : ''} autocomplete="new-password" form="hostForm">
                <input type="text" name="email_accounts[${emailId}][notes]" placeholder="Notas (ej: Nombre Apellido)" value="${emailData.notes || ''}" autocomplete="off" form="hostForm">
                <button type="button" class="delete-btn email-delete-btn">✕</button>
            `;
            return div;
        }

        document.getElementById('addEmailAccountBtn').addEventListener('click', () => {
            document.getElementById('emailAccountsContainer').appendChild(createEmailAccountElement());
        });

        // --- Lógica para el buscador de emails ---
        const emailSearchInput = document.getElementById('emailSearchInput');
        emailSearchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const emailItems = document.querySelectorAll('#emailAccountsContainer .email-item');
            
            emailItems.forEach(item => {
                const emailAddress = item.querySelector('input[name*="[email_address]"]').value.toLowerCase();
                const emailNotes = item.querySelector('input[name*="[notes]"]').value.toLowerCase();
                
                const isVisible = emailAddress.includes(searchTerm) || emailNotes.includes(searchTerm);
                item.style.display = isVisible ? '' : 'none';
            });
        });

        // Event delegation for dynamic delete buttons
        document.getElementById('hostModal').addEventListener('click', function(e) {
            if (e.target.classList.contains('ftp-delete-btn')) {
                e.target.closest('.ftp-item').remove();
            }
            if (e.target.classList.contains('cpanel-delete-btn')) {
                e.target.closest('.cpanel-item').remove();
            }
            if (e.target.classList.contains('email-delete-btn')) {
                e.target.closest('.email-item').remove();
            }
        });
    });
    </script>
</body>
</html>
<?php
// hosting_view.php - Vista de solo lectura para la gestión de hosting.

require_once 'bootstrap.php';
require_once 'database.php';

$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self';");

// Verificar autenticación (cualquier usuario logueado puede ver).
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = get_database_connection($config, true);

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
    error_log('Hosting View Error: ' . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vista de Hosting</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/datacenter.css">
</head>
<body class="page-manage">
    <div class="admin-container admin-container-full-width">
        <header class="admin-header">
            <h1>🌐 Vista de Hosting</h1>
            <p>Consulta la información de tus servidores de hosting, cPanel, FTP y cuentas de correo.</p>
        </header>

        <div class="content">
            <div class="search-box">
                <input type="search" id="mainSearch" placeholder="🔍 Buscar en todos los servidores de hosting...">
            </div>

            <?php if (empty($hosting_servers)): ?>
                <div class="no-data">
                    <h2>No hay servidores de hosting configurados.</h2>
                    <?php if (($_SESSION['user_role'] ?? 'user') === 'admin'): ?>
                        <a href="hosting_manager.php" class="quick-link quick-link-standalone">⚙️ Ir a Gestión de Hosting</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="servers-grid">
                    <?php foreach ($hosting_servers as $server): ?>
                    <div class="server-card collapsed" id="host-<?= $server['id'] ?>">
                        <div class="server-header">
                            <div>
                                🌐 <strong><?= htmlspecialchars($server['label']) ?></strong>
                                <small>(<?= htmlspecialchars($server['hostname']) ?>)</small>
                            </div>
                            <div class="server-header-actions">
                                <?php if (($_SESSION['user_role'] ?? 'user') === 'admin'): ?>
                                    <a href="hosting_manager.php?edit=<?= $server['id'] ?>" class="quick-link" style="padding: 0.4rem 0.8rem; font-size: 0.9rem;">✏️ Editar</a>
                                <?php endif; ?>
                                <button type="button" class="view-toggle-btn" aria-expanded="false" aria-label="Expandir/Contraer servidor <?= htmlspecialchars($server['label']) ?>">▶</button>
                            </div>
                        </div>
                        <div class="server-body">
                            <!-- cPanel Accounts -->
                            <?php if (!empty($server['accounts'])): ?>
                            <div class="info-row">
                                <div class="info-label">
                                    👤 Cuentas cPanel <span class="count-badge"><?= count($server['accounts']) ?></span>
                                </div>
                                <?php foreach ($server['accounts'] as $acc): ?>
                                <div class="service-card">
                                    <div class="service-title"><?= htmlspecialchars($acc['label'] ?: $acc['username']) ?></div>
                                    <div class="cred-row">
                                        <span><strong>Usuario:</strong> <?= htmlspecialchars($acc['username']) ?> | <strong>Dominio:</strong> <?= htmlspecialchars($acc['domain'] ?: 'N/A') ?></span>
                                        <button type="button" class="copy-cred-btn" data-type="hosting_account" data-id="<?= $acc['id'] ?>">📋 Copiar Pass</button>
                                    </div>
                                    <div class="quick-links">
                                        <a href="https://<?= htmlspecialchars($server['hostname']) ?>:<?= htmlspecialchars($server['cpanel_port']) ?>" target="_blank" class="quick-link">Acceder a cPanel</a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <!-- FTP Accounts -->
                            <?php if (!empty($server['ftp_accounts'])): ?>
                            <div class="info-row">
                                <div class="info-label">
                                    🔒 Cuentas FTP <span class="count-badge"><?= count($server['ftp_accounts']) ?></span>
                                </div>
                                <?php foreach ($server['ftp_accounts'] as $ftp): ?>
                                <div class="service-card">
                                    <div class="cred-row">
                                        <span><strong>Usuario:</strong> <?= htmlspecialchars($ftp['username']) ?></span>
                                        <button type="button" class="copy-cred-btn" data-type="hosting_ftp" data-id="<?= $ftp['id'] ?>">📋 Copiar Pass</button>
                                    </div>
                                    <?php if(!empty($ftp['notes'])): ?><small>Nota: <?= htmlspecialchars($ftp['notes']) ?></small><?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Email Accounts -->
                            <?php if (!empty($server['emails'])): ?>
                            <div class="info-row">
                                <div class="info-label">
                                    ✉️ Cuentas de Email <span class="count-badge"><?= count($server['emails']) ?></span>
                                </div>
                                <input type="search" class="sub-search-input" placeholder="Buscar en emails de este servidor..." data-target-list="email-list-<?= $server['id'] ?>">
                                <div class="scrollable-list" id="email-list-<?= $server['id'] ?>">
                                    <?php foreach ($server['emails'] as $email): ?>
                                    <div class="service-card email-item">
                                        <div class="cred-row">
                                            <span><strong>Email:</strong> <?= htmlspecialchars($email['email_address']) ?></span>
                                            <button type="button" class="copy-cred-btn" data-type="hosting_email" data-id="<?= $email['id'] ?>">📋 Copiar Pass</button>
                                        </div>
                                        <?php if(!empty($email['notes'])): ?><small>Nota: <?= htmlspecialchars($email['notes']) ?></small><?php endif; ?>
                                        <div class="quick-links">
                                            <a href="https://<?= htmlspecialchars($server['hostname']) ?>:<?= htmlspecialchars($server['webmail_port']) ?>" target="_blank" class="quick-link">Acceder a Webmail</a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Notes -->
                            <?php if (!empty($server['notes'])): ?>
                            <div class="info-row">
                                <div class="info-label">📝 Notas del Servidor</div>
                                <small class="server-notes"><?= nl2br(htmlspecialchars($server['notes'])) ?></small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <a href="index2.php" class="back-btn">← Volver al Portal</a>

    <script nonce="<?= htmlspecialchars($nonce) ?>">
    document.addEventListener('DOMContentLoaded', function() {
        // Búsqueda principal
        const mainSearch = document.getElementById('mainSearch');
        const serverCards = document.querySelectorAll('.server-card');

        mainSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            serverCards.forEach(card => {
                const cardText = card.textContent.toLowerCase();
                card.style.display = cardText.includes(searchTerm) ? '' : 'none';
            });
        });

        // Búsqueda secundaria (dentro de cada card)
        const subSearchInputs = document.querySelectorAll('.sub-search-input');
        subSearchInputs.forEach(input => {
            input.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const targetListId = this.dataset.targetList;
                const listItems = document.querySelectorAll(`#${targetListId} .email-item`);

                listItems.forEach(item => {
                    const itemText = item.textContent.toLowerCase();
                    item.style.display = itemText.includes(searchTerm) ? '' : 'none';
                });
            });
        });

        // --- Lógica para paneles colapsables (acordeón) ---
        const serversGrid = document.querySelector('.servers-grid');
        if (serversGrid) {
            serversGrid.addEventListener('click', function (e) {
                const header = e.target.closest('.server-header');
                if (!header) return;

                const card = header.closest('.server-card');
                const body = card.querySelector('.server-body');
                const toggleBtn = header.querySelector('.view-toggle-btn');

                if (card && body && toggleBtn) {
                    toggleCard(card, body, toggleBtn);
                }
            });
        }

        function toggleCard(card, body, toggleBtn) {
            const isCollapsed = card.classList.contains('collapsed');
            if (isCollapsed) {
                card.classList.remove('collapsed');
                body.style.maxHeight = body.scrollHeight + 'px';
                toggleBtn.setAttribute('aria-expanded', 'true');
            } else {
                body.style.maxHeight = null;
                card.classList.add('collapsed');
                toggleBtn.setAttribute('aria-expanded', 'false');
            }
        }

        // --- Lógica para copiar contraseñas ---
        serversGrid?.addEventListener('click', function (e) {
            const copyBtn = e.target.closest('.copy-cred-btn');
            if (!copyBtn) return;

            const credType = copyBtn.dataset.type;
            const credId = copyBtn.dataset.id;
            const originalText = copyBtn.textContent;

            fetch(`api/hosting.php?action=get_password&type=${credType}&id=${credId}`)
                .then(response => {
                    if (!response.ok) throw new Error('No se pudo obtener la contraseña.');
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.password) {
                        return navigator.clipboard.writeText(data.password);
                    } else {
                        throw new Error(data.message || 'Error desconocido.');
                    }
                })
                .then(() => {
                    copyBtn.textContent = '✅ Copiado';
                })
                .catch(error => {
                    console.error('Error al copiar:', error);
                    copyBtn.textContent = '❌ Error';
                })
                .finally(() => {
                    setTimeout(() => { copyBtn.textContent = originalText; }, 2000);
                });
        });
    });
    </script>
</body>
</html>
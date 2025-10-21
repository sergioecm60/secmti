<?php
// hosting_view.php - Vista de solo lectura para la gestión de hosting.

require_once 'bootstrap.php';

$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'nonce-{$nonce}';");

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
    <title>🌐 Hosting - Portal SECMTI</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/datacenter.css">
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
        }
        .hosting-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }
        .hosting-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }
        .hosting-hostname {
            font-size: 0.875rem;
            opacity: 0.9;
            margin-top: 0.25rem;
        }
        .toggle-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 1.5rem;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .hosting-card.expanded .toggle-btn {
            transform: rotate(90deg);
        }
        .hosting-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        .hosting-card.expanded .hosting-body {
            max-height: 5000px;
        }
        .section-group {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        .section-group:last-child {
            border-bottom: none;
        }
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .count-badge {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
        }
        .items-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        }
        .item-card {
            background: rgba(0,0,0,0.02);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        .item-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .item-info {
            font-size: 0.875rem;
            color: #666;
            margin: 0.25rem 0;
        }
        .item-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }
        .btn-copy {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            transition: opacity 0.2s;
        }
        .btn-copy:hover {
            opacity: 0.8;
        }
        .btn-link {
            background: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.875rem;
            display: inline-block;
            transition: all 0.2s;
        }
        .btn-link:hover {
            background: var(--primary-color);
            color: white;
        }
        .sub-search {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        .scrollable-emails {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body class="page-manage">
    <div class="admin-container admin-container-full-width">
        <!-- Header Compacto Moderno -->
        <div class="compact-header">
            <div class="header-left">
                <h1>🌐 Hosting</h1>
                <p class="header-subtitle">Servidores, cPanel, FTP y cuentas de correo</p>
                <?php if (!empty($hosting_servers)): ?>
                <span class="stats-compact">
                    <span class="stat-badge">🖥️ <?= count($hosting_servers) ?> Servidores</span>
                    <span class="stat-badge">👤 <?= array_sum(array_map(fn($s) => count($s['accounts']), $hosting_servers)) ?> Cuentas</span>
                    <span class="stat-badge">✉️ <?= array_sum(array_map(fn($s) => count($s['emails']), $hosting_servers)) ?> Emails</span>
                    <span class="stat-badge">🔒 <?= array_sum(array_map(fn($s) => count($s['ftp_accounts']), $hosting_servers)) ?> FTP</span>
                </span>
                <?php endif; ?>
            </div>
            <div class="header-actions">
                <form method="GET" action="" class="compact-search">
                    <input type="search" 
                           id="mainSearch" 
                           placeholder="🔍 Buscar en hosting..."
                           autocomplete="off">
                </form>
                <?php if (($_SESSION['user_role'] ?? 'user') === 'admin'): ?>
                    <a href="hosting_manager.php" class="btn-action btn-primary">⚙️ Administrar</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="content">
            <?php if (empty($hosting_servers)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🌐</div>
                    <h2>No hay servidores de hosting configurados</h2>
                    <p>Contacte al administrador para configurar los servidores de hosting.</p>
                    <?php if (($_SESSION['user_role'] ?? 'user') === 'admin'): ?>
                        <a href="hosting_manager.php" class="btn-action btn-primary" style="margin-top: 1rem;">⚙️ Configurar Hosting</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="servers-container">
                    <?php foreach ($hosting_servers as $server): ?>
                    <div class="hosting-card" id="host-<?= $server['id'] ?>">
                        <div class="hosting-header">
                            <div>
                                <h2 class="hosting-title">🌐 <?= htmlspecialchars($server['label']) ?></h2>
                                <div class="hosting-hostname"><?= htmlspecialchars($server['hostname']) ?></div>
                            </div>
                            <button type="button" class="toggle-btn" aria-label="Expandir/Contraer">▶</button>
                        </div>
                        
                        <div class="hosting-body">
                            <!-- cPanel Accounts -->
                            <?php if (!empty($server['accounts'])): ?>
                            <div class="section-group">
                                <div class="section-title">
                                    👤 Cuentas cPanel 
                                    <span class="count-badge"><?= count($server['accounts']) ?></span>
                                </div>
                                <div class="items-grid">
                                    <?php foreach ($server['accounts'] as $acc): ?>
                                    <div class="item-card">
                                        <div class="item-title"><?= htmlspecialchars($acc['label'] ?: $acc['username']) ?></div>
                                        <div class="item-info">👤 Usuario: <strong><?= htmlspecialchars($acc['username']) ?></strong></div>
                                        <div class="item-info">🌐 Dominio: <strong><?= htmlspecialchars($acc['domain'] ?: 'N/A') ?></strong></div>
                                        <div class="item-actions">
                                            <button type="button" class="btn-copy" data-type="hosting_account" data-id="<?= $acc['id'] ?>">📋 Copiar Pass</button>
                                            <a href="https://<?= htmlspecialchars($server['hostname']) ?>:<?= htmlspecialchars($server['cpanel_port']) ?>" target="_blank" class="btn-link">🔗 Abrir cPanel</a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- FTP Accounts -->
                            <?php if (!empty($server['ftp_accounts'])): ?>
                            <div class="section-group">
                                <div class="section-title">
                                    🔒 Cuentas FTP 
                                    <span class="count-badge"><?= count($server['ftp_accounts']) ?></span>
                                </div>
                                <div class="items-grid">
                                    <?php foreach ($server['ftp_accounts'] as $ftp): ?>
                                    <div class="item-card">
                                        <div class="item-title">🔒 <?= htmlspecialchars($ftp['username']) ?></div>
                                        <?php if(!empty($ftp['notes'])): ?>
                                            <div class="item-info">📝 <?= htmlspecialchars($ftp['notes']) ?></div>
                                        <?php endif; ?>
                                        <div class="item-actions">
                                            <button type="button" class="btn-copy" data-type="hosting_ftp" data-id="<?= $ftp['id'] ?>">📋 Copiar Pass</button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Email Accounts -->
                            <?php if (!empty($server['emails'])): ?>
                            <div class="section-group">
                                <div class="section-title">
                                    ✉️ Cuentas de Email 
                                    <span class="count-badge"><?= count($server['emails']) ?></span>
                                </div>
                                <input type="search" class="sub-search" placeholder="🔍 Buscar email..." data-target="email-list-<?= $server['id'] ?>">
                                <div class="scrollable-emails" id="email-list-<?= $server['id'] ?>">
                                    <div class="items-grid">
                                        <?php foreach ($server['emails'] as $email): ?>
                                        <div class="item-card email-item">
                                            <div class="item-title">✉️ <?= htmlspecialchars($email['email_address']) ?></div>
                                            <?php if(!empty($email['notes'])): ?>
                                                <div class="item-info">📝 <?= htmlspecialchars($email['notes']) ?></div>
                                            <?php endif; ?>
                                            <div class="item-actions">
                                                <button type="button" class="btn-copy" data-type="hosting_email" data-id="<?= $email['id'] ?>">📋 Copiar Pass</button>
                                                <a href="https://<?= htmlspecialchars($server['hostname']) ?>:<?= htmlspecialchars($server['webmail_port']) ?>" target="_blank" class="btn-link">🔗 Webmail</a>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Notes -->
                            <?php if (!empty($server['notes'])): ?>
                            <div class="section-group">
                                <div class="section-title">📝 Notas del Servidor</div>
                                <div class="item-info"><?= nl2br(htmlspecialchars($server['notes'])) ?></div>
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
        const serverCards = document.querySelectorAll('.hosting-card');

        mainSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            serverCards.forEach(card => {
                const cardText = card.textContent.toLowerCase();
                card.style.display = cardText.includes(searchTerm) ? '' : 'none';
            });
        });

        // Búsqueda de emails dentro de cada servidor
        document.querySelectorAll('.sub-search').forEach(input => {
            input.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const targetId = this.dataset.target;
                const items = document.querySelectorAll(`#${targetId} .email-item`);
                
                items.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    item.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        });

        // Toggle expand/collapse
        document.querySelectorAll('.hosting-header').forEach(header => {
            header.addEventListener('click', function() {
                const card = this.closest('.hosting-card');
                card.classList.toggle('expanded');
            });
        });

        // Copiar contraseñas
        document.querySelectorAll('.btn-copy').forEach(btn => {
            btn.addEventListener('click', function() {
                const credType = this.dataset.type;
                const credId = this.dataset.id;
                const originalText = this.textContent;

                fetch(`api/datacenter.php?action=get_password&type=${credType}&id=${credId}`)
                    .then(response => {
                        if (!response.ok) throw new Error('No se pudo obtener la contraseña.');
                        return response.json();
                    })
                    .then(data => {
                        if (data.success && data.password) {
                            // Usar la API del portapapeles si está disponible (contexto seguro: HTTPS o localhost)
                            if (navigator.clipboard && window.isSecureContext) {
                                return navigator.clipboard.writeText(data.password);
                            } else {
                                // Fallback para contextos no seguros (HTTP)
                                const textArea = document.createElement('textarea');
                                textArea.value = data.password;
                                textArea.style.position = 'absolute';
                                textArea.style.left = '-9999px';
                                document.body.appendChild(textArea);
                                textArea.select();
                                try {
                                    document.execCommand('copy');
                                } catch (err) {
                                    throw new Error('No se pudo copiar la contraseña con el método de respaldo.');
                                } finally {
                                    document.body.removeChild(textArea);
                                }
                            }
                        } else {
                            throw new Error(data.message || 'Error desconocido.');
                        }
                    })
                    .then(() => {
                        this.textContent = '✅ Copiado';
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        this.textContent = '❌ Error';
                    })
                    .finally(() => {
                        setTimeout(() => { this.textContent = originalText; }, 2000);
                    });
            });
        });
    });
    </script>
</body>
</html>
<?php
// locations_manager.php - Gestor de Ubicaciones
require_once 'bootstrap.php';
require_once 'database.php';

$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self';");

if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = get_database_connection($config, true);
$status_message = '';

// --- MANEJO DE ACCIONES POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $status_message = '<div class="status-message error">Error de validación CSRF.</div>';
    } else {
        try {
            $pdo->beginTransaction();
            $action = $_POST['action'] ?? '';

            if ($action === 'save_location') {
                $id = $_POST['id'] ?? null;
                $name = trim($_POST['name'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $notes = trim($_POST['notes'] ?? '');

                if (empty($name)) {
                    throw new Exception("El nombre de la ubicación es obligatorio.");
                }

                if (empty($id) || strpos($id, 'new_') === 0) {
                    $stmt = $pdo->prepare("INSERT INTO dc_locations (name, address, notes) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $address, $notes]);
                    $status_message = '<div class="status-message success">✅ Ubicación creada.</div>';
                } else {
                    $stmt = $pdo->prepare("UPDATE dc_locations SET name=?, address=?, notes=? WHERE id=?");
                    $stmt->execute([$name, $address, $notes, $id]);
                    $status_message = '<div class="status-message success">✅ Ubicación actualizada.</div>';
                }
            } elseif ($action === 'delete_location') {
                $id = $_POST['id'] ?? 0;
                // La FK en dc_servers tiene ON DELETE SET NULL, por lo que los servidores no se borrarán.
                $stmt = $pdo->prepare("DELETE FROM dc_locations WHERE id = ?");
                $stmt->execute([$id]);
                $status_message = '<div class="status-message success">✅ Ubicación eliminada.</div>';
            }

            $pdo->commit();
            header('Location: ' . $_SERVER['PHP_SELF'] . '?status=' . urlencode(strip_tags($status_message)));
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $status_message = '<div class="status-message error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

if (isset($_GET['status'])) {
    $status_message = '<div class="status-message success">' . htmlspecialchars($_GET['status']) . '</div>';
}

// --- CARGA DE DATOS ---
$locations = $pdo->query("SELECT * FROM dc_locations ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Ubicaciones</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/datacenter.css">
</head>
<body class="page-manage">
    <div class="admin-container">
        <header class="admin-header">
            <h1>📍 Gestión de Ubicaciones</h1>
            <p>Administra las ubicaciones físicas de tu infraestructura.</p>
        </header>

        <div class="content">
            <?= $status_message ?>

            <div class="table-container">
                <table id="locations-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Dirección</th>
                            <th>Notas</th>
                            <th style="width: 150px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locations as $loc): ?>
                        <tr>
                            <td><?= htmlspecialchars($loc['name']) ?></td>
                            <td><?= htmlspecialchars($loc['address']) ?></td>
                            <td><?= nl2br(htmlspecialchars($loc['notes'])) ?></td>
                            <td>
                                <button type="button" class="action-btn edit-btn" data-location='<?= htmlspecialchars(json_encode($loc)) ?>'>✏️ Editar</button>
                                <form method="POST" class="delete-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="action" value="delete_location">
                                    <input type="hidden" name="id" value="<?= $loc['id'] ?>">
                                    <button type="submit" class="action-btn delete-btn">🗑️</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <button type="button" id="addLocationBtn" class="add-btn">+ Agregar Ubicación</button>
        </div>
    </div>

    <a href="datacenter_view.php" class="back-btn">← Volver a Infraestructura</a>

    <!-- Modal -->
    <div id="locationModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modalTitle">Agregar Ubicación</h2>
            <form method="POST" id="locationForm">
                <input type="hidden" name="action" value="save_location">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="id" id="locationId">

                <div class="form-group">
                    <label for="name">Nombre *</label>
                    <input type="text" name="name" id="locationName" required>
                </div>
                <div class="form-group">
                    <label for="address">Dirección</label>
                    <input type="text" name="address" id="locationAddress">
                </div>
                <div class="form-group">
                    <label for="notes">Notas</label>
                    <textarea name="notes" id="locationNotes" rows="3"></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="save-btn">💾 Guardar</button>
                    <button type="button" class="cancel-btn">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script nonce="<?= htmlspecialchars($nonce) ?>">
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('locationModal');
        const form = document.getElementById('locationForm');
        const addBtn = document.getElementById('addLocationBtn');
        const closeBtn = modal.querySelector('.close');
        const cancelBtn = modal.querySelector('.cancel-btn');

        function openModal(locationData = null) {
            form.reset();
            if (locationData) {
                document.getElementById('modalTitle').textContent = 'Editar Ubicación';
                document.getElementById('locationId').value = locationData.id;
                document.getElementById('locationName').value = locationData.name;
                document.getElementById('locationAddress').value = locationData.address || '';
                document.getElementById('locationNotes').value = locationData.notes || '';
            } else {
                document.getElementById('modalTitle').textContent = 'Agregar Ubicación';
                document.getElementById('locationId').value = 'new_' + Date.now();
            }
            modal.classList.add('active');
        }

        function closeModal() {
            modal.classList.remove('active');
        }

        addBtn.addEventListener('click', () => openModal());
        closeBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);

        document.getElementById('locations-table').addEventListener('click', function(e) {
            const editBtn = e.target.closest('.edit-btn');
            if (editBtn) {
                const locationData = JSON.parse(editBtn.dataset.location);
                openModal(locationData);
            }
        });

        document.querySelectorAll('.delete-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('¿Estás seguro de que quieres eliminar esta ubicación? Los servidores en esta ubicación no se borrarán, pero quedarán sin ubicación asignada.')) {
                    e.preventDefault();
                }
            });
        });
    });
    </script>
</body>
</html>
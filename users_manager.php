<?php
// users_manager.php - Página para administrar los usuarios del portal con permisos de locaciones.

require_once 'bootstrap.php';

$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'nonce-{$nonce}';");

// Verificar autenticación y rol de administrador.
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$status_message = '';
$pdo = get_database_connection($config, true);

// --- MANEJO DEL GUARDADO DE USUARIOS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
            validate_request_csrf();
            $action = $_POST['action'] ?? '';
            $user_id = $_POST['user_id'] ?? null;
            $username = trim($_POST['username'] ?? '');
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'user';
            $allowed_locations = $_POST['allowed_locations'] ?? [];

            if ($action === 'delete') {
                // Prevenir la eliminación del último usuario
                $stmt_count = $pdo->query("SELECT COUNT(*) FROM users");
                if ($stmt_count->fetchColumn() <= 1) {
                    throw new Exception('No se puede eliminar el último usuario.');
                }
                // Prevenir auto-eliminación
                if ($user_id == $_SESSION['user_id']) {
                    throw new Exception('No puedes eliminar tu propia cuenta.');
                }
                
                // Iniciar transacción
                $pdo->beginTransaction();
                
                // Eliminar relaciones de locaciones
                $stmt = $pdo->prepare("DELETE FROM user_locations WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Eliminar usuario
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                
                $pdo->commit();
                $status_message = '<div class="status-message success">Usuario eliminado correctamente.</div>';

            } elseif ($action === 'save') {
                // Validaciones
                if (empty($username)) throw new Exception('El nombre de usuario es obligatorio.');
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('El formato del email no es válido.');
                
                // Validar locaciones para usuarios con rol 'user'
                if ($role === 'user' && empty($allowed_locations)) {
                    throw new Exception('Debes asignar al menos una locación para usuarios con rol "Usuario".');
                }

                // Iniciar transacción
                $pdo->beginTransaction();

                if (empty($user_id) || strpos($user_id, 'new_') === 0) { // Nuevo usuario
                    if (empty($password) || strlen($password) < 8) {
                        throw new Exception('La contraseña es obligatoria para usuarios nuevos y debe tener al menos 8 caracteres.');
                    }
                    $sql = "INSERT INTO users (username, full_name, email, pass_hash, role) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$username, $full_name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
                    $user_id = $pdo->lastInsertId();
                    $status_message = '<div class="status-message success">Usuario creado correctamente.</div>';
                } else { // Actualizar usuario
                    $sql = "UPDATE users SET username = ?, full_name = ?, email = ?, role = ?";
                    $params = [$username, $full_name, $email, $role];
                    if (!empty($password)) {
                        if (strlen($password) < 8) throw new Exception('La nueva contraseña debe tener al menos 8 caracteres.');
                        $sql .= ", pass_hash = ?";
                        $params[] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    $sql .= " WHERE id = ?";
                    $params[] = $user_id;
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $status_message = '<div class="status-message success">Usuario actualizado correctamente.</div>';
                }

                // Asegurarse de que $user_id es un entero para las operaciones de locaciones
                // Esto es crucial si $user_id viene como string desde el POST para usuarios existentes
                $user_id = (int)$user_id;

                // Gestionar las locaciones asignadas
                // Primero eliminar todas las asignaciones existentes
                $stmt = $pdo->prepare("DELETE FROM user_locations WHERE user_id = ?");
                $stmt->execute([$user_id]);

                // Si es usuario (no admin) y tiene locaciones asignadas, insertarlas
                if ($role === 'user' && !empty($allowed_locations)) {
                    $stmt = $pdo->prepare("INSERT INTO user_locations (user_id, location_id) VALUES (?, ?)");
                    foreach ($allowed_locations as $location_id) {
                        $stmt->execute([$user_id, (int)$location_id]);
                    }
                }

                $pdo->commit();
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $status_message = '<div class="status-message error">' . ($e->getCode() == 23000 ? 'Error: El nombre de usuario o email ya existe.' : 'Error de base de datos.') . '</div>';
            error_log('Users Manager PDOException: ' . $e->getMessage());
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $status_message = '<div class="status-message error">Error: ' . $e->getMessage() . '</div>';
        }
}

// --- Carga de datos para mostrar en la tabla ---
$all_users = $pdo->query("SELECT id, username, full_name, email, role, last_login FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// Cargar las locaciones asignadas a cada usuario
foreach ($all_users as &$user) {
    $stmt = $pdo->prepare("SELECT l.id, l.name FROM user_locations ul INNER JOIN dc_locations l ON ul.location_id = l.id WHERE ul.user_id = ? ORDER BY l.name");
    $stmt->execute([$user['id']]);
    $user['locations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($user);

// Cargar todas las locaciones disponibles
$all_locations = $pdo->query("SELECT id, name FROM dc_locations ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$user_count = count($all_users);

ob_start();
?>
<form method="POST" id="userForm">
    <input type="hidden" name="action" value="save">
    <?= csrf_field() ?>
    <input type="hidden" name="user_id" id="userId">
    
    <div class="form-group">
        <label for="username">Nombre de Usuario *</label>
        <input type="text" name="username" id="username" required>
    </div>
    <div class="form-group">
        <label for="full_name">Nombre Completo</label>
        <input type="text" name="full_name" id="full_name">
    </div>
    <div class="form-group">
        <label for="email">Email</label>
        <input type="email" name="email" id="email" autocomplete="email">
    </div>
    <div class="form-group">
        <label for="password">Contraseña</label>
        <input type="password" name="password" id="password" placeholder="Dejar en blanco para no cambiar" autocomplete="new-password">
        <small id="password-help">Mínimo 8 caracteres. Obligatoria para usuarios nuevos.</small>
    </div>
    <div class="form-group">
        <label for="role">Rol</label>
        <select name="role" id="role">
            <option value="user">Usuario</option>
            <option value="admin">Administrador</option>
        </select>
        <small>Los administradores tienen acceso completo. Los usuarios solo pueden ver.</small>
    </div>
    
    <div class="form-group" id="locations-group">
        <label>Locaciones Permitidas *</label>
        <small>Selecciona las locaciones que este usuario podrá visualizar</small>
        <div class="checkbox-controls">
            <a href="#" id="selectAllLocations">Seleccionar todo</a> |
            <a href="#" id="deselectAllLocations">Deseleccionar todo</a>
        </div>
        <div class="checkbox-group" id="locationsCheckboxes">
            <?php foreach ($all_locations as $location): ?>
            <label class="checkbox-label">
                <input type="checkbox" name="allowed_locations[]" value="<?= $location['id'] ?>">
                <?= htmlspecialchars($location['name']) ?>
            </label>
            <?php endforeach; ?>
            <?php if (empty($all_locations)): ?>
                <p style="color: #888; text-align: center; padding: 1rem;">No hay locaciones para asignar. <a href="locations_manager.php">Agregar locaciones</a>.</p>
            <?php endif; ?>
        </div>
    </div>
</form>
<?php
$user_form_content = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <style nonce="<?= htmlspecialchars($nonce) ?>">
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 8px;
            padding: 12px;
            background: #f5f5f5;
            border-radius: 4px;
            max-height: 200px;
            overflow-y: auto;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 4px;
        }
        .checkbox-label:hover {
            background: #e0e0e0;
            border-radius: 4px;
        }
        .checkbox-label input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        .locations-badge {
            display: inline-block;
            padding: 2px 8px;
            background: #e3f2fd;
            color: #1976d2;
            border-radius: 12px;
            font-size: 0.85em;
            margin: 2px;
        }
        .locations-cell { max-width: 300px; }
        #locations-group.hidden { display: none; }
        .checkbox-controls {
            font-size: 0.9em;
            margin: 8px 0;
        }
        .checkbox-controls a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        .checkbox-controls a:hover { text-decoration: underline; }
        /* Clases para reemplazar estilos en línea y cumplir con CSP */
        .badge-admin {
            background-color: #fff3cd;
            color: #856404;
        }
        .text-unassigned {
            color: #999;
        }
    </style>
</head>
<body class="page-manage">
    <div class="admin-container">
        <header class="admin-header">
            <h1>👥 Gestión de Usuarios del Portal</h1>
            <p>Crea, edita y elimina los usuarios con acceso al portal. Los usuarios solo pueden visualizar las locaciones asignadas.</p>
        </header>

        <div class="content">
            <?= $status_message ?>

            <div class="table-container">
                <table id="users-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre Completo</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Locaciones Permitidas</th>
                            <th>Último Login</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_users as $user): ?>
                        <tr data-user-id="<?= $user['id'] ?>">
                            <td data-label="Usuario"><?= htmlspecialchars($user['username']) ?></td>
                            <td data-label="Nombre Completo"><?= htmlspecialchars($user['full_name'] ?: '-') ?></td>
                            <td data-label="Email"><?= htmlspecialchars($user['email'] ?: '-') ?></td>
                            <td data-label="Rol"><?= htmlspecialchars(ucfirst($user['role'])) ?></td>
                            <td data-label="Locaciones" class="locations-cell">
                                <?php if ($user['role'] === 'admin'): ?>
                                    <span class="locations-badge badge-admin">Todas (Admin)</span>
                                <?php elseif (empty($user['locations'])): ?>
                                    <span class="text-unassigned">Sin asignar</span>
                                <?php else: ?>
                                    <?php foreach ($user['locations'] as $loc): ?>
                                        <span class="locations-badge"><?= htmlspecialchars($loc['name']) ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td data-label="Último Login"><?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Nunca' ?></td>
                            <td class="actions-cell">
                                <button type="button" class="edit-btn">Editar</button>
                                <form method="POST" class="delete-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="delete-btn" <?= ($user_count <= 1 || $user['id'] == $_SESSION['user_id']) ? 'disabled' : '' ?>>Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="button" id="add-user-btn" class="add-btn">Añadir Nuevo Usuario</button>
        </div>
    </div>

    <a href="index2.php" class="back-btn">← Volver al Portal</a>

    <?php
    echo render_modal([
        'id' => 'userModal',
        'title' => 'Gestionar Usuario',
        'size' => 'medium',
        'content' => $user_form_content,
        'form_id' => 'userForm',
        'submit_text' => 'Guardar Usuario'
    ]);
    ?>

    <script src="assets/js/modal-system.js" nonce="<?= htmlspecialchars($nonce) ?>"></script>
    <script nonce="<?= htmlspecialchars($nonce) ?>">
    document.addEventListener('DOMContentLoaded', function() {
        const allUsersData = <?= json_encode($all_users, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
        const roleSelect = document.getElementById('role');
        const locationsGroup = document.getElementById('locations-group');

        // Función para mostrar/ocultar el grupo de locaciones según el rol
        function toggleLocationsGroup() {
            if (roleSelect.value === 'admin') {
                locationsGroup.classList.add('hidden');
                // Desmarcar todos los checkboxes para admin
                document.querySelectorAll('#locationsCheckboxes input[type="checkbox"]').forEach(cb => {
                    cb.checked = false;
                });
            } else {
                locationsGroup.classList.remove('hidden');
            }
        }

        // Evento para cambiar el rol
        roleSelect.addEventListener('change', toggleLocationsGroup);

        function openModal(userData = null) {
            const form = document.getElementById('userForm');
            const modalTitle = document.querySelector('#userModal .modal-title');
            form.reset();
            
            // Desmarcar todos los checkboxes primero
            document.querySelectorAll('#locationsCheckboxes input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
            });
            
            if (userData) {
                modalTitle.textContent = 'Editar Usuario';
                document.getElementById('userId').value = userData.id;
                document.getElementById('username').value = userData.username;
                document.getElementById('full_name').value = userData.full_name || '';
                document.getElementById('email').value = userData.email || '';
                document.getElementById('role').value = userData.role;
                document.getElementById('password').placeholder = 'Dejar en blanco para no cambiar';
                document.getElementById('password').required = false;
                
                // Marcar las locaciones asignadas
                if (userData.locations) {
                    userData.locations.forEach(loc => {
                        const checkbox = document.querySelector('#locationsCheckboxes input[value="' + loc.id + '"]');
                        if (checkbox) checkbox.checked = true;
                    });
                }
            } else {
                modalTitle.textContent = 'Agregar Usuario';
                document.getElementById('userId').value = 'new_' + Date.now();
                document.getElementById('password').placeholder = 'Contraseña (obligatoria)';
                document.getElementById('password').required = true;
                document.getElementById('role').value = 'user'; // Por defecto usuario
            }
            
            toggleLocationsGroup();
            modalManager.open('userModal');
        }

        document.getElementById('add-user-btn').addEventListener('click', () => openModal());

        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.closest('tr').dataset.userId;
                const userData = allUsersData.find(u => u.id == userId);
                openModal(userData);
            });
        });

        // Manejar la confirmación de borrado de forma segura
        document.querySelectorAll('.delete-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('¿Estás seguro de que quieres eliminar a este usuario?')) {
                    e.preventDefault(); // Corregido: paréntesis faltante
                }
            });
        });

        // --- Lógica para Seleccionar/Deseleccionar Todas las Locaciones ---
        const selectAllBtn = document.getElementById('selectAllLocations');
        const deselectAllBtn = document.getElementById('deselectAllLocations');
        const locationsCheckboxes = document.querySelectorAll('#locationsCheckboxes input[type="checkbox"]');

        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function(e) {
                e.preventDefault();
                locationsCheckboxes.forEach(cb => { cb.checked = true; });
            });
        }

        if (deselectAllBtn) {
            deselectAllBtn.addEventListener('click', function(e) {
                e.preventDefault();
                locationsCheckboxes.forEach(cb => { cb.checked = false; });
            });
        }

        // Validación adicional antes de enviar el formulario
        document.getElementById('userForm').addEventListener('submit', function(e) {
            const role = document.getElementById('role').value;
            const checkedLocations = document.querySelectorAll('#locationsCheckboxes input[type="checkbox"]:checked');

            if (role === 'user' && checkedLocations.length === 0) {
                e.preventDefault();
                alert('Debes seleccionar al menos una locación para usuarios con rol "Usuario".');
                return false;
            }
        });
    });
    </script>
</body>
</html>
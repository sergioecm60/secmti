<?php
// users_manager.php - Página para administrar los usuarios del portal.

require_once 'bootstrap.php';
require_once 'database.php';

$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self';");

// Verificar autenticación y rol de administrador.
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$status_message = '';
$pdo = get_database_connection($config, true);

// --- MANEJO DEL GUARDADO DE USUARIOS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $status_message = '<div class="status-message error">Error de validación CSRF. Intente de nuevo.</div>';
    } else {
        try {
            $action = $_POST['action'] ?? '';
            $user_id = $_POST['user_id'] ?? null;
            $username = trim($_POST['username'] ?? '');
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'user';

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
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $status_message = '<div class="status-message success">Usuario eliminado correctamente.</div>';

            } elseif ($action === 'save') {
                // Validaciones
                if (empty($username)) throw new Exception('El nombre de usuario es obligatorio.');
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('El formato del email no es válido.');

                if (empty($user_id) || strpos($user_id, 'new_') === 0) { // Nuevo usuario
                    if (empty($password) || strlen($password) < 8) {
                        throw new Exception('La contraseña es obligatoria para usuarios nuevos y debe tener al menos 8 caracteres.');
                    }
                    $sql = "INSERT INTO users (username, full_name, email, pass_hash, role) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$username, $full_name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
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
                    $params[] = (int)$user_id;
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $status_message = '<div class="status-message success">Usuario actualizado correctamente.</div>';
                }
            }
        } catch (PDOException $e) {
            $status_message = '<div class="status-message error">' . ($e->getCode() == 23000 ? 'Error: El nombre de usuario o email ya existe.' : 'Error de base de datos.') . '</div>';
            error_log('Users Manager PDOException: ' . $e->getMessage());
        } catch (Exception $e) {
            $status_message = '<div class="status-message error">Error: ' . $e->getMessage() . '</div>';
        }
    }
}

// --- Carga de datos para mostrar en la tabla ---
$all_users = $pdo->query("SELECT id, username, full_name, email, role, last_login FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
$user_count = count($all_users);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="page-manage">
    <div class="admin-container">
        <header class="admin-header">
            <h1>👥 Gestión de Usuarios del Portal</h1>
            <p>Crea, edita y elimina los usuarios con acceso al portal.</p>
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
                            <td data-label="Último Login"><?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Nunca' ?></td>
                            <td class="actions-cell">
                                <button type="button" class="edit-btn">Editar</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de que quieres eliminar a este usuario?');">
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

    <!-- Modal para Agregar/Editar Usuario -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modalTitle">Agregar Usuario</h2>
            <form method="POST" id="userForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
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
                    <input type="email" name="email" id="email">
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
                </div>
                <div class="form-actions">
                    <button type="submit" class="save-btn">Guardar</button>
                    <button type="button" class="cancel-btn">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script nonce="<?= htmlspecialchars($nonce) ?>">
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('userModal');
        const allUsersData = <?= json_encode($all_users) ?>;

        function openModal(userData = null) {
            const form = document.getElementById('userForm');
            form.reset();
            if (userData) {
                document.getElementById('modalTitle').textContent = 'Editar Usuario';
                document.getElementById('userId').value = userData.id;
                document.getElementById('username').value = userData.username;
                document.getElementById('full_name').value = userData.full_name || '';
                document.getElementById('email').value = userData.email || '';
                document.getElementById('role').value = userData.role;
                document.getElementById('password').placeholder = 'Dejar en blanco para no cambiar';
                document.getElementById('password').required = false;
            } else {
                document.getElementById('modalTitle').textContent = 'Agregar Usuario';
                document.getElementById('userId').value = 'new_' + Date.now();
                document.getElementById('password').placeholder = 'Contraseña (obligatoria)';
                document.getElementById('password').required = true;
            }
            modal.classList.add('active');
        }

        function closeModal() {
            modal.classList.remove('active');
        }

        document.getElementById('add-user-btn').addEventListener('click', () => openModal());
        modal.querySelector('.close').addEventListener('click', closeModal);
        modal.querySelector('.cancel-btn').addEventListener('click', closeModal);

        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.closest('tr').dataset.userId;
                const userData = allUsersData.find(u => u.id == userId);
                openModal(userData);
            });
        });
    });
    </script>
</body>
</html>
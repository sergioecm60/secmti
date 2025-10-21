# 📋 REPORTE DE MEJORAS PARA SECMTI

**Fecha:** 2025-10-21
**Versión analizada:** 1.0.2
**Analista:** Claude Code

---

## 🔴 PRIORIDAD CRÍTICA - Seguridad

### 1. **Validación de IV en Clase de Encriptación**
**Archivo:** `src/Util/Encryption.php:55-58`

**Problema actual:**
```php
public function decrypt(string $encrypted_text): string|false {
    $data = base64_decode($encrypted_text, true);
    if ($data === false) {
        return false;
    }
    $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);
    $iv = substr($data, 0, $iv_length);  // ❌ No valida longitud del IV
```

**Riesgo:** Datos corruptos o manipulados pueden causar errores de descifrado silenciosos.

**Solución:**
```php
public function decrypt(string $encrypted_text): string|false {
    $data = base64_decode($encrypted_text, true);
    if ($data === false) {
        return false;
    }

    $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);

    // ✅ Validar que hay suficientes datos
    if (mb_strlen($data, '8bit') < $iv_length) {
        error_log('Encryption::decrypt - Datos insuficientes para extraer IV');
        return false;
    }

    $iv = substr($data, 0, $iv_length);
    $ciphertext = substr($data, $iv_length);

    // ✅ Validar que hay ciphertext
    if (empty($ciphertext)) {
        error_log('Encryption::decrypt - Ciphertext vacío');
        return false;
    }

    return openssl_decrypt($ciphertext, self::CIPHER_METHOD, $this->key, OPENSSL_RAW_DATA, $iv);
}
```

**Impacto:** Alto
**Esfuerzo:** 30 minutos

---

### 2. **Rate Limiting Basado en Sesión (Evitable)**
**Archivo:** `bootstrap.php:221-250`

**Problema:** El rate limiting actual usa `$_SESSION`, lo que permite evadir bloqueos limpiando cookies.

**Solución:** Implementar rate limiting basado en BD + IP:

**Paso 1:** Agregar tabla en `db/install.sql`
```sql
CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(255) NOT NULL COMMENT 'IP o user_id',
  `action` varchar(50) NOT NULL,
  `attempts` int(11) DEFAULT 1,
  `blocked_until` datetime DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_identifier_action` (`identifier`, `action`),
  KEY `idx_blocked_until` (`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
```

**Paso 2:** Nueva función en `bootstrap.php`
```php
/**
 * Rate limiting basado en base de datos
 * Más robusto que el basado en sesión
 */
function check_rate_limit_db(PDO $pdo, string $action, string $identifier = null, int $max_attempts = 5, int $window_seconds = 300): bool {
    $identifier = $identifier ?? IP_ADDRESS;
    $now = new DateTime();

    // Buscar registro existente
    $stmt = $pdo->prepare("
        SELECT attempts, blocked_until, updated_at
        FROM rate_limits
        WHERE identifier = ? AND action = ?
    ");
    $stmt->execute([$identifier, $action]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si está bloqueado, verificar si ya expiró
    if ($record && $record['blocked_until']) {
        $blocked_until = new DateTime($record['blocked_until']);
        if ($now < $blocked_until) {
            return false; // Aún bloqueado
        }
        // Expiró el bloqueo, resetear
        $stmt = $pdo->prepare("UPDATE rate_limits SET attempts = 0, blocked_until = NULL WHERE identifier = ? AND action = ?");
        $stmt->execute([$identifier, $action]);
        return true;
    }

    // Limpiar intentos antiguos
    if ($record) {
        $updated_at = new DateTime($record['updated_at']);
        $diff = $now->getTimestamp() - $updated_at->getTimestamp();

        if ($diff > $window_seconds) {
            // Ventana expirada, resetear
            $stmt = $pdo->prepare("UPDATE rate_limits SET attempts = 1, updated_at = NOW() WHERE identifier = ? AND action = ?");
            $stmt->execute([$identifier, $action]);
            return true;
        }

        // Incrementar intentos
        $new_attempts = $record['attempts'] + 1;

        if ($new_attempts >= $max_attempts) {
            // Bloquear
            $blocked_until = (clone $now)->modify("+{$window_seconds} seconds");
            $stmt = $pdo->prepare("UPDATE rate_limits SET attempts = ?, blocked_until = ? WHERE identifier = ? AND action = ?");
            $stmt->execute([$new_attempts, $blocked_until->format('Y-m-d H:i:s'), $identifier, $action]);
            error_log("Rate limit exceeded for action '{$action}' from: {$identifier}");
            return false;
        }

        // Incrementar
        $stmt = $pdo->prepare("UPDATE rate_limits SET attempts = ? WHERE identifier = ? AND action = ?");
        $stmt->execute([$new_attempts, $identifier, $action]);
        return true;
    }

    // Primer intento, crear registro
    $stmt = $pdo->prepare("INSERT INTO rate_limits (identifier, action, attempts) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE attempts = attempts + 1");
    $stmt->execute([$identifier, $action]);
    return true;
}
```

**Uso:**
```php
// En api/auth.php (línea ~31)
$pdo = get_database_connection($config, false);
if (!check_rate_limit_db($pdo, 'login_attempt', IP_ADDRESS, 5, 900)) {
    throw new Exception('Demasiados intentos. Intente más tarde.', 429);
}
```

**Impacto:** Muy Alto
**Esfuerzo:** 2-3 horas

---

### 3. **Auditoría de Descifrado de Credenciales**
**Archivo:** `api/datacenter.php:69`

**Problema:** No hay logs cuando se descifran credenciales sensibles. Esto dificulta auditorías de seguridad.

**Solución:**
```php
case 'get_password':
    if ($id <= 0) {
        throw new Exception('ID de credencial inválido', 400);
    }

    $type = $_REQUEST['type'] ?? null;

    $table_map = [
        'server_main'       => 'dc_servers',
        'dc_credential'     => 'dc_credentials',
        'hosting_account'   => 'dc_hosting_accounts',
        'hosting_ftp'       => 'dc_hosting_ftp_accounts',
        'hosting_email'     => 'dc_hosting_emails',
        'pc_equipment'      => 'pc_equipment'
    ];

    if (!isset($table_map[$type])) {
        throw new Exception('Tipo de credencial no válido', 400);
    }

    $table_name = $table_map[$type];

    $stmt = $pdo->prepare("SELECT `password` FROM `{$table_name}` WHERE id = ?");
    $stmt->execute([$id]);
    $encrypted_password = $stmt->fetchColumn();

    if ($encrypted_password === false || $encrypted_password === null) {
        throw new Exception('Contraseña no disponible', 404);
    }

    // Descifrar la contraseña
    $decrypted_password = decrypt_password($encrypted_password);
    if ($decrypted_password === false) {
        log_security_event('credential_decrypt_failed', "Type: {$type}, ID: {$id}");
        throw new Exception('No se pudo procesar la credencial.', 500);
    }

    // ✅ NUEVO: Auditoría de descifrado exitoso
    try {
        $log_stmt = $pdo->prepare("
            INSERT INTO dc_access_log (user_id, action, entity_type, entity_id, details, ip_address)
            VALUES (?, 'decrypt_credential', ?, ?, ?, ?)
        ");
        $log_stmt->execute([
            $_SESSION['user_id'],
            $type,
            $id,
            "Credential decrypted for type: {$type}",
            IP_ADDRESS
        ]);
    } catch (Exception $e) {
        error_log('Error logging credential access: ' . $e->getMessage());
    }

    echo json_encode(['success' => true, 'password' => $decrypted_password]);
    break;
```

**Beneficio:** Trazabilidad completa de accesos a credenciales para cumplimiento normativo.

**Impacto:** Alto
**Esfuerzo:** 30 minutos

---

### 4. **CSRF Token Sin Expiración**
**Archivo:** `bootstrap.php:369-371`

**Problema:** Los tokens CSRF nunca expiran, aumentando ventana de ataque.

**Solución:**
```php
// En bootstrap.php después de línea 371
// 6.7 Generar token CSRF si no existe o está expirado
if (empty($_SESSION['csrf_token']) ||
    !isset($_SESSION['csrf_token_created']) ||
    (time() - $_SESSION['csrf_token_created']) > 3600) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_created'] = time();
}
```

Actualizar función `verify_csrf_token` (línea 206):
```php
function verify_csrf_token(string $token): bool {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_created'])) {
        return false;
    }

    // Validar expiración (1 hora)
    if ((time() - $_SESSION['csrf_token_created']) > 3600) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_created']);
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}
```

**Impacto:** Medio-Alto
**Esfuerzo:** 20 minutos

---

### 5. **Validación de Fortaleza de Contraseñas**

**Problema:** No hay validación de complejidad de contraseñas al crear usuarios.

**Solución:** Crear nuevo archivo `include/password_validator.php`

```php
<?php
/**
 * Validador de fortaleza de contraseñas
 */
class PasswordValidator {
    const MIN_LENGTH = 12;
    const REQUIRE_UPPERCASE = true;
    const REQUIRE_LOWERCASE = true;
    const REQUIRE_NUMBERS = true;
    const REQUIRE_SPECIAL = true;

    /**
     * Valida una contraseña contra las políticas de seguridad
     *
     * @param string $password Contraseña a validar
     * @return array ['valid' => bool, 'errors' => array, 'strength' => string]
     */
    public static function validate(string $password): array {
        $errors = [];

        if (strlen($password) < self::MIN_LENGTH) {
            $errors[] = "La contraseña debe tener al menos " . self::MIN_LENGTH . " caracteres";
        }

        if (self::REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Debe contener al menos una letra mayúscula";
        }

        if (self::REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Debe contener al menos una letra minúscula";
        }

        if (self::REQUIRE_NUMBERS && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Debe contener al menos un número";
        }

        if (self::REQUIRE_SPECIAL && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Debe contener al menos un carácter especial (!@#$%^&*)";
        }

        // Verificar contraseñas comunes (top 100)
        $common_passwords = [
            'password', '12345678', 'qwerty', 'admin', 'welcome',
            'password123', 'admin123', 'letmein', 'monkey', '1234567890'
        ];
        if (in_array(strtolower($password), $common_passwords)) {
            $errors[] = "Esta contraseña es demasiado común y fácil de adivinar";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'strength' => self::calculateStrength($password)
        ];
    }

    /**
     * Calcula la fortaleza de una contraseña
     *
     * @param string $password
     * @return string 'débil' | 'media' | 'fuerte'
     */
    private static function calculateStrength(string $password): string {
        $score = 0;

        if (strlen($password) >= 8) $score++;
        if (strlen($password) >= 12) $score++;
        if (strlen($password) >= 16) $score++;
        if (preg_match('/[a-z]/', $password)) $score++;
        if (preg_match('/[A-Z]/', $password)) $score++;
        if (preg_match('/[0-9]/', $password)) $score++;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score++;

        // Penalizar patrones comunes
        if (preg_match('/(.)\1{2,}/', $password)) $score--; // Caracteres repetidos
        if (preg_match('/^[0-9]+$/', $password)) $score--; // Solo números

        if ($score < 4) return 'débil';
        if ($score < 6) return 'media';
        return 'fuerte';
    }
}
```

**Uso en users_manager.php:**
```php
require_once 'include/password_validator.php';

// Al crear/actualizar usuario
$password = $_POST['password'] ?? '';
$validation = PasswordValidator::validate($password);

if (!$validation['valid']) {
    echo json_encode([
        'success' => false,
        'message' => 'Contraseña no cumple requisitos de seguridad',
        'errors' => $validation['errors']
    ]);
    exit;
}
```

**Impacto:** Alto (previene contraseñas débiles)
**Esfuerzo:** 1-2 horas

---

## 🟡 PRIORIDAD ALTA - Rendimiento

### 6. **Falta de Índices en Base de Datos**
**Archivo:** `db/install.sql`

**Problema:** Consultas lentas en tablas sin índices apropiados. A medida que crezcan los datos, las queries serán muy lentas.

**Solución:** Añadir al final de `db/install.sql`

```sql
-- ============================================================================
-- ÍNDICES PARA OPTIMIZACIÓN DE CONSULTAS
-- ============================================================================

-- Índices para dc_servers
ALTER TABLE `dc_servers`
  ADD INDEX `idx_location_id` (`location_id`),
  ADD INDEX `idx_status` (`status`),
  ADD INDEX `idx_type` (`type`),
  ADD INDEX `idx_created_at` (`created_at`),
  ADD INDEX `idx_location_status` (`location_id`, `status`);

-- Índices para dc_services
ALTER TABLE `dc_services`
  ADD INDEX `idx_server_id` (`server_id`),
  ADD INDEX `idx_name` (`name`);

-- Índices para dc_credentials
ALTER TABLE `dc_credentials`
  ADD INDEX `idx_service_id` (`service_id`);

-- Índices para dc_access_log
ALTER TABLE `dc_access_log`
  ADD INDEX `idx_user_id` (`user_id`),
  ADD INDEX `idx_action` (`action`),
  ADD INDEX `idx_created_at` (`created_at`),
  ADD INDEX `idx_ip_address` (`ip_address`),
  ADD INDEX `idx_user_action_date` (`user_id`, `action`, `created_at`);

-- Índices para hosting
ALTER TABLE `dc_hosting_accounts`
  ADD INDEX `idx_server_id` (`server_id`),
  ADD INDEX `idx_domain` (`domain`);

ALTER TABLE `dc_hosting_emails`
  ADD INDEX `idx_server_id` (`server_id`),
  ADD INDEX `idx_email_address` (`email_address`);

ALTER TABLE `dc_hosting_ftp_accounts`
  ADD INDEX `idx_server_id` (`server_id`);

-- Índices para locations
ALTER TABLE `dc_locations`
  ADD INDEX `idx_name` (`name`);

-- Índices para parque informático
ALTER TABLE `pc_equipment`
  ADD INDEX `idx_location_id` (`location_id`),
  ADD INDEX `idx_status` (`status`),
  ADD INDEX `idx_asset_tag` (`asset_tag`);

-- Índices para usuarios
ALTER TABLE `users`
  ADD INDEX `idx_username` (`username`),
  ADD INDEX `idx_role` (`role`);
```

**Script de migración para BD existentes:** `db/migrations/001_add_indexes.sql`
```sql
-- Ejecutar solo si la BD ya existe y tiene datos
-- Verificar índices existentes antes: SHOW INDEX FROM dc_servers;

ALTER TABLE `dc_servers` ADD INDEX IF NOT EXISTS `idx_location_id` (`location_id`);
-- ... (mismo contenido que arriba pero con IF NOT EXISTS)
```

**Beneficio esperado:**
- Queries 10-100x más rápidas en tablas grandes
- Reducción de carga de CPU en el servidor MySQL

**Impacto:** Muy Alto (crítico para escalabilidad)
**Esfuerzo:** 30 minutos

---

### 7. **Optimización de N+1 Queries**

**Problema:** En `datacenter_view.php`, se carga cada servicio con una query individual por servidor.

**Solución:** Cargar todo con JOINs optimizados

```php
// En datacenter_view.php o en un nuevo Repository
function getServersWithStats(PDO $pdo, ?int $location_id = null): array {
    $where = $location_id ? "WHERE s.location_id = :location_id" : "";

    $sql = "
        SELECT
            s.id as server_id,
            s.label as server_label,
            s.type,
            s.status,
            s.hw_model,
            s.hw_cpu,
            s.hw_ram,
            s.net_ip_lan,
            s.net_ip_wan,
            l.name as location_name,
            l.id as location_id,
            COUNT(DISTINCT srv.id) as services_count,
            COUNT(DISTINCT c.id) as credentials_count
        FROM dc_servers s
        LEFT JOIN dc_locations l ON s.location_id = l.id
        LEFT JOIN dc_services srv ON s.id = srv.server_id
        LEFT JOIN dc_credentials c ON srv.id = c.service_id
        {$where}
        GROUP BY s.id
        ORDER BY l.name, s.label
    ";

    $stmt = $pdo->prepare($sql);
    if ($location_id) {
        $stmt->bindValue(':location_id', $location_id, PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

**Beneficio:**
- De N+1 queries a solo 1 query
- Ejemplo: 50 servidores = de 51 queries a 1 query (98% reducción)

**Impacto:** Muy Alto
**Esfuerzo:** 2-3 horas

---

### 8. **Minificación de Assets**

**Problema:**
- CSS: 106KB sin comprimir
- JS: 64KB sin comprimir
- No hay versionado de cache

**Solución:**

**Paso 1:** Instalar herramientas
```bash
cd /home/user/secmti
npm init -y
npm install --save-dev cssnano postcss postcss-cli terser
```

**Paso 2:** Crear `package.json` scripts
```json
{
  "name": "secmti",
  "version": "1.0.2",
  "scripts": {
    "minify-css": "postcss assets/css/*.css --use cssnano --dir assets/css/dist",
    "minify-js": "terser assets/js/*.js --compress --mangle --output-dir assets/js/dist",
    "build": "npm run minify-css && npm run minify-js",
    "watch": "npm run build -- --watch"
  },
  "devDependencies": {
    "cssnano": "^6.0.0",
    "postcss": "^8.4.0",
    "postcss-cli": "^11.0.0",
    "terser": "^5.26.0"
  }
}
```

**Paso 3:** Crear `postcss.config.js`
```js
module.exports = {
  plugins: {
    cssnano: {
      preset: ['default', {
        discardComments: { removeAll: true },
        normalizeWhitespace: true,
        minifyFontValues: true,
        minifySelectors: true
      }]
    }
  }
}
```

**Paso 4:** Actualizar referencias en templates
```php
// En templates/navbar.php u otros
<?php $v = '1.0.2'; // Versión para cache busting ?>
<link rel="stylesheet" href="assets/css/dist/main.css?v=<?= $v ?>">
<script src="assets/js/dist/main.js?v=<?= $v ?>"></script>
```

**Beneficio esperado:**
- CSS: 106KB → ~42KB (60% reducción)
- JS: 64KB → ~26KB (59% reducción)
- Ahorro total de ancho de banda: ~102KB por carga de página

**Impacto:** Alto (mejora tiempos de carga)
**Esfuerzo:** 2-3 horas

---

## 🟢 PRIORIDAD MEDIA - Arquitectura

### 9. **Capa de Repositorio para Datos**

**Problema:** Lógica de BD dispersa en múltiples archivos, duplicación de código.

**Solución:** Implementar patrón Repository

**Archivo:** `src/Repository/ServerRepository.php`
```php
<?php
namespace SecMTI\Repository;

use PDO;

/**
 * Repositorio para operaciones de servidores
 */
class ServerRepository {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Buscar servidor por ID
     */
    public function findById(int $id): ?array {
        $stmt = $this->pdo->prepare("
            SELECT s.*, l.name as location_name
            FROM dc_servers s
            LEFT JOIN dc_locations l ON s.location_id = l.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $result['net_dns'] = json_decode($result['net_dns'] ?? '[]', true);
        }

        return $result ?: null;
    }

    /**
     * Listar todos los servidores activos
     */
    public function findAllActive(): array {
        $stmt = $this->pdo->query("
            SELECT s.*, l.name as location_name
            FROM dc_servers s
            LEFT JOIN dc_locations l ON s.location_id = l.id
            WHERE s.status = 'activo'
            ORDER BY l.name, s.label
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar servidores por ubicación
     */
    public function findByLocation(int $location_id): array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM dc_servers
            WHERE location_id = ?
            ORDER BY label
        ");
        $stmt->execute([$location_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crear nuevo servidor
     */
    public function create(array $data): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO dc_servers (
                server_id, label, type, location_id, status,
                hw_model, hw_cpu, hw_ram, hw_disk,
                net_ip_lan, net_ip_wan, net_host_external,
                net_gateway, net_dns, notes, username, password, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['server_id'] ?? null,
            $data['label'],
            $data['type'],
            $data['location_id'],
            $data['status'] ?? 'activo',
            $data['hw_model'] ?? null,
            $data['hw_cpu'] ?? null,
            $data['hw_ram'] ?? null,
            $data['hw_disk'] ?? null,
            $data['net_ip_lan'] ?? null,
            $data['net_ip_wan'] ?? null,
            $data['net_host_external'] ?? null,
            $data['net_gateway'] ?? null,
            isset($data['net_dns']) ? json_encode($data['net_dns']) : '[]',
            $data['notes'] ?? null,
            $data['username'] ?? null,
            $data['password'] ?? null,
            $data['created_by']
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Actualizar servidor
     */
    public function update(int $id, array $data): bool {
        $stmt = $this->pdo->prepare("
            UPDATE dc_servers SET
                label = ?,
                type = ?,
                location_id = ?,
                status = ?,
                hw_model = ?,
                hw_cpu = ?,
                hw_ram = ?,
                hw_disk = ?,
                net_ip_lan = ?,
                net_ip_wan = ?,
                net_host_external = ?,
                net_gateway = ?,
                net_dns = ?,
                notes = ?,
                username = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        return $stmt->execute([
            $data['label'],
            $data['type'],
            $data['location_id'],
            $data['status'],
            $data['hw_model'] ?? null,
            $data['hw_cpu'] ?? null,
            $data['hw_ram'] ?? null,
            $data['hw_disk'] ?? null,
            $data['net_ip_lan'] ?? null,
            $data['net_ip_wan'] ?? null,
            $data['net_host_external'] ?? null,
            $data['net_gateway'] ?? null,
            isset($data['net_dns']) ? json_encode($data['net_dns']) : '[]',
            $data['notes'] ?? null,
            $data['username'] ?? null,
            $id
        ]);
    }

    /**
     * Eliminar servidor (soft delete)
     */
    public function delete(int $id): bool {
        // Soft delete: cambiar status en lugar de eliminar
        $stmt = $this->pdo->prepare("
            UPDATE dc_servers SET status = 'eliminado', updated_at = NOW() WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    /**
     * Eliminar permanentemente
     */
    public function hardDelete(int $id): bool {
        // Primero eliminar servicios y credenciales asociadas
        $this->pdo->beginTransaction();

        try {
            // Obtener IDs de servicios
            $stmt = $this->pdo->prepare("SELECT id FROM dc_services WHERE server_id = ?");
            $stmt->execute([$id]);
            $service_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Eliminar credenciales
            if (!empty($service_ids)) {
                $placeholders = implode(',', array_fill(0, count($service_ids), '?'));
                $stmt = $this->pdo->prepare("DELETE FROM dc_credentials WHERE service_id IN ($placeholders)");
                $stmt->execute($service_ids);
            }

            // Eliminar servicios
            $stmt = $this->pdo->prepare("DELETE FROM dc_services WHERE server_id = ?");
            $stmt->execute([$id]);

            // Eliminar servidor
            $stmt = $this->pdo->prepare("DELETE FROM dc_servers WHERE id = ?");
            $stmt->execute([$id]);

            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            error_log("Error deleting server: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Contar servidores por ubicación
     */
    public function countByLocation(int $location_id): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM dc_servers WHERE location_id = ?");
        $stmt->execute([$location_id]);
        return (int)$stmt->fetchColumn();
    }
}
```

**Uso:**
```php
// En datacenter_view.php
require_once 'bootstrap.php';
use SecMTI\Repository\ServerRepository;

$pdo = get_database_connection($config);
$serverRepo = new ServerRepository($pdo);

$servers = $serverRepo->findAllActive();
$server = $serverRepo->findById($id);
```

**Beneficios:**
- Código reutilizable
- Más fácil de testear
- Lógica de BD centralizada
- Reduce duplicación

**Impacto:** Medio (mejora mantenibilidad)
**Esfuerzo:** 1 día

---

### 10. **Validador Centralizado de Entrada**

**Problema:** Validación duplicada en cada endpoint API.

**Solución:** Crear `src/Validator/InputValidator.php`

```php
<?php
namespace SecMTI\Validator;

/**
 * Validador centralizado para inputs
 */
class InputValidator {
    private array $errors = [];
    private array $data;

    public function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Validar campos requeridos
     */
    public function required(array $fields): self {
        foreach ($fields as $field) {
            if (!isset($this->data[$field]) || trim($this->data[$field]) === '') {
                $this->errors[$field] = "El campo {$field} es requerido";
            }
        }
        return $this;
    }

    /**
     * Validar longitud de string
     */
    public function length(string $field, int $min, int $max): self {
        if (isset($this->data[$field])) {
            $length = strlen($this->data[$field]);
            if ($length < $min || $length > $max) {
                $this->errors[$field] = "El campo {$field} debe tener entre {$min} y {$max} caracteres";
            }
        }
        return $this;
    }

    /**
     * Validar email
     */
    public function email(string $field): self {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "El campo {$field} debe ser un email válido";
        }
        return $this;
    }

    /**
     * Validar IP
     */
    public function ip(string $field, bool $allowPrivate = true): self {
        if (isset($this->data[$field])) {
            $flags = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
            if (!$allowPrivate) {
                $flags |= FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
            }

            if (!filter_var($this->data[$field], FILTER_VALIDATE_IP, $flags)) {
                $this->errors[$field] = "El campo {$field} debe ser una IP válida";
            }
        }
        return $this;
    }

    /**
     * Validar URL
     */
    public function url(string $field): self {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_URL)) {
            $this->errors[$field] = "El campo {$field} debe ser una URL válida";
        }
        return $this;
    }

    /**
     * Validar número entero
     */
    public function integer(string $field, ?int $min = null, ?int $max = null): self {
        if (isset($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_INT)) {
                $this->errors[$field] = "El campo {$field} debe ser un número entero";
            } else {
                $value = (int)$this->data[$field];
                if ($min !== null && $value < $min) {
                    $this->errors[$field] = "El campo {$field} debe ser mayor o igual a {$min}";
                }
                if ($max !== null && $value > $max) {
                    $this->errors[$field] = "El campo {$field} debe ser menor o igual a {$max}";
                }
            }
        }
        return $this;
    }

    /**
     * Validar que esté en un conjunto de valores
     */
    public function in(string $field, array $allowed): self {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowed, true)) {
            $allowedStr = implode(', ', $allowed);
            $this->errors[$field] = "El campo {$field} debe ser uno de: {$allowedStr}";
        }
        return $this;
    }

    /**
     * Validación personalizada
     */
    public function custom(string $field, callable $callback, string $errorMessage): self {
        if (isset($this->data[$field])) {
            if (!$callback($this->data[$field])) {
                $this->errors[$field] = $errorMessage;
            }
        }
        return $this;
    }

    /**
     * Verificar si pasó todas las validaciones
     */
    public function isValid(): bool {
        return empty($this->errors);
    }

    /**
     * Obtener errores
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * Obtener primer error
     */
    public function getFirstError(): ?string {
        return !empty($this->errors) ? array_values($this->errors)[0] : null;
    }
}
```

**Uso en APIs:**
```php
// En api/datacenter.php (ejemplo)
$input = json_decode(file_get_contents('php://input'), true);

$validator = new InputValidator($input);
$validator
    ->required(['label', 'type', 'location_id'])
    ->length('label', 3, 100)
    ->in('type', ['físico', 'virtual', 'contenedor', 'cloud', 'ISP'])
    ->integer('location_id', 1)
    ->ip('net_ip_lan', true)
    ->url('net_host_external');

if (!$validator->isValid()) {
    throw new Exception(json_encode($validator->getErrors()), 400);
}
```

**Impacto:** Medio
**Esfuerzo:** 3-4 horas

---

## 🔵 PRIORIDAD BAJA - Infraestructura y Testing

### 11. **Tests Unitarios**

**Problema:** No hay tests automatizados, aumenta riesgo de regresiones.

**Solución:** Implementar PHPUnit

**Paso 1:** Instalar PHPUnit
```bash
composer require --dev phpunit/phpunit ^10.0
```

**Paso 2:** Crear `phpunit.xml`
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php"
         colors="true"
         stopOnFailure="false">
    <testsuites>
        <testsuite name="Unit Tests">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

**Paso 3:** Test de ejemplo - `tests/Unit/EncryptionTest.php`
```php
<?php
use PHPUnit\Framework\TestCase;
use SecMTI\Util\Encryption;

class EncryptionTest extends TestCase {
    private Encryption $encryption;

    protected function setUp(): void {
        $key = random_bytes(32);
        $this->encryption = new Encryption($key);
    }

    public function testEncryptDecryptRoundTrip(): void {
        $plaintext = 'MiContraseñaSecreta123!@#';

        $encrypted = $this->encryption->encrypt($plaintext);
        $this->assertNotFalse($encrypted);
        $this->assertNotEquals($plaintext, $encrypted);

        $decrypted = $this->encryption->decrypt($encrypted);
        $this->assertEquals($plaintext, $decrypted);
    }

    public function testDecryptInvalidData(): void {
        $result = $this->encryption->decrypt('invalid_base64_!!!');
        $this->assertFalse($result);
    }

    public function testDecryptEmptyString(): void {
        $result = $this->encryption->decrypt('');
        $this->assertFalse($result);
    }

    public function testDecryptTooShort(): void {
        $result = $this->encryption->decrypt(base64_encode('short'));
        $this->assertFalse($result);
    }

    public function testConstructorThrowsOnInvalidKey(): void {
        $this->expectException(\InvalidArgumentException::class);
        new Encryption('clave_muy_corta');
    }
}
```

**Paso 4:** Test de validador - `tests/Unit/InputValidatorTest.php`
```php
<?php
use PHPUnit\Framework\TestCase;
use SecMTI\Validator\InputValidator;

class InputValidatorTest extends TestCase {
    public function testRequiredFieldsValidation(): void {
        $data = ['name' => 'Test', 'email' => ''];
        $validator = new InputValidator($data);
        $validator->required(['name', 'email', 'phone']);

        $this->assertFalse($validator->isValid());
        $errors = $validator->getErrors();
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('phone', $errors);
    }

    public function testEmailValidation(): void {
        $validator = new InputValidator(['email' => 'invalid-email']);
        $validator->email('email');

        $this->assertFalse($validator->isValid());
    }

    public function testIPValidation(): void {
        $validator = new InputValidator(['ip' => '192.168.1.1']);
        $validator->ip('ip');

        $this->assertTrue($validator->isValid());
    }
}
```

**Ejecutar tests:**
```bash
./vendor/bin/phpunit
```

**Impacto:** Medio-Alto (largo plazo)
**Esfuerzo:** 1-2 semanas

---

### 12. **Configuración Docker**

**Problema:** Instalación manual compleja, inconsistencias entre entornos.

**Solución:** Docker Compose para entorno reproducible

**Archivo:** `docker-compose.yml`
```yaml
version: '3.8'

services:
  web:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: secmti_web
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/html
      - ./logs:/var/www/html/logs
    environment:
      - APACHE_DOCUMENT_ROOT=/var/www/html
    depends_on:
      - db
    networks:
      - secmti_network
    restart: unless-stopped

  db:
    image: mariadb:10.11
    container_name: secmti_db
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD:-root_secure_pass}
      MYSQL_DATABASE: ${DB_NAME:-portal_db}
      MYSQL_USER: ${DB_USER:-secmti_user}
      MYSQL_PASSWORD: ${DB_PASS:-secmti_pass}
    volumes:
      - db_data:/var/lib/mysql
      - ./db/install.sql:/docker-entrypoint-initdb.d/001_install.sql
      - ./db/migrations:/docker-entrypoint-initdb.d/migrations
    ports:
      - "3306:3306"
    networks:
      - secmti_network
    restart: unless-stopped

  phpmyadmin:
    image: phpmyadmin/phpmyadmin:latest
    container_name: secmti_phpmyadmin
    environment:
      PMA_HOST: db
      PMA_PORT: 3306
      PMA_USER: root
      PMA_PASSWORD: ${DB_ROOT_PASSWORD:-root_secure_pass}
    ports:
      - "8081:80"
    depends_on:
      - db
    networks:
      - secmti_network
    restart: unless-stopped

volumes:
  db_data:
    driver: local

networks:
  secmti_network:
    driver: bridge
```

**Archivo:** `Dockerfile`
```dockerfile
FROM php:8.2-apache

# Instalar extensiones PHP necesarias
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_mysql mysqli \
    && docker-php-ext-enable pdo_mysql mysqli

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar Apache
RUN a2enmod rewrite
COPY .htaccess /var/www/html/.htaccess

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Instalar dependencias PHP
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

EXPOSE 80
```

**Archivo:** `.dockerignore`
```
.git
.gitignore
.env
config.php
logs/*
vendor/
node_modules/
```

**Uso:**
```bash
# Iniciar
docker-compose up -d

# Ver logs
docker-compose logs -f web

# Detener
docker-compose down

# Reconstruir
docker-compose up -d --build
```

**Beneficios:**
- Instalación en 1 comando
- Entorno idéntico en desarrollo y producción
- Fácil escalar (múltiples instancias web)

**Impacto:** Alto (facilita desarrollo)
**Esfuerzo:** 4-6 horas

---

## 📊 RESUMEN EJECUTIVO

### Distribución por Prioridad

| Prioridad | Cantidad | Esfuerzo Total Estimado |
|-----------|----------|-------------------------|
| 🔴 Crítica (Seguridad) | 5 | 5-8 horas |
| 🟡 Alta (Rendimiento) | 3 | 6-10 horas |
| 🟢 Media (Arquitectura) | 2 | 1-2 días |
| 🔵 Baja (Infraestructura) | 2 | 2-3 semanas |

### Impacto Esperado

**Seguridad:**
- ✅ Protección contra ataques de timing
- ✅ Rate limiting robusto no evitable
- ✅ Auditoría completa de accesos
- ✅ Tokens CSRF con expiración
- ✅ Contraseñas seguras obligatorias

**Rendimiento:**
- ⚡ 40-60% reducción en tamaño de assets
- ⚡ 10-100x mejora en queries con índices
- ⚡ 98% reducción de queries con optimización N+1

**Mantenibilidad:**
- 🔧 Código más limpio y reutilizable
- 🔧 Validación centralizada
- 🔧 Arquitectura escalable

---

## 🎯 PLAN DE IMPLEMENTACIÓN RECOMENDADO

### Sprint 1 (Semana 1) - Seguridad Crítica
**Objetivo:** Cerrar brechas de seguridad urgentes

- [ ] Mejora #1: Validación de IV en encriptación (30 min)
- [ ] Mejora #3: Auditoría de descifrado (30 min)
- [ ] Mejora #4: Expiración CSRF tokens (20 min)
- [ ] Mejora #6: Índices en BD (30 min)
- [ ] Testing de las 4 mejoras anteriores (2 horas)

**Total:** ~4-5 horas
**Resultado:** Sistema significativamente más seguro

---

### Sprint 2 (Semana 2) - Seguridad y Rendimiento
**Objetivo:** Rate limiting robusto y optimizaciones

- [ ] Mejora #2: Rate limiting en BD (3 horas)
- [ ] Mejora #5: Validador de contraseñas (2 horas)
- [ ] Mejora #7: Optimización N+1 queries (3 horas)
- [ ] Testing integral (2 horas)

**Total:** ~10 horas
**Resultado:** Prevención de brute force + queries optimizadas

---

### Sprint 3 (Semana 3) - Optimización Frontend
**Objetivo:** Mejorar tiempos de carga

- [ ] Mejora #8: Setup de minificación (2 horas)
- [ ] Minificación de todos los assets (1 hora)
- [ ] Implementar cache busting (1 hora)
- [ ] Testing de rendimiento (1 hora)

**Total:** ~5 horas
**Resultado:** 60% reducción en tamaño de assets

---

### Sprint 4 (Semana 4) - Arquitectura
**Objetivo:** Código más mantenible

- [ ] Mejora #9: Capa de repositorio (1 día)
- [ ] Mejora #10: Validador centralizado (4 horas)
- [ ] Refactorizar código existente para usar nuevas clases (4 horas)

**Total:** ~2 días
**Resultado:** Código más limpio y reutilizable

---

### Backlog (Futuro)
**Para cuando haya tiempo:**

- [ ] Mejora #11: Tests unitarios
- [ ] Mejora #12: Configuración Docker
- [ ] CI/CD pipeline
- [ ] Documentación API (OpenAPI/Swagger)

---

## 🔍 NOTAS ADICIONALES

### Consideraciones de Seguridad
1. **Backups:** Implementar antes de hacer cambios en BD
2. **Testing:** Probar en entorno de desarrollo primero
3. **Rollback plan:** Tener plan B si algo falla

### Métricas de Éxito
- Tiempo de carga de página: objetivo < 2 segundos
- Queries por página: objetivo < 10 queries
- Cobertura de tests: objetivo > 70%
- Sin vulnerabilidades críticas en auditoría

### Compatibilidad
- PHP 8.0+ (actual: ✅)
- MySQL 5.7+ / MariaDB 10.3+ (actual: ✅)
- Navegadores modernos (Chrome 90+, Firefox 88+, Safari 14+)

---

**Documento generado:** 2025-10-21
**Próxima revisión recomendada:** Después de Sprint 3

---

## 📞 CONTACTO

Para consultas sobre estas mejoras, contactar al equipo de desarrollo.

**IMPORTANTE:** Este documento es confidencial y contiene información técnica sobre la arquitectura de seguridad del sistema.

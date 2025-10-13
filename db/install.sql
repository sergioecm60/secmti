-- ============================================================================
-- ============================================================================
-- INSTALADOR INTELIGENTE DE BASE DE DATOS - PORTAL SECMTI
-- ============================================================================
-- Versión: 1.0.0
-- Fecha: 2025-10-09
-- Descripción: Script de instalación/actualización que puede ejecutarse
--              múltiples veces sin perder datos existentes
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "-03:00";
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- PASO 1: CREAR BASE DE DATOS SI NO EXISTE
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `portal_db` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_spanish_ci;

USE `portal_db`;

-- ============================================================================
-- PASO 2: CREAR/ACTUALIZAR TABLAS
-- ============================================================================

-- Tabla: users
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) COLLATE utf8mb4_spanish_ci NOT NULL,
  `pass_hash` VARCHAR(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `role` ENUM('admin','user') COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'user',
  `full_name` VARCHAR(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `email` VARCHAR(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `failed_login_attempts` INT NOT NULL DEFAULT 0,
  `lockout_until` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Usuarios del sistema';

-- Tabla: dc_locations
CREATE TABLE IF NOT EXISTS `dc_locations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `address` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` TEXT COLLATE utf8mb4_spanish_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Ubicaciones físicas de los servidores';

-- Tabla: dc_servers
CREATE TABLE IF NOT EXISTS `dc_servers` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `server_id` VARCHAR(100) COLLATE utf8mb4_spanish_ci NOT NULL,
  `label` VARCHAR(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `type` ENUM('physical','virtual','container','cloud','isp') COLLATE utf8mb4_spanish_ci DEFAULT 'physical',
  `location_id` INT DEFAULT NULL,
  `status` ENUM('active','inactive','maintenance') COLLATE utf8mb4_spanish_ci DEFAULT 'active',
  `hw_model` VARCHAR(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `hw_cpu` VARCHAR(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `hw_ram` VARCHAR(50) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `hw_disk` VARCHAR(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `net_ip_lan` VARCHAR(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `net_ip_wan` VARCHAR(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `net_host_external` VARCHAR(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `net_gateway` VARCHAR(50) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `net_dns` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON array de DNS',
  `notes` TEXT COLLATE utf8mb4_spanish_ci,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pass_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `server_id` (`server_id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  KEY `idx_label` (`label`),
  KEY `fk_created_by` (`created_by`),
  KEY `fk_location_id` (`location_id`),
  CONSTRAINT `fk_servers_location` FOREIGN KEY (`location_id`) REFERENCES `dc_locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Servidores físicos, virtuales y contenedores';

-- Tabla: dc_services
CREATE TABLE IF NOT EXISTS `dc_services` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `server_id` INT NOT NULL,
  `service_id` VARCHAR(100) COLLATE utf8mb4_spanish_ci NOT NULL,
  `name` VARCHAR(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `url_internal` VARCHAR(500) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `url_external` VARCHAR(500) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `port` VARCHAR(10) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `protocol` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'https',
  `notes` TEXT COLLATE utf8mb4_spanish_ci,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_service` (`server_id`,`service_id`),
  KEY `idx_server_id` (`server_id`),
  KEY `idx_name` (`name`),
  KEY `idx_protocol` (`protocol`),
  CONSTRAINT `fk_services_server` FOREIGN KEY (`server_id`) REFERENCES `dc_servers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Servicios instalados en servidores';

-- Tabla: dc_credentials
CREATE TABLE IF NOT EXISTS `dc_credentials` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `service_id` INT NOT NULL,
  `credential_id` VARCHAR(100) COLLATE utf8mb4_spanish_ci NOT NULL,
  `username` VARCHAR(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `password` VARCHAR(500) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'IMPORTANTE: Guardar cifrado con la clave de la aplicación (AES-256-CBC).',
  `role` VARCHAR(50) COLLATE utf8mb4_spanish_ci DEFAULT 'user',
  `notes` TEXT COLLATE utf8mb4_spanish_ci,
  `last_password_change` TIMESTAMP NULL DEFAULT NULL,
  `password_expires_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_credential` (`service_id`,`credential_id`),
  KEY `idx_service_id` (`service_id`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`),
  CONSTRAINT `fk_credentials_service` FOREIGN KEY (`service_id`) REFERENCES `dc_services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Credenciales de acceso';

-- Tabla: dc_access_log
CREATE TABLE IF NOT EXISTS `dc_access_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'view, edit, create, delete, copy_password',
  `entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'server, service, credential',
  `entity_id` int NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON con detalles',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_ip` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Auditoría de accesos';

-- Tabla: dc_hosting_servers
CREATE TABLE IF NOT EXISTS `dc_hosting_servers` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `hostname` VARCHAR(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `label` VARCHAR(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `webmail_port` INT DEFAULT 2096,
  `cpanel_port` INT DEFAULT 2083,
  `notes` TEXT COLLATE utf8mb4_spanish_ci,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hostname` (`hostname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Servidores de Hosting (cPanel/WHM)';

-- Tabla: dc_hosting_accounts
CREATE TABLE IF NOT EXISTS `dc_hosting_accounts` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `server_id` INT NOT NULL,
  `username` VARCHAR(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `password` VARCHAR(500) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Cifrado con la clave de la aplicación.',
  `domain` VARCHAR(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `label` VARCHAR(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `notes` TEXT COLLATE utf8mb4_spanish_ci,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_server_id` (`server_id`),
  CONSTRAINT `fk_hosting_accounts_server` FOREIGN KEY (`server_id`) REFERENCES `dc_hosting_servers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Cuentas de cPanel en servidores de hosting';

-- Tabla: dc_hosting_emails
CREATE TABLE IF NOT EXISTS `dc_hosting_emails` (
  `id` int NOT NULL AUTO_INCREMENT,
  `server_id` int NOT NULL,
  `email_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Cifrado con la clave de la aplicación.',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_server_id` (`server_id`),
  CONSTRAINT `fk_hosting_emails_server` FOREIGN KEY (`server_id`) REFERENCES `dc_hosting_servers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Cuentas de email en servidores de hosting';

-- Tabla: dc_hosting_ftp_accounts
CREATE TABLE IF NOT EXISTS `dc_hosting_ftp_accounts` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `server_id` INT NOT NULL,
  `username` VARCHAR(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `password` VARCHAR(500) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Cifrado con la clave de la aplicación.',
  `notes` TEXT COLLATE utf8mb4_spanish_ci,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_server_id` (`server_id`),
  CONSTRAINT `fk_hosting_ftp_server` FOREIGN KEY (`server_id`) REFERENCES `dc_hosting_servers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Cuentas FTP';

-- ============================================================================
-- PASO 3: CREAR/RECREAR TRIGGERS
-- ============================================================================

DROP TRIGGER IF EXISTS `trg_servers_audit_insert`;
DROP TRIGGER IF EXISTS `trg_servers_audit_update`;
DROP TRIGGER IF EXISTS `trg_servers_audit_delete`;

DELIMITER $$

CREATE TRIGGER `trg_servers_audit_insert` 
AFTER INSERT ON `dc_servers` FOR EACH ROW 
BEGIN
    INSERT INTO `dc_access_log` (`action`, `entity_type`, `entity_id`, `details`)
    VALUES ('create', 'server', NEW.id, JSON_OBJECT('label', NEW.label, 'type', NEW.type));
END$$

CREATE TRIGGER `trg_servers_audit_update` 
AFTER UPDATE ON `dc_servers` FOR EACH ROW 
BEGIN
    INSERT INTO `dc_access_log` (`action`, `entity_type`, `entity_id`, `details`)
    VALUES ('edit', 'server', NEW.id, JSON_OBJECT('label', NEW.label, 'old_label', OLD.label));
END$$

CREATE TRIGGER `trg_servers_audit_delete` 
BEFORE DELETE ON `dc_servers` FOR EACH ROW 
BEGIN
    INSERT INTO `dc_access_log` (`action`, `entity_type`, `entity_id`, `details`)
    VALUES ('delete', 'server', OLD.id, JSON_OBJECT('label', OLD.label, 'type', OLD.type));
END$$

DELIMITER ;

-- ============================================================================
-- PASO 4: CREAR/RECREAR STORED PROCEDURES
-- ============================================================================

DROP PROCEDURE IF EXISTS `sp_get_server_full`;
DROP PROCEDURE IF EXISTS `sp_get_stats`;
DROP PROCEDURE IF EXISTS `sp_search_infrastructure`;

DELIMITER $$

CREATE PROCEDURE `sp_get_server_full` (IN `p_server_id` VARCHAR(100))
BEGIN
    SELECT * FROM vw_datacenter_full 
    WHERE server_id = p_server_id;
END$$

CREATE PROCEDURE `sp_get_stats` ()
BEGIN
    SELECT 
        -- Contadores para el dashboard
        (SELECT COUNT(*) FROM dc_servers) as total_servers,
        (SELECT COUNT(*) FROM dc_servers WHERE status = 'active') as active_servers,
        (SELECT COUNT(*) FROM dc_services) as total_services,
        (SELECT COUNT(*) FROM dc_credentials) as total_credentials,
        (SELECT COUNT(*) FROM dc_locations) as total_locations,
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM dc_hosting_servers) as hosting_servers,
        (SELECT COUNT(*) FROM dc_hosting_emails) as email_accounts,
        (SELECT COUNT(*) FROM dc_servers WHERE status = 'maintenance') as servers_maintenance,
        (SELECT COUNT(*) FROM dc_hosting_accounts) as hosting_accounts,
        (SELECT COUNT(*) FROM dc_hosting_ftp_accounts) as ftp_accounts,
        (SELECT COUNT(*) FROM dc_servers WHERE status = 'inactive') as servers_inactive;
END$$

CREATE PROCEDURE `sp_search_infrastructure` (IN `search_term` VARCHAR(255))
BEGIN
    SELECT DISTINCT
        s.id AS server_id,
        s.server_id AS server_code,
        s.label AS server_name,
        s.type,
        s.status,
        s.net_ip_lan,
        s.net_ip_wan,
        s.net_host_external,
        sv.name AS service_name,
        sv.protocol,
        sv.port,
        'server' AS match_type
    FROM dc_servers s
    LEFT JOIN dc_services sv ON s.id = sv.server_id
    WHERE 
        s.label LIKE CONCAT('%', search_term, '%')
        OR s.server_id LIKE CONCAT('%', search_term, '%')
        OR s.net_ip_lan LIKE CONCAT('%', search_term, '%')
        OR s.net_ip_wan LIKE CONCAT('%', search_term, '%')
        OR s.net_host_external LIKE CONCAT('%', search_term, '%')
        OR sv.name LIKE CONCAT('%', search_term, '%')
        OR s.notes LIKE CONCAT('%', search_term, '%')
        OR sv.notes LIKE CONCAT('%', search_term, '%')
    ORDER BY s.label, sv.name;
END$$

DELIMITER ;

-- ============================================================================
-- PASO 5: CREAR/RECREAR VISTA
-- ============================================================================

DROP VIEW IF EXISTS `vw_datacenter_full`;

CREATE VIEW `vw_datacenter_full` AS 
SELECT 
    s.id AS server_pk,
    s.server_id AS server_id,
    s.label AS server_name,
    s.type AS server_type,
    s.status AS status,
    l.name AS location_name,
    s.hw_model AS hw_model,
    s.hw_cpu AS hw_cpu,
    s.hw_ram AS hw_ram,
    s.hw_disk AS hw_disk,
    s.net_ip_lan AS net_ip_lan,
    s.net_ip_wan AS net_ip_wan,
    s.net_host_external AS net_host_external,
    sv.id AS service_pk,
    sv.service_id AS service_id,
    sv.name AS service_name,
    sv.url_internal AS url_internal,
    sv.url_external AS url_external,
    sv.port AS port,
    sv.protocol AS protocol,
    c.id AS credential_pk,
    c.credential_id AS credential_id,
    c.username AS username,
    c.role AS cred_role,
    DATE_FORMAT(s.created_at, '%d/%m/%Y %H:%i') AS server_created_formatted,
    DATE_FORMAT(s.updated_at, '%d/%m/%Y %H:%i') AS server_updated_formatted
FROM dc_servers s
LEFT JOIN dc_locations l ON s.location_id = l.id
LEFT JOIN dc_services sv ON s.id = sv.server_id
LEFT JOIN dc_credentials c ON sv.id = c.service_id
ORDER BY s.label ASC, sv.name ASC, c.role DESC;

-- ============================================================================
-- PASO 6: INSERTAR DATOS INICIALES (SOLO SI NO EXISTEN)
-- ============================================================================

-- Usuario admin por defecto (solo si no existe ningún usuario)
INSERT INTO `users` (`username`, `email`, `pass_hash`, `role`)
SELECT 'admin', 'admin@secmti.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `username` = 'admin');

-- Ubicación por defecto
INSERT INTO `dc_locations` (`id`, `name`, `address`, `notes`)
SELECT 1, 'Datacenter Principal', 'Ubicación por defecto', NULL
WHERE NOT EXISTS (SELECT 1 FROM `dc_locations` WHERE `id` = 1);

-- ============================================================================
-- PASO 7: VERIFICACIÓN Y REPORTE
-- ============================================================================

SELECT 
    'INSTALACIÓN COMPLETADA' as status,
    DATABASE() as database_name,
    (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()) as total_tables,
    (SELECT COUNT(*) FROM information_schema.views WHERE table_schema = DATABASE()) as total_views,
    (SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema = DATABASE()) as total_procedures,
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM dc_servers) as total_servers,
    (SELECT COUNT(*) FROM dc_services) as total_services,
    (SELECT COUNT(*) FROM dc_credentials) as total_credentials,
    NOW() as installation_time;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- FIN DEL INSTALADOR
-- ============================================================================
-- Para ejecutar: Importar este archivo en phpMyAdmin
-- El script puede ejecutarse múltiples veces sin perder datos
-- ============================================================================
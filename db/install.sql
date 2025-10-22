-- =====================================================
-- Portal SECMTI - Script de Instalación de Base de Datos
-- =====================================================
-- Versión: 1.2
-- Fecha: 2025-10-22
-- Descripción: Estructura completa de la base de datos para Portal SECMTI
--              Sistema de gestión de infraestructura TI, servicios y hosting
-- Autor: Sergio Cabrera Miers (sergiomiers@gmail.com)
-- Zona Horaria: Argentina (UTC-3)
-- Charset: utf8mb4 con collation spanish_ci
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "-03:00"; -- Zona horaria de Argentina (UTC-3)

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- =====================================================
-- SECCIÓN 1: TABLAS DE AUDITORÍA Y LOGS
-- =====================================================

-- --------------------------------------------------------
-- Tabla: dc_access_log
-- Descripción: Registra todas las acciones realizadas en el sistema
--              para auditoría y trazabilidad (quién, qué, cuándo, dónde)
-- Uso: Monitoreo de seguridad y análisis de actividad
-- --------------------------------------------------------

CREATE TABLE `dc_access_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'Usuario que realizó la acción',
  `action` varchar(50) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Tipo de acción: view, edit, create, delete, copy_password',
  `entity_type` varchar(50) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Tipo de entidad afectada: server, service, credential',
  `entity_id` int(11) NOT NULL COMMENT 'ID de la entidad afectada',
  `ip_address` varchar(45) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Dirección IP del usuario',
  `user_agent` varchar(500) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Navegador y sistema operativo',
  `details` text COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Detalles adicionales en formato JSON',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'Fecha y hora del evento'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Auditoría de accesos y acciones del sistema';

-- =====================================================
-- SECCIÓN 2: TABLAS DE DATACENTER (SERVIDORES)
-- =====================================================

-- --------------------------------------------------------
-- Tabla: dc_locations
-- Descripción: Ubicaciones físicas donde se encuentran los servidores
--              (oficinas, datacenters, sucursales, etc.)
-- Uso: Organización geográfica de la infraestructura
-- --------------------------------------------------------

CREATE TABLE `dc_locations` (
  `id` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Nombre de la ubicación (ej: Datacenter Central)',
  `address` varchar(500) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Dirección física completa',
  `notes` text COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Notas adicionales sobre la ubicación'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Ubicaciones físicas de servidores y equipos';

-- --------------------------------------------------------
-- Tabla: dc_servers
-- Descripción: Inventario completo de servidores (físicos, virtuales, cloud)
--              con información de hardware y red
-- Uso: Gestión centralizada de toda la infraestructura de servidores
-- --------------------------------------------------------

CREATE TABLE `dc_servers` (
  `id` int(11) NOT NULL,
  `server_id` varchar(100) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Identificador único del servidor (ej: SRV-WEB-01)',
  `label` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Nombre descriptivo del servidor',
  `type` enum('physical','virtual','container','cloud','isp','Router','Switch','Dvr','Alarmas','Antena') COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'physical' COMMENT 'Tipo de servidor o dispositivo',
  `location_id` int(11) DEFAULT NULL COMMENT 'Ubicación física del servidor',
  `status` enum('active','inactive','maintenance') COLLATE utf8mb4_spanish_ci DEFAULT 'active' COMMENT 'Estado operativo del servidor',
  `hw_model` varchar(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Modelo de hardware (ej: Dell PowerEdge R740)',
  `hw_cpu` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Procesador (ej: Intel Xeon E5-2680 v4)',
  `hw_ram` varchar(50) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Memoria RAM (ej: 64GB DDR4)',
  `hw_disk` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Almacenamiento (ej: 2x 1TB SSD RAID1)',
  `net_ip_lan` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'IP en red local (ej: 192.168.1.10)',
  `net_ip_wan` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'IP pública (ej: 200.45.123.45)',
  `net_host_external` varchar(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Hostname o dominio externo (ej: server.empresa.com)',
  `net_gateway` varchar(50) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Gateway de red',
  `net_dns` text COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Servidores DNS en formato JSON',
  `notes` text COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Notas y observaciones',
  `username` varchar(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Usuario principal del servidor',
  `password` varchar(500) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Contraseña cifrada con AES-256-CBC',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'Fecha de creación del registro',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Fecha de última actualización',
  `created_by` int(11) DEFAULT NULL COMMENT 'Usuario que creó el registro'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Servidores físicos, virtuales y dispositivos de red';

-- --------------------------------------------------------
-- Tabla: dc_services
-- Descripción: Servicios instalados en cada servidor (web, SSH, DB, etc.)
--              con URLs de acceso y credenciales asociadas
-- Uso: Inventario de servicios y puntos de acceso por servidor
-- --------------------------------------------------------

CREATE TABLE `dc_services` (
  `id` int(11) NOT NULL,
  `server_id` int(11) NOT NULL COMMENT 'Servidor donde está instalado el servicio',
  `service_id` varchar(100) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Identificador único del servicio',
  `name` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Nombre del servicio (ej: Panel Proxmox, MySQL)',
  `url_internal` varchar(500) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'URL de acceso interno (LAN)',
  `url_external` varchar(500) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'URL de acceso externo (WAN)',
  `port` varchar(10) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Puerto del servicio',
  `protocol` varchar(20) COLLATE utf8mb4_spanish_ci DEFAULT 'https' COMMENT 'Protocolo: http, https, ssh, rdp, etc.',
  `notes` text COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Notas sobre el servicio',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Servicios instalados en servidores';

-- --------------------------------------------------------
-- Tabla: dc_credentials
-- Descripción: Credenciales de acceso a los servicios
--              Contraseñas encriptadas con AES-256-CBC
-- Uso: Almacenamiento seguro de usuarios y contraseñas por servicio
-- --------------------------------------------------------

CREATE TABLE `dc_credentials` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL COMMENT 'Servicio al que pertenece la credencial',
  `credential_id` varchar(100) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Identificador de la credencial',
  `username` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Usuario de acceso',
  `password` varchar(500) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Contraseña cifrada con AES-256-CBC',
  `role` varchar(50) COLLATE utf8mb4_spanish_ci DEFAULT 'user' COMMENT 'Rol: admin, user, root, etc.',
  `notes` text COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Notas sobre esta credencial',
  `last_password_change` timestamp NULL DEFAULT NULL COMMENT 'Última vez que se cambió la contraseña',
  `password_expires_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha de expiración de la contraseña',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Credenciales cifradas de acceso a servicios';

-- =====================================================
-- SECCIÓN 3: TABLAS DE HOSTING (cPanel/WHM)
-- =====================================================

-- --------------------------------------------------------
-- Tabla: dc_hosting_servers
-- Descripción: Servidores de hosting con cPanel/WHM
-- Uso: Gestión de servidores de hosting compartido
-- --------------------------------------------------------

CREATE TABLE `dc_hosting_servers` (
  `id` int(11) NOT NULL,
  `hostname` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Hostname del servidor (ej: cpanel.hosting.com)',
  `label` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Etiqueta descriptiva del servidor',
  `webmail_port` int(11) DEFAULT 2096 COMMENT 'Puerto de acceso a Webmail (por defecto 2096)',
  `cpanel_port` int(11) DEFAULT 2083 COMMENT 'Puerto de acceso a cPanel (por defecto 2083)',
  `notes` text COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Notas sobre el servidor',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Servidores de hosting (cPanel/WHM)';

-- --------------------------------------------------------
-- Tabla: dc_hosting_accounts
-- Descripción: Cuentas de cPanel en servidores de hosting
-- Uso: Gestión de cuentas de clientes con panel cPanel
-- --------------------------------------------------------

CREATE TABLE `dc_hosting_accounts` (
  `id` int(11) NOT NULL,
  `server_id` int(11) NOT NULL COMMENT 'Servidor de hosting donde está la cuenta',
  `username` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Usuario de cPanel',
  `password` varchar(500) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Contraseña cifrada',
  `domain` varchar(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Dominio principal de la cuenta',
  `label` varchar(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Etiqueta descriptiva',
  `notes` text COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Notas sobre la cuenta',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Cuentas de cPanel en servidores de hosting';

-- --------------------------------------------------------
-- Tabla: dc_hosting_emails
-- Descripción: Cuentas de correo electrónico en servidores de hosting
-- Uso: Gestión de casillas de email por servidor
-- --------------------------------------------------------

CREATE TABLE `dc_hosting_emails` (
  `id` int(11) NOT NULL,
  `server_id` int(11) NOT NULL COMMENT 'Servidor de hosting',
  `email_address` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Dirección de correo (ej: info@empresa.com)',
  `password` varchar(500) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Contraseña cifrada',
  `notes` text COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Notas sobre la cuenta de email',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Cuentas de email en servidores de hosting';

-- --------------------------------------------------------
-- Tabla: dc_hosting_ftp_accounts
-- Descripción: Cuentas FTP para transferencia de archivos
-- Uso: Gestión de usuarios FTP por servidor de hosting
-- --------------------------------------------------------

CREATE TABLE `dc_hosting_ftp_accounts` (
  `id` int(11) NOT NULL,
  `server_id` int(11) NOT NULL COMMENT 'Servidor de hosting',
  `username` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Usuario FTP',
  `password` varchar(500) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Contraseña cifrada',
  `notes` text COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Notas sobre la cuenta FTP',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Cuentas FTP en servidores de hosting';

-- --------------------------------------------------------
-- Tabla: dc_hosting_terminal_server_accounts
-- Descripción: Cuentas de acceso RDP/Terminal Server
-- Uso: Gestión de credenciales para acceso remoto a servidores Windows
-- --------------------------------------------------------

CREATE TABLE `dc_hosting_terminal_server_accounts` (
  `id` int(11) NOT NULL,
  `server_id` int(11) NOT NULL COMMENT 'Servidor de hosting asociado',
  `host` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Hostname o IP del Terminal Server',
  `port` int(11) NOT NULL DEFAULT 3389 COMMENT 'Puerto RDP (por defecto 3389)',
  `username` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Usuario de acceso',
  `password` text COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Contraseña cifrada',
  `notes` text COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Notas sobre el acceso',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Cuentas de Terminal Server/RDP para acceso remoto';

-- =====================================================
-- SECCIÓN 4: TABLAS DE EQUIPAMIENTO (PARQUE INFORMÁTICO)
-- =====================================================

-- --------------------------------------------------------
-- Tabla: pc_equipment
-- Descripción: Inventario de equipos informáticos (PCs, laptops, etc.)
--              distribuidos en diferentes ubicaciones
-- Uso: Control de hardware asignado a usuarios por departamento
-- --------------------------------------------------------

CREATE TABLE `pc_equipment` (
  `id` int(11) NOT NULL,
  `asset_tag` varchar(50) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Etiqueta de activo/inventario',
  `location_id` int(11) DEFAULT NULL COMMENT 'Ubicación física del equipo',
  `assigned_to` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Usuario al que está asignado',
  `pc_model` varchar(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Modelo del equipo (ej: HP EliteBook 840)',
  `department` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Departamento o sector',
  `status` enum('Nueva','Usada','Reacondicionada','En Depósito','De Baja') COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'Usada' COMMENT 'Estado del equipo',
  `os` varchar(50) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Sistema operativo instalado',
  `office_suite` varchar(50) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Suite ofimática instalada',
  `phone` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Teléfono asignado',
  `printer` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Impresora asignada',
  `notes` text COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Notas adicionales',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Inventario de equipos informáticos (parque de PCs)';

-- =====================================================
-- SECCIÓN 5: TABLAS DEL PORTAL (SERVICIOS WEB)
-- =====================================================

-- --------------------------------------------------------
-- Tabla: service_categories
-- Descripción: Categorías para organizar los servicios en el portal
-- Uso: Agrupación visual de servicios (LAN, WAN, Sucursales, etc.)
-- --------------------------------------------------------

CREATE TABLE `service_categories` (
  `id` int(11) NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Identificador único (ej: accesos-lan)',
  `name` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Nombre visible (ej: Accesos LAN)',
  `sort_order` int(11) DEFAULT 0 COMMENT 'Orden de aparición en el dashboard',
  `icon` varchar(50) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Emoji o clase de icono',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '1 = visible, 0 = oculto'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Categorías para organizar servicios del portal';

-- --------------------------------------------------------
-- Tabla: services
-- Descripción: Servicios/botones que aparecen en el portal principal
--              Cada servicio tiene URL, categoría y permisos de acceso
-- Uso: Configuración dinámica del dashboard del portal
-- --------------------------------------------------------

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `service_key` varchar(100) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Clave única del servicio (ej: lan, datacenter)',
  `label` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Texto del botón mostrado al usuario',
  `url` varchar(500) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'URL del servicio',
  `category` varchar(100) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Categoría del servicio (LAN, WAN, etc.)',
  `requires_login` tinyint(1) DEFAULT 1 COMMENT '1 = requiere login, 0 = acceso público',
  `requires_role` enum('user','admin') COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Rol requerido para acceder',
  `redirect` tinyint(1) DEFAULT 0 COMMENT '0 = nueva ventana, 1 = misma ventana',
  `sort_order` int(11) DEFAULT 0 COMMENT 'Orden dentro de la categoría',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '1 = visible, 0 = oculto',
  `icon` varchar(50) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Emoji o icono del servicio',
  `description` text COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Descripción del servicio',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Servicios/botones del portal principal';

-- =====================================================
-- SECCIÓN 6: TABLAS DE USUARIOS Y PERMISOS
-- =====================================================

-- --------------------------------------------------------
-- Tabla: users
-- Descripción: Usuarios del sistema con autenticación y roles
-- Uso: Control de acceso al portal (admin/user)
-- --------------------------------------------------------

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Nombre de usuario (login)',
  `pass_hash` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL COMMENT 'Hash de contraseña con password_hash()',
  `role` enum('admin','user') COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'user' COMMENT 'Rol del usuario',
  `full_name` varchar(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Nombre completo',
  `email` varchar(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Email de contacto',
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0 COMMENT 'Intentos fallidos de login (anti fuerza bruta)',
  `lockout_until` datetime DEFAULT NULL COMMENT 'Bloqueado hasta esta fecha/hora',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL COMMENT 'Última vez que inició sesión'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Usuarios del sistema con roles y autenticación';

-- --------------------------------------------------------
-- Tabla: user_locations
-- Descripción: Relación muchos a muchos entre usuarios y ubicaciones
-- Uso: Control de acceso por ubicación (usuarios ven solo sus ubicaciones)
-- --------------------------------------------------------

CREATE TABLE `user_locations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Usuario',
  `location_id` int(11) NOT NULL COMMENT 'Ubicación a la que tiene acceso',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Relación usuarios-ubicaciones para control de acceso';

-- =====================================================
-- SECCIÓN 7: VISTAS SQL
-- =====================================================

-- --------------------------------------------------------
-- Vista: vw_datacenter_full
-- Descripción: Vista completa que une servidores, ubicaciones,
--              servicios y credenciales en una sola consulta
-- Uso: Consultas optimizadas para el dashboard de datacenter
-- --------------------------------------------------------

CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`sistemaspvyt`@`%` SQL SECURITY DEFINER VIEW `vw_datacenter_full` AS
SELECT
  `s`.`id` AS `server_pk`,
  `s`.`server_id` AS `server_id`,
  `s`.`label` AS `server_name`,
  `s`.`type` AS `server_type`,
  `s`.`status` AS `status`,
  `l`.`name` AS `location_name`,
  `s`.`hw_model` AS `hw_model`,
  `s`.`hw_cpu` AS `hw_cpu`,
  `s`.`hw_ram` AS `hw_ram`,
  `s`.`hw_disk` AS `hw_disk`,
  `s`.`net_ip_lan` AS `net_ip_lan`,
  `s`.`net_ip_wan` AS `net_ip_wan`,
  `s`.`net_host_external` AS `net_host_external`,
  `sv`.`id` AS `service_pk`,
  `sv`.`service_id` AS `service_id`,
  `sv`.`name` AS `service_name`,
  `sv`.`url_internal` AS `url_internal`,
  `sv`.`url_external` AS `url_external`,
  `sv`.`port` AS `port`,
  `sv`.`protocol` AS `protocol`,
  `c`.`id` AS `credential_pk`,
  `c`.`credential_id` AS `credential_id`,
  `c`.`username` AS `username`,
  `c`.`role` AS `cred_role`,
  date_format(`s`.`created_at`,'%d/%m/%Y %H:%i') AS `server_created_formatted`,
  date_format(`s`.`updated_at`,'%d/%m/%Y %H:%i') AS `server_updated_formatted`
FROM (((`dc_servers` `s`
  LEFT JOIN `dc_locations` `l` ON `s`.`location_id` = `l`.`id`)
  LEFT JOIN `dc_services` `sv` ON `s`.`id` = `sv`.`server_id`)
  LEFT JOIN `dc_credentials` `c` ON `sv`.`id` = `c`.`service_id`)
ORDER BY `s`.`label` ASC, `sv`.`name` ASC, `c`.`role` DESC;

-- =====================================================
-- SECCIÓN 8: ÍNDICES Y CLAVES PRIMARIAS
-- =====================================================

-- Índices para dc_access_log (optimizar consultas de auditoría)
ALTER TABLE `dc_access_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_ip` (`ip_address`);

-- Índices para dc_credentials (búsqueda rápida por servicio y usuario)
ALTER TABLE `dc_credentials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_credential` (`service_id`,`credential_id`),
  ADD KEY `idx_service_id` (`service_id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role` (`role`);

-- Índices para tablas de hosting
ALTER TABLE `dc_hosting_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_server_id` (`server_id`);

ALTER TABLE `dc_hosting_emails`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_server_id` (`server_id`);

ALTER TABLE `dc_hosting_ftp_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_server_id` (`server_id`);

ALTER TABLE `dc_hosting_servers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hostname` (`hostname`);

ALTER TABLE `dc_hosting_terminal_server_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `server_id` (`server_id`);

-- Índices para ubicaciones (nombre único)
ALTER TABLE `dc_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

-- Índices para servidores (búsqueda optimizada por tipo, estado, etc.)
ALTER TABLE `dc_servers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `server_id` (`server_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_label` (`label`),
  ADD KEY `fk_created_by` (`created_by`),
  ADD KEY `fk_location_id` (`location_id`);

-- Índices para servicios
ALTER TABLE `dc_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_service` (`server_id`,`service_id`),
  ADD KEY `idx_server_id` (`server_id`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_protocol` (`protocol`);

-- Índices para equipos de cómputo
ALTER TABLE `pc_equipment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `asset_tag` (`asset_tag`),
  ADD KEY `location_id` (`location_id`);

-- Índices para servicios del portal
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `service_key` (`service_key`),
  ADD KEY `category` (`category`),
  ADD KEY `is_active` (`is_active`),
  ADD KEY `sort_order` (`sort_order`),
  ADD KEY `idx_category_active` (`category`,`is_active`,`sort_order`),
  ADD KEY `idx_role_active` (`requires_role`,`is_active`);

ALTER TABLE `service_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

-- Índices para usuarios
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_role` (`role`);

ALTER TABLE `user_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_location_unique` (`user_id`,`location_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `location_id` (`location_id`);

-- =====================================================
-- SECCIÓN 9: AUTO_INCREMENT
-- =====================================================

ALTER TABLE `dc_access_log` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_credentials` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_hosting_accounts` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_hosting_emails` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_hosting_ftp_accounts` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_hosting_servers` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_hosting_terminal_server_accounts` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_locations` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_servers` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_services` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `pc_equipment` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `services` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `service_categories` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `users` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `user_locations` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- =====================================================
-- SECCIÓN 10: RESTRICCIONES DE CLAVE FORÁNEA (FOREIGN KEYS)
-- =====================================================

-- Credenciales dependen de servicios (si se borra servicio, se borran credenciales)
ALTER TABLE `dc_credentials`
  ADD CONSTRAINT `fk_credentials_service` FOREIGN KEY (`service_id`)
  REFERENCES `dc_services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Cuentas de hosting dependen de servidores
ALTER TABLE `dc_hosting_accounts`
  ADD CONSTRAINT `fk_hosting_accounts_server` FOREIGN KEY (`server_id`)
  REFERENCES `dc_hosting_servers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `dc_hosting_emails`
  ADD CONSTRAINT `fk_hosting_emails_server` FOREIGN KEY (`server_id`)
  REFERENCES `dc_hosting_servers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `dc_hosting_ftp_accounts`
  ADD CONSTRAINT `fk_hosting_ftp_server` FOREIGN KEY (`server_id`)
  REFERENCES `dc_hosting_servers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `dc_hosting_terminal_server_accounts`
  ADD CONSTRAINT `dc_hosting_terminal_server_accounts_ibfk_1` FOREIGN KEY (`server_id`)
  REFERENCES `dc_hosting_servers` (`id`) ON DELETE CASCADE;

-- Servidores tienen ubicación (si se borra ubicación, servidor queda sin ubicación)
ALTER TABLE `dc_servers`
  ADD CONSTRAINT `fk_servers_location` FOREIGN KEY (`location_id`)
  REFERENCES `dc_locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Servicios dependen de servidores
ALTER TABLE `dc_services`
  ADD CONSTRAINT `fk_services_server` FOREIGN KEY (`server_id`)
  REFERENCES `dc_servers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Equipos tienen ubicación
ALTER TABLE `pc_equipment`
  ADD CONSTRAINT `pc_equipment_ibfk_1` FOREIGN KEY (`location_id`)
  REFERENCES `dc_locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Relación usuarios-ubicaciones
ALTER TABLE `user_locations`
  ADD CONSTRAINT `user_locations_ibfk_1` FOREIGN KEY (`user_id`)
  REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_locations_ibfk_2` FOREIGN KEY (`location_id`)
  REFERENCES `dc_locations` (`id`) ON DELETE CASCADE;

-- =====================================================
-- FIN DEL SCRIPT DE INSTALACIÓN
-- =====================================================

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

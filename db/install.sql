-- ============================================================================
-- Script de Instalación del Portal SECMTI
-- Versión: 1.2
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "-03:00";

---
---
CREATE DATABASE IF NOT EXISTS `portal_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;
USE `portal_db`;

CREATE TABLE `dc_access_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `details` text COLLATE utf8mb4_spanish_ci DEFAULT NULL COMMENT 'Detalles de los cambios realizados',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

CREATE TABLE `dc_credentials` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `credential_id` varchar(50) COLLATE utf8mb4_spanish_ci NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `password` text COLLATE utf8mb4_spanish_ci NOT NULL,
  `role` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT 'user',
  `notes` text COLLATE utf8mb4_spanish_ci,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

CREATE TABLE `dc_hosting_accounts` (
  `id` int(11) NOT NULL,
  `server_id` int(11) NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `password` text COLLATE utf8mb4_spanish_ci NOT NULL,
  `domain` varchar(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `label` varchar(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_spanish_ci,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

CREATE TABLE `dc_hosting_emails` (
  `id` int(11) NOT NULL,
  `server_id` int(11) NOT NULL,
  `email_address` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `password` text COLLATE utf8mb4_spanish_ci NOT NULL,
  `notes` text COLLATE utf8mb4_spanish_ci,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

CREATE TABLE `dc_hosting_ftp_accounts` (
  `id` int(11) NOT NULL,
  `server_id` int(11) NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `password` text COLLATE utf8mb4_spanish_ci NOT NULL,
  `notes` text COLLATE utf8mb4_spanish_ci,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

CREATE TABLE `dc_hosting_servers` (
  `id` int(11) NOT NULL,
  `hostname` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `webmail_port` int(11) DEFAULT 2096,
  `cpanel_port` int(11) DEFAULT 2083,
  `notes` text COLLATE utf8mb4_spanish_ci,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

CREATE TABLE `dc_hosting_terminal_server_accounts` (
  `id` int(11) NOT NULL,
  `server_id` int(11) NOT NULL,
  `host` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `port` int(11) NOT NULL DEFAULT 3389,
  `username` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `password` text COLLATE utf8mb4_spanish_ci NOT NULL,
  `notes` text COLLATE utf8mb4_spanish_ci,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

CREATE TABLE `dc_locations` (
  `id` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `address` text COLLATE utf8mb4_spanish_ci,
  `notes` text COLLATE utf8mb4_spanish_ci,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `dc_locations` (`id`, `name`, `address`, `notes`) VALUES
(1, 'Datacenter Central', 'Ubicación Principal', 'Equipos centrales de la empresa.'),
(2, 'Sucursal Norte', 'Dirección Sucursal Norte', 'Equipamiento de la sucursal norte.'),
(3, 'Sucursal Sur', 'Dirección Sucursal Sur', 'Equipamiento de la sucursal sur.');

-- --------------------------------------------------------

CREATE TABLE `dc_servers` (
  `id` int(11) NOT NULL,
  `server_id` varchar(50) COLLATE utf8mb4_spanish_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `type` enum('physical','virtual','container','cloud','isp','Router','Switch','Dvr','Alarmas') COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'physical',
  `location_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive','maintenance') COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'active',
  `hw_model` varchar(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `hw_cpu` varchar(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `hw_ram` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `hw_disk` varchar(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `net_ip_lan` varchar(45) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `net_ip_wan` varchar(45) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `net_host_external` varchar(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `net_gateway` varchar(45) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `net_dns` text COLLATE utf8mb4_spanish_ci,
  `notes` text COLLATE utf8mb4_spanish_ci,
  `username` varchar(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `password` text COLLATE utf8mb4_spanish_ci,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

CREATE TABLE `dc_services` (
  `id` int(11) NOT NULL,
  `server_id` int(11) NOT NULL,
  `service_id` varchar(50) COLLATE utf8mb4_spanish_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `url_internal` varchar(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `url_external` varchar(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `port` varchar(50) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `protocol` varchar(50) COLLATE utf8mb4_spanish_ci DEFAULT 'https',
  `notes` text COLLATE utf8mb4_spanish_ci,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

CREATE TABLE `pc_equipment` (
  `id` int(11) NOT NULL,
  `asset_tag` varchar(50) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `assigned_to` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `pc_model` varchar(255) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `department` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `status` enum('Nueva','Usada','Reacondicionada','En Depósito','De Baja') COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'Usada',
  `os` varchar(50) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `office_suite` varchar(50) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `phone` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `printer` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_spanish_ci,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_spanish_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `pass_hash` varchar(255) COLLATE utf8mb4_spanish_ci NOT NULL,
  `role` enum('user','admin') COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'user',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `users` (`id`, `username`, `full_name`, `email`, `pass_hash`, `role`, `last_login`) VALUES
(1, 'admin', 'Administrador del Sistema', 'admin@example.com', '$2y$10$E/g0j4g.ABd.G.xL5xL4d.x.Z.x.Z.x.Z.x.Z.x.Z.x.Z.x.Z.x.Z', 'admin', NULL);

-- --------------------------------------------------------

--
-- Índices
--

ALTER TABLE `dc_access_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `dc_credentials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `credential_id` (`credential_id`),
  ADD KEY `service_id` (`service_id`);

ALTER TABLE `dc_hosting_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `server_id` (`server_id`);

ALTER TABLE `dc_hosting_emails`
  ADD PRIMARY KEY (`id`),
  ADD KEY `server_id` (`server_id`);

ALTER TABLE `dc_hosting_ftp_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `server_id` (`server_id`);

ALTER TABLE `dc_hosting_servers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hostname` (`hostname`);

ALTER TABLE `dc_hosting_terminal_server_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `server_id` (`server_id`);

ALTER TABLE `dc_locations`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `dc_servers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `server_id` (`server_id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `created_by` (`created_by`);

ALTER TABLE `dc_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `service_id` (`service_id`),
  ADD KEY `server_id` (`server_id`);

ALTER TABLE `pc_equipment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `asset_tag` (`asset_tag`),
  ADD KEY `location_id` (`location_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

-- --------------------------------------------------------

--
-- AUTO_INCREMENT
--

ALTER TABLE `dc_access_log` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_credentials` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_hosting_accounts` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_hosting_emails` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_hosting_ftp_accounts` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_hosting_servers` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_hosting_terminal_server_accounts` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_locations` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `dc_servers` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_services` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `pc_equipment` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `users` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

-- --------------------------------------------------------

--
-- Restricciones (Foreign Keys)
--

ALTER TABLE `dc_access_log`
  ADD CONSTRAINT `dc_access_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `dc_credentials`
  ADD CONSTRAINT `dc_credentials_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `dc_services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `dc_hosting_accounts`
  ADD CONSTRAINT `dc_hosting_accounts_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `dc_hosting_servers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `dc_hosting_emails`
  ADD CONSTRAINT `dc_hosting_emails_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `dc_hosting_servers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `dc_hosting_ftp_accounts`
  ADD CONSTRAINT `dc_hosting_ftp_accounts_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `dc_hosting_servers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `dc_hosting_terminal_server_accounts`
  ADD CONSTRAINT `dc_hosting_terminal_server_accounts_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `dc_hosting_servers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `dc_servers`
  ADD CONSTRAINT `dc_servers_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `dc_locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `dc_servers_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `dc_services`
  ADD CONSTRAINT `dc_services_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `dc_servers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `pc_equipment`
  ADD CONSTRAINT `pc_equipment_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `dc_locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

COMMIT;


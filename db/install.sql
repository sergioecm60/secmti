-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 24-07-2024 a las 04:00:01
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `portal_db`
--
CREATE DATABASE IF NOT EXISTS `portal_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;
USE `portal_db`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dc_access_log`
--

CREATE TABLE `dc_access_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dc_credentials`
--

CREATE TABLE `dc_credentials` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `credential_id` varchar(50) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` text NOT NULL,
  `role` varchar(100) DEFAULT 'user',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dc_hosting_accounts`
--

CREATE TABLE `dc_hosting_accounts` (
  `id` int(11) NOT NULL,
  `server_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` text NOT NULL,
  `domain` varchar(255) DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dc_hosting_emails`
--

CREATE TABLE `dc_hosting_emails` (
  `id` int(11) NOT NULL,
  `server_id` int(11) NOT NULL,
  `email_address` varchar(255) NOT NULL,
  `password` text NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dc_hosting_ftp_accounts`
--

CREATE TABLE `dc_hosting_ftp_accounts` (
  `id` int(11) NOT NULL,
  `server_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` text NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dc_hosting_servers`
--

CREATE TABLE `dc_hosting_servers` (
  `id` int(11) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `label` varchar(255) NOT NULL,
  `webmail_port` int(11) DEFAULT 2096,
  `cpanel_port` int(11) DEFAULT 2083,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dc_locations`
--

CREATE TABLE `dc_locations` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `dc_locations`
--

INSERT INTO `dc_locations` (`id`, `name`, `address`, `notes`) VALUES
(1, 'Datacenter Principal', 'Ubicación por defecto', 'Datacenter central de la empresa'),
(2, 'Hotel Lyon', 'Riobamba 251 CABA', 'Equipamiento en el Hotel Lyon'),
(3, 'Hotel Villa de Merlo', 'Av. del Sol 801, Merlo San Luis', 'Equipamiento Hotel Villa de Merlo'),
(4, 'Hotel San Miguel Plaza', 'Ruta Provincial 56 S/N, Córdoba', 'Equipamiento San Miguel Plaza Hotel'),
(16, 'La Margarita', 'Olavarria S/N, Buenos Aires', 'Equipamiento La Margarita'),
(17, 'Grand Hotel', 'Hipolito Irigoyen 250, Termas de Rio Hondo', 'Equipamiento Grand Hotel'),
(18, 'Hotel Kalken', 'Tte Valentin Feilberg 119, El Calafate', 'Equipamiento Hotel Kalken');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dc_servers`
--

CREATE TABLE `dc_servers` (
  `id` int(11) NOT NULL,
  `server_id` varchar(50) NOT NULL,
  `label` varchar(255) NOT NULL,
  `type` enum('physical','virtual','container','cloud','isp') NOT NULL DEFAULT 'physical',
  `location_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  `hw_model` varchar(255) DEFAULT NULL,
  `hw_cpu` varchar(255) DEFAULT NULL,
  `hw_ram` varchar(100) DEFAULT NULL,
  `hw_disk` varchar(255) DEFAULT NULL,
  `net_ip_lan` varchar(45) DEFAULT NULL,
  `net_ip_wan` varchar(45) DEFAULT NULL,
  `net_host_external` varchar(255) DEFAULT NULL,
  `net_gateway` varchar(45) DEFAULT NULL,
  `net_dns` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dc_services`
--

CREATE TABLE `dc_services` (
  `id` int(11) NOT NULL,
  `server_id` int(11) NOT NULL,
  `service_id` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `url_internal` varchar(255) DEFAULT NULL,
  `url_external` varchar(255) DEFAULT NULL,
  `port` varchar(50) DEFAULT NULL,
  `protocol` varchar(50) DEFAULT 'https',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pc_equipment`
--

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

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `pass_hash` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `email`, `pass_hash`, `role`, `last_login`) VALUES
(1, 'admin', 'Administrador del Sistema', 'admin@example.com', '$2y$10$E/g0j4g.ABd.G.xL5xL4d.x.Z.x.Z.x.Z.x.Z.x.Z.x.Z.x.Z.x.Z', 'admin', NULL); -- Contraseña: 12345678

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `dc_access_log`
--
ALTER TABLE `dc_access_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `dc_credentials`
--
ALTER TABLE `dc_credentials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `credential_id` (`credential_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indices de la tabla `dc_hosting_accounts`
--
ALTER TABLE `dc_hosting_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `server_id` (`server_id`);

--
-- Indices de la tabla `dc_hosting_emails`
--
ALTER TABLE `dc_hosting_emails`
  ADD PRIMARY KEY (`id`),
  ADD KEY `server_id` (`server_id`);

--
-- Indices de la tabla `dc_hosting_ftp_accounts`
--
ALTER TABLE `dc_hosting_ftp_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `server_id` (`server_id`);

--
-- Indices de la tabla `dc_hosting_servers`
--
ALTER TABLE `dc_hosting_servers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hostname` (`hostname`);

--
-- Indices de la tabla `dc_locations`
--
ALTER TABLE `dc_locations`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `dc_servers`
--
ALTER TABLE `dc_servers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `server_id` (`server_id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indices de la tabla `dc_services`
--
ALTER TABLE `dc_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `service_id` (`service_id`),
  ADD KEY `server_id` (`server_id`);

--
-- Indices de la tabla `pc_equipment`
--
ALTER TABLE `pc_equipment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `asset_tag` (`asset_tag`),
  ADD KEY `location_id` (`location_id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

ALTER TABLE `dc_access_log` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_credentials` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_hosting_accounts` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_hosting_emails` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_hosting_ftp_accounts` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_hosting_servers` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_locations` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;
ALTER TABLE `dc_servers` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `dc_services` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `pc_equipment` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `users` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
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

ALTER TABLE `dc_servers`
  ADD CONSTRAINT `dc_servers_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `dc_locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `dc_servers_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `dc_services`
  ADD CONSTRAINT `dc_services_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `dc_servers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `pc_equipment`
  ADD CONSTRAINT `pc_equipment_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `dc_locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
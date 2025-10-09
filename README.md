# Portal de Servicios SECMTI

**Creado por Sergio Cabrera**
<br>
📧 [sergiomiers@gmail.com](mailto:sergiomiers@gmail.com)
<<<<<<< HEAD
🔧 Asistencia técnica: IAS Gemini, ChatGPT y Qwen

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.0-8892BF.svg?style=for-the-badge)](https://php.net)
[![License](https://img.shields.io/badge/License-GPLv3-blue.svg?style=for-the-badge)](license.txt)
[![Version](https://img.shields.io/badge/Version-0.0.31-green.svg?style=for-the-badge)](https://github.com/sergioecm60/secmti/releases)
[![GitHub Repo](https://img.shields.io/badge/GitHub-Repository-181717?style=for-the-badge\&logo=github)](https://github.com/sergioecm60/secmti)
=======
<br>
🤖 Asistencia técnica: **Claude (Anthropic)**, Gemini (Google), ChatGPT (OpenAI) y Qwen (Alibaba)

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.0-8892BF.svg?style=for-the-badge)](https://php.net)
[![License](https://img.shields.io/badge/License-GPLv3-blue.svg?style=for-the-badge)](license.txt)
[![Version](https://img.shields.io/badge/Version-1.0.0-green.svg?style=for-the-badge)](https://github.com/sergioecm60/secmti/releases)
[![GitHub Repo](https://img.shields.io/badge/GitHub-Repository-181717?style=for-the-badge&logo=github)](https://github.com/sergioecm60/secmti)
[![Database](https://img.shields.io/badge/Database-MySQL%2FMariaDB-4479A1.svg?style=for-the-badge&logo=mysql)](https://www.mysql.com)
>>>>>>> a8418d4 (feat: Refactor project structure and add new features)

---

## 📄 Descripción

<<<<<<< HEAD
Un **portal de servicios simple, seguro y personalizable**, escrito en **PHP puro**, sin frameworks.
Diseñado para ser **ligero, seguro y fácil de instalar y administrar**, ideal para entornos corporativos o de administración interna.

Este portal permite centralizar el acceso a herramientas internas (como Proxmox, Webmin, mytop, info, etc.)
tras una capa de autenticación robusta, con monitoreo de seguridad y gestión web completa.
=======
Un **portal de servicios profesional y completo** para **gestión de infraestructura TI**, escrito en **PHP puro** sin frameworks. Diseñado para ser **ligero, seguro y fácil de administrar**, ideal para empresas que necesitan centralizar el acceso a servicios internos y gestionar su datacenter de forma eficiente.

Este portal permite:
- 🖥️ **Gestionar servidores físicos y virtuales** (Proxmox, VMs, containers, cloud)
- 🔑 **Administrar credenciales** de forma segura y centralizada
- 🌐 **Organizar servicios** por categorías (LAN, WAN, Sucursales)
- 📊 **Monitorear infraestructura** con dashboard de estadísticas en tiempo real
- 🏢 **Gestionar hosting** (cPanel, emails, FTP, dominios)
>>>>>>> a8418d4 (feat: Refactor project structure and add new features)

---

## 🚀 Versión Actual

**1.0.0** - Sistema completo de gestión de infraestructura con base de datos integrada

---

## ✨ Características Principales

### 🌐 Página de Aterrizaje (`index.php`)
- Página pública de presentación profesional
- Información de contacto, sucursales y redes sociales
- Diseño moderno con gradientes y animaciones
- Totalmente personalizable desde el panel de administración

### 🔐 Portal de Servicios (`index2.php`)
- **Dashboard de estadísticas** con métricas en tiempo real
- Acceso organizado por categorías (LAN, WAN, Sucursales)
- **Drag & Drop** para reorganizar servicios (solo admin)
- **Secciones colapsables** para mejor organización
- Vista de actividad reciente del sistema
- Interfaz moderna y responsive

### 🏢 Gestión de Infraestructura (`datacenter_view.php`)
- **Inventario completo** de servidores físicos y virtuales
- Información detallada de hardware (CPU, RAM, discos)
- **Gestión de red** (IPs LAN/WAN, hostnames, DNS, gateway)
- **Servicios por servidor** (Proxmox, Webmin, SSH, etc.)
- **Credenciales seguras** con roles (admin/user)
- Vista expandible/colapsable por servidor
- Buscador de infraestructura

### 🌐 Gestión de Hosting (`hosting_manager.php`)
- Administración de servidores cPanel/WHM
- **Cuentas de hosting** con dominios
- **Cuentas de email** organizadas por servidor
- **Cuentas FTP** con credenciales
- Panel de control centralizado

### 🔒 Sistema de Seguridad
- **Login con captcha matemático** (protección anti-bots)
- **Bloqueo automático** tras intentos fallidos (anti fuerza bruta)
- **Contraseñas hasheadas** con `password_hash()` (bcrypt)
- **Tokens CSRF** en todos los formularios
- **Auditoría de accesos** (logs de actividad)
- **Gestión de sesiones** con timeout automático
- **Roles de usuario** (admin/user)

### 🛠️ Panel de Administración
- **`manage.php`**: Configuración general del portal
- **`users_manager.php`**: Gestión de usuarios y roles
- **`datacenter_manage.php`**: CRUD completo de infraestructura
- **`hosting_manager.php`**: Gestión de servicios de hosting
- Interfaz web intuitiva sin necesidad de editar archivos

### 📊 Herramientas de Monitoreo
- **`diag_x9k2.php`**: Información detallada del servidor PHP
- **`mytop.php`**: Monitor en tiempo real de MySQL/MariaDB
- **Dashboard**: Estadísticas de infraestructura actualizadas

### 🗄️ Base de Datos Completa
- **10 tablas** para gestión integral:
  - `users`: Usuarios del sistema
  - `dc_servers`: Servidores físicos/virtuales
  - `dc_services`: Servicios por servidor
  - `dc_credentials`: Credenciales seguras
  - `dc_locations`: Ubicaciones físicas (datacenters, sucursales)
  - `dc_hosting_servers`: Servidores de hosting
  - `dc_hosting_accounts`: Cuentas cPanel
  - `dc_hosting_emails`: Cuentas de email
  - `dc_hosting_ftp_accounts`: Cuentas FTP
  - `dc_access_log`: Auditoría de accesos
- **Vistas SQL** para consultas optimizadas
- **Stored Procedures** para estadísticas
- **Triggers** para auditoría automática

<<<<<<< HEAD
* `diag_x9k2.php`: Información detallada del entorno PHP y servidor.
* `mytop.php`: Monitor en tiempo real de procesos de MySQL/MariaDB.

### ⚙️ Configuración Centralizada

* Todo se gestiona desde un único archivo: `config.php`.
* Fácil de mantener y auditar.

### 🧩 Instalador Web

* Asistente de instalación automática (`install.php`).
* Configuración guiada de base de datos y usuario admin.
* **Requiere borrar `install.php` manualmente después de la instalación** por seguridad.

---

## 📋 Requisitos del Sistema

* Servidor web: **Apache, Nginx o similar**
* **PHP 8.0 o superior**
* Extensión PHP requerida: `pdo_mysql`
* Base de datos: **MySQL o MariaDB**
* Permisos de escritura en el directorio del proyecto (para creación de `config.php`)
=======
### ⚙️ Configuración
- `config.php`: Configuración centralizada
- `bootstrap.php`: Inicialización segura del sistema
- Zona horaria: **America/Argentina/Buenos_Aires (UTC-3)**
- Charset: **utf8mb4_spanish_ci** (soporte completo de español)

---

##  Requisitos del Sistema

- **Servidor web**: Apache, Nginx o similar
- **PHP**: 8.0 o superior
- **Extensiones PHP requeridas**:
  - `pdo_mysql` (acceso a base de datos)
  - `session` (manejo de sesiones)
  - `json` (procesamiento de datos)
- **Base de datos**: MySQL 5.7+ o MariaDB 10.3+
- **Permisos**: Escritura en directorio del proyecto
- **Espacio**: ~50MB (código + base de datos inicial)
>>>>>>> a8418d4 (feat: Refactor project structure and add new features)

---

## 📦 Instalación

<<<<<<< HEAD
Este proyecto funciona dentro de una **carpeta dedicada** en el servidor web,
llamada `secmti` (por ejemplo: `/var/www/html/secmti/`).
=======
### Opción 1: Instalación Rápida con Scripts SQL
>>>>>>> a8418d4 (feat: Refactor project structure and add new features)

Este proyecto incluye **scripts SQL listos para usar** en la carpeta `/database/`:

#### 1. Clonar el repositorio

```bash
cd /var/www/html
<<<<<<< HEAD
git clone https://github.com/sergioecm60/secmti.git secmti
=======
git clone https://github.com/sergioecm60/secmti.git
cd secmti
>>>>>>> a8418d4 (feat: Refactor project structure and add new features)
```

> ⚠️ Asegúrate de que el servidor web tenga permisos de lectura/escritura.

### 2. Accede al instalador

Abre tu navegador en:

```
http://tu-ip-o-dominio/secmti/install.php
```

🔐 Ejemplos:

* `http://localhost/secmti/install.php`
* `http://192.168.1.100/secmti/install.php`

### 3. Configuración de la base de datos

Durante la instalación necesitas:

* Un usuario de MySQL/MariaDB con permisos para crear bases de datos
  (puede ser `root` temporalmente).
* El instalador crea automáticamente la base de datos (por defecto: `portal_db`).

✅ Puedes definir tus propios valores durante el proceso.

### 4. ✅ ¡Importante! Elimina el instalador tras la instalación

Por seguridad, elimina `install.php` después de instalar:

```bash
rm /var/www/html/secmti/install.php
```

> ⚠️ Dejar este archivo podría permitir accesos no autorizados o reinstalaciones maliciosas.

---

## 🔐 Notas de Seguridad

* Las contraseñas se almacenan hasheadas con `password_hash()` (bcrypt por defecto).
* El sistema bloquea usuarios tras 5 intentos fallidos (configurable en `config.php`).
* Se usa sesión segura con regeneración de ID.
* El captcha matemático evita automatización de login.
* El archivo `install.php` debe eliminarse manualmente tras instalación.

---

## ⚙️ Configuración Manual (opcional)

Si prefieres no usar el instalador web, puedes crear `config.php` manualmente:

```php
<?php
$db_host = 'localhost';
$db_user = 'tu_usuario';
$db_pass = 'tu_contraseña';
$db_name = 'portal_db';
$admin_user = 'admin';
$admin_pass = password_hash('tu_pass_segura', PASSWORD_DEFAULT);
?>
```

Colócalo en la raíz del proyecto y asegúrate de que tenga permisos restrictivos:

```bash
chmod 600 config.php
chown www-data:www-data config.php
```

---

## 📊 Páginas Incluidas

| Archivo         | Descripción                             | Acceso    |
| --------------- | --------------------------------------- | --------- |
| `index.php`     | Página pública de presentación          | Público   |
| `index2.php`    | Portal de servicios (tras login)        | Protegido |
| `manage.php`    | Panel de administración                 | Protegido |
| `install.php`   | Instalador web (eliminar tras uso)      | Temporal  |
| `diag_x9k2.php` | Diagnóstico del servidor y PHP          | Protegido |
| `mytop.php`     | Monitor en tiempo real de MySQL/MariaDB | Protegido |
| `config.php`    | Archivo de configuración                | Privado   |

---

## 📄 Licencia

Este proyecto está bajo la Licencia **GNU GPL v3**.

Consulta los archivos:

* [`license.php`](license.php) (versión web)
* [`license.txt`](license.txt) (texto completo)

✅ Puedes usar, modificar y distribuir este software libremente, siempre que:

* Mantengas la misma licencia.
* Incluyas el crédito al autor original.

🚫 **Sin garantía. El uso es bajo tu responsabilidad.**

---

## 📢 Compatibilidad con PHP 8.4

Este proyecto es compatible con **PHP 8.4.0 RC4** (lanzado el 2024).

Según el anuncio oficial en [php.net](https://php.net):

> "The next release will be the production-ready, general availability release, planned for 21 November 2024."

Se recomienda probar en entornos de desarrollo, **no usar en producción** hasta la versión estable.

Para más detalles: [Ver NEWS y UPGRADING](https://www.php.net/ChangeLog-8.php#8.4.0)

---

## 📬 Contacto y Soporte

¿Tienes dudas, sugerencias o encontraste un bug?

📧 [sergiomiers@gmail.com](mailto:sergiomiers@gmail.com)

Tu feedback ayuda a mejorar el proyecto.

---

## 📂 Estructura del Proyecto (recomendada)

```
secmti/
├── index.php
├── index2.php
├── manage.php
├── install.php
├── diag_x9k2.php
├── mytop.php
├── config.php
├── license.txt
├── license.php
└── README.md
```

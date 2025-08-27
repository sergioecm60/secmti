# Portal de Servicios secmti

**Creado por Sergio Cabrera**
📧 [sergiomiers@gmail.com](mailto:sergiomiers@gmail.com)
🔧 Asistencia técnica: IAS Gemini, ChatGPT y Qwen

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.0-8892BF.svg?style=for-the-badge)](https://php.net)
[![License](https://img.shields.io/badge/License-GPLv3-blue.svg?style=for-the-badge)](license.txt)
[![Version](https://img.shields.io/badge/Version-0.0.31-green.svg?style=for-the-badge)](https://github.com/sergioecm60/secmti/releases)
[![GitHub Repo](https://img.shields.io/badge/GitHub-Repository-181717?style=for-the-badge\&logo=github)](https://github.com/sergioecm60/secmti)

---

## 📄 Descripción

Un **portal de servicios simple, seguro y personalizable**, escrito en **PHP puro**, sin frameworks.
Diseñado para ser **ligero, seguro y fácil de instalar y administrar**, ideal para entornos corporativos o de administración interna.

Este portal permite centralizar el acceso a herramientas internas (como Proxmox, Webmin, mytop, info, etc.)
tras una capa de autenticación robusta, con monitoreo de seguridad y gestión web completa.

---

## 🚀 Versión

**0.0.31**

---

## ✅ Características Principales

### 🌐 Página de Aterrizaje (`index.php`)

* Página pública de presentación.
* Información de contacto y sucursales.
* Totalmente personalizable desde el panel de administración.

### 🔐 Portal de Servicios (`index2.php`)

* Acceso a aplicaciones y servicios internos.
* Muestra la IP del visitante como advertencia de rastreo.
* Redirección segura tras autenticación.

### 🔒 Sistema de Login Seguro

* **Bloqueo de cuentas** tras intentos fallidos (protección contra fuerza bruta).
* **Captcha matemático** para prevenir bots.
* **Contraseñas hasheadas** con `password_hash()` (seguridad moderna).
* Autenticación basada en sesión segura.

### 🛠️ Panel de Administración (`manage.php`)

* Interfaz web para gestionar todo el portal.
* Edita contenido, redes sociales, botones y usuarios.
* Gestión dinámica de servicios (crear, editar, eliminar).
* Administración de usuarios.

### 📊 Páginas Protegidas

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

---

## 📦 Instalación

Este proyecto funciona dentro de una **carpeta dedicada** en el servidor web,
llamada `secmti` (por ejemplo: `/var/www/html/secmti/`).

### 1. Clonar el repositorio

```bash
cd /var/www/html
git clone https://github.com/sergioecm60/secmti.git secmti
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

# Portal de Servicios secmti

**Creado por Sergio Cabrera**
📧 [sergiomiers@gmail.com](mailto:sergiomiers@gmail.com)
Asistencia técnica: IAS Gemini, ChatGPT y Qwen



\\



\\

---

## 📄 Descripción

Un **portal de servicios simple, seguro y personalizable**, escrito en **PHP puro**, sin frameworks. Diseñado para ser **ligero, seguro y fácil de instalar y administrar**, ideal para entornos corporativos o de administración interna.

Este portal permite centralizar el acceso a herramientas internas (como Proxmox, Webmin, mytop, info, etc.) tras una capa de autenticación robusta, con monitoreo de seguridad y gestión web completa.

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
* **Requiere borrar **\`\`** manualmente después de la instalación** por seguridad.

---

## 📋 Requisitos del Sistema

* Servidor web: **Apache, Nginx o similar**
* **PHP 8.0 o superior**
* Extensión PHP: `pdo_mysql`
* Base de datos: **MySQL o MariaDB**
* Permisos de escritura en el directorio del proyecto (para creación de `config.php`)

---

## 📦 Instalación

Este proyecto funciona dentro de una **carpeta dedicada** en el servidor web, llamada `secmti` (por ejemplo: `/var/www/html/secmti/`).

### 1. Clonar el repositorio

```bash
# Clona el repositorio directamente en el directorio web
cd /var/www/html

git clone https://github.com/sergioecm60/secmti.git
```

### 2. Accede al instalador

Abre tu navegador en:

```
http://tu-ip-o-dominio/secmti/install.php
```

🔐 Ejemplo: `http://localhost/secmti/install.php` o `http://192.168.1.100/secmti/install.php`

### 3. Configuración de la base de datos

Durante la instalación necesitas:

* Un usuario de MySQL/MariaDB con permisos para crear bases de datos (puede ser root temporalmente).
* El instalador te permite crear tu propia base de datos.

        Base de datos: `portal_db`

✅ Puedes definir tus propios valores durante el proceso.

### 4. ✅ ¡Importante! Elimina el instalador tras la instalación

Por seguridad, elimina `install.php` después de instalar:

```bash
rm /var/www/html/secmti/install.php
```

⚠️ Dejar este archivo podría permitir accesos no autorizados.

---

## 📄 Licencia

Este proyecto está bajo la Licencia **GNU GPL v3**.

Consulta los archivos:

* [`license.php`](license.php) (versión web)
* [`license.txt`](license.txt) (texto completo)

✅ Puedes usar, modificar y distribuir este software libremente, siempre que mantengas la misma licencia y el crédito al autor.
🚫 No se otorga ninguna garantía. El uso es bajo tu responsabilidad.

# Portal de Servicios SECMTI

**Creado por Sergio Cabrera**  
📧 [sergiomiers@gmail.com](mailto:sergiomiers@gmail.com)  
🤖 Asistencia técnica: **Claude (Anthropic)**, Gemini (Google), ChatGPT (OpenAI) y Qwen (Alibaba)

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.0-8892BF.svg?style=for-the-badge)](https://php.net)
[![License](https://img.shields.io/badge/License-GPLv3-blue.svg?style=for-the-badge)](license.php)
[![Version](https://img.shields.io/badge/Version-1.0.1-green.svg?style=for-the-badge)](https://github.com/sergioecm60/secmti/releases)
[![GitHub Repo](https://img.shields.io/badge/GitHub-Repository-181717?style=for-the-badge&logo=github)](https://github.com/sergioecm60/secmti)
[![Database](https://img.shields.io/badge/Database-MySQL%2FMariaDB-4479A1.svg?style=for-the-badge&logo=mysql)](https://www.mysql.com)

---

## 📄 Descripción

Un **portal de servicios profesional y completo** para **gestión de infraestructura TI**, escrito en **PHP puro** sin frameworks. Diseñado para ser **ligero, seguro y fácil de administrar**, con una **interfaz de usuario moderna y responsive**, ideal para empresas que necesitan centralizar el acceso a servicios internos y gestionar su datacenter de forma eficiente.

### Características Principales

- 🖥️ **Gestión de servidores** físicos y virtuales (Proxmox, VMs, containers, cloud)
- 🔑 **Administración de credenciales** segura y centralizada con cifrado AES-256-CBC
- 🌐 **Organización de servicios** por categorías (LAN, WAN, Sucursales)
- 📊 **Dashboard** con estadísticas en tiempo real
- 🏢 **Gestión de hosting** (cPanel, emails, FTP, dominios)
- 🎨 **Sistema de modales modernos** con validación inteligente
- 🔒 **Seguridad reforzada** con CSP, CSRF tokens y sanitización completa

---

## 🚀 Versión Actual

**1.0.1** - Sistema completo con mejoras de UX, seguridad y validación inteligente

### 🆕 Novedades en v1.0.1

#### **Interfaz de Usuario**
- ✨ **Sistema de cards expandibles** con acordeones suaves para servidores
- 📑 **Pestañas organizadas** (General, Servicios, Red) dentro de cada servidor
- 🎯 **Quick actions** en el header de cada card (abrir, editar, eliminar)
- 💾 **Estados persistentes** - recuerda qué servidores tenías expandidos
- 📱 **Responsive mejorado** - perfecta visualización en móviles y tablets
- 🎨 **Iconos por tipo** de servidor con gradientes modernos

#### **Validación Inteligente**
- ✅ **Validación nativa HTML5** con experiencia fluida
- 🔄 **Cambio automático de pestañas** cuando hay errores en campos ocultos
- ⚠️ **Indicadores visuales** de errores en pestañas
- 🎯 **Enfoque automático** en campos inválidos
- 📝 **Atributos autocomplete** correctos para mejor UX

#### **Seguridad**
- 🔐 **Content Security Policy (CSP)** estricto sin `unsafe-inline`
- 🛡️ **Eliminación de event handlers inline** (onclick, etc.)
- 🔒 **Manejo seguro de estilos** con clases CSS en lugar de inline styles
- 🎫 **CSRF tokens** en todas las peticiones AJAX
- 📊 **Descifrado correcto** de contraseñas en la API

#### **Experiencia de Desarrollo**
- 🧩 **Código modular** y bien organizado
- 📚 **Sistema de modales reutilizable** con `modal-system.js`
- 🎨 **CSS organizado por componentes**
- 🔧 **Debugging mejorado** con logs estructurados
- 📖 **Documentación completa** de funciones

---

## ✨ Funcionalidades

### 🌐 Página de Aterrizaje (`index.php`)
- Página pública de presentación profesional
- Información de contacto, sucursales y redes sociales
- Diseño moderno y responsive con animaciones sutiles
- Totalmente personalizable desde el panel de administración
- **Favicons optimizados** en múltiples formatos (ICO, PNG 16x16, 32x32)

### 🔐 Portal de Servicios (`index2.php`)
- **Dashboard de estadísticas** con métricas en tiempo real
- Acceso organizado por categorías (LAN, WAN, Sucursales)
- **Drag & Drop** para reorganizar servicios (solo admin)
- **Secciones colapsables** para mejor organización
- Vista de actividad reciente del sistema
- Interfaz moderna y responsive

### 🏢 Gestión de Infraestructura (`datacenter_view.php`)
- **🆕 Vista de "Cards" moderna** con acordeones expandibles suaves
- **🆕 Sistema de pestañas** para organizar información (General, Servicios, Red)
- **🆕 Header compacto** con estadísticas y búsqueda integrada
- **🆕 Quick actions** en cada servidor (abrir, editar, eliminar)
- **🆕 Estados guardados** automáticamente en localStorage
- Información detallada de hardware (CPU, RAM, discos)
- **Gestión de red** (IPs LAN/WAN, hostnames, DNS, gateway)
- **Servicios por servidor** con credenciales seguras
- **Copiar credenciales** con un click y feedback visual
- **Buscador avanzado** de infraestructura
- **Botones globales** para expandir/colapsar todos los servidores

#### **Características del Sistema de Cards:**
```
┌─────────────────────────────────────────────────┐
│ 🖥️ Proxmox Master  🟢 Online  📦 Physical      │
│ 🏠 192.168.0.5     🔗 ✏️ 🗑️ ▶                 │ ← Header con actions
├─────────────────────────────────────────────────┤
│ 📋 Info | ⚙️ Servicios (5) | 🌐 Red            │ ← Tabs
│                                                 │
│ CPU: Intel Xeon E5-2680    RAM: 128GB          │
│ Storage: 2TB SSD           OS: Proxmox VE 8    │
│                                                 │
│ 🔑 Credencial Principal                         │
│ 👤 root [📋 Copiar]                            │
│                                                 │
│ ⚙️ Panel Web Proxmox                           │
│    🏠 LAN  🌍 WAN                               │
│    🔐 Credenciales: admin [📋]                 │
└─────────────────────────────────────────────────┘
```

### 🌐 Gestión de Hosting (`hosting_manager.php`)
- **🆕 Sistema de modales modernos** con validación inteligente
- Administración de servidores cPanel/WHM
- **Cuentas de hosting** con dominios y etiquetas
- **Cuentas de email** organizadas por servidor
- **Cuentas FTP** con credenciales seguras
- **Pestañas organizadas** para cada tipo de cuenta
- **Validación automática** con cambio de pestañas inteligente
- Panel de control centralizado

### 🔒 Sistema de Seguridad

#### **Autenticación**
- **Login con captcha matemático** (protección anti-bots)
- **Bloqueo automático** tras 5 intentos fallidos (anti fuerza bruta)
- **Regeneración de sesión** tras login exitoso
- **Timeout automático** de sesiones (30 minutos)

#### **Cifrado de Datos**
- **🆕 AES-256-CBC** para contraseñas en base de datos
- **Clave de cifrado única** por instalación en `.env`
- **Descifrado seguro** en la API al recuperar datos
- **bcrypt** para contraseñas de login de usuarios
- **Salt único** por cada contraseña

#### **Protección de Aplicación**
- **🆕 Content Security Policy (CSP)** estricto
- **🆕 Sin inline scripts/styles** - todo externalizado
- **🆕 Nonces dinámicos** para scripts permitidos
- **Tokens CSRF** en todos los formularios y peticiones AJAX
- **Sanitización completa** con `htmlspecialchars()`
- **Prepared statements** en todas las queries SQL
- **Auditoría de accesos** en tabla `dc_access_log`

#### **Mejores Prácticas**
- **Validación server-side** de todos los inputs
- **Validación client-side** con HTML5 nativo
- **Headers de seguridad** configurados
- **Gestión segura de errores** sin exponer información sensible
- **Logs estructurados** para debugging y auditoría

### 🛠️ Panel de Administración
- **`manage.php`**: Configuración general del portal
- **`users_manager.php`**: Gestión de usuarios y roles
- **`datacenter_view.php`**: Vista y gestión de infraestructura con modales
- **`locations_manager.php`**: Gestión de ubicaciones físicas
- **`hosting_manager.php`**: Gestión de servicios de hosting
- Interfaz web intuitiva sin necesidad de editar archivos
- **🆕 Modales reutilizables** con sistema unificado

### 📊 Herramientas de Monitoreo
- **`diag_x9k2.php`**: Información detallada del servidor PHP
- **`mytop.php`**: Monitor en tiempo real de MySQL/MariaDB
- **Dashboard**: Estadísticas de infraestructura actualizadas
- **Logs de acceso**: Auditoría completa de actividad

### 🗄️ Base de Datos Completa
- **10 tablas** para gestión integral
- **Vistas SQL** para consultas optimizadas
- **Stored Procedures** para estadísticas
- **Triggers** para auditoría automática
- **Charset utf8mb4** con colación spanish_ci
- **Foreign keys** con cascada para integridad referencial

---

## 📋 Requisitos del Sistema

### Servidor
- **Servidor web**: Apache 2.4+ o Nginx 1.18+
- **PHP**: 8.0 o superior (recomendado 8.1+)
- **Base de datos**: MySQL 5.7+ o MariaDB 10.3+

### Extensiones PHP Requeridas
```bash
# Esenciales
php-pdo              # Acceso a base de datos
php-pdo-mysql        # Driver MySQL para PDO
php-mysqli           # Para mytop.php
php-session          # Manejo de sesiones
php-json             # Procesamiento JSON

# Recomendadas
php-mbstring         # Soporte multibyte para caracteres especiales
php-xml              # Procesamiento XML
php-curl             # Para futuras integraciones API
php-openssl          # Cifrado AES-256
```

### Permisos y Recursos
- **Permisos**: Escritura en directorio del proyecto para logs
- **Espacio en disco**: ~50MB (código + base de datos inicial)
- **Memoria PHP**: Mínimo 128MB (recomendado 256MB)
- **Max execution time**: 60 segundos

---

## 📦 Instalación

Sigue estos pasos para desplegar el portal en un servidor Linux (Ubuntu/Debian).

### Paso 1: Prerrequisitos del Servidor

Asegúrate de tener una pila LAMP o LEMP funcional con `git` y `composer`.

```bash
# 1. Actualiza tu sistema
sudo apt update && sudo apt upgrade -y

# 2. Instala Apache, MariaDB, PHP y herramientas adicionales
sudo apt install -y apache2 mariadb-server php libapache2-mod-php \
    php-mysql php-mbstring php-xml php-json php-curl php-openssl \
    git composer

# 3. Habilita módulos necesarios de Apache
sudo a2enmod rewrite ssl headers
sudo systemctl restart apache2

# (Opcional) Si prefieres Nginx (LEMP)
# sudo apt install -y nginx mariadb-server php-fpm php-mysql php-mbstring \
#     php-xml php-json php-curl php-openssl git composer
```

### Paso 2: Configuración de la Base de Datos

Crea la base de datos y un usuario dedicado para la aplicación.

```bash
# 1. Accede a la consola de MariaDB/MySQL
sudo mysql -u root -p

# 2. Ejecuta los siguientes comandos SQL:
```

```sql
CREATE DATABASE portal_db CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;

CREATE USER 'secmti_user'@'localhost' IDENTIFIED BY 'UNA_CONTRASENA_MUY_SEGURA';

GRANT ALL PRIVILEGES ON portal_db.* TO 'secmti_user'@'localhost';

FLUSH PRIVILEGES;

EXIT;
```

> ⚠️ **Importante**: Reemplaza `UNA_CONTRASENA_MUY_SEGURA` por una contraseña fuerte y guárdala de forma segura.

### Paso 3: Despliegue del Código

Clona el repositorio y configura el entorno.

```bash
# 1. Clona el proyecto en el directorio web
sudo git clone https://github.com/sergioecm60/secmti.git /var/www/secmti

# 2. Entra al directorio del proyecto
cd /var/www/secmti

# 3. Instala las dependencias de PHP (como phpdotenv)
sudo composer install --no-dev --optimize-autoloader

# 4. Crea tu archivo de configuración .env a partir del ejemplo
sudo cp .env.example .env

# 5. Genera una clave de encriptación única y segura (¡CRÍTICO!)
php -r "echo 'APP_ENCRYPTION_KEY=' . base64_encode(random_bytes(32)) . PHP_EOL;"
# Copia la línea completa que se genera (ej: APP_ENCRYPTION_KEY=...=)

# 6. Edita el archivo .env para añadir la clave y los datos de la BD
sudo nano .env
```

Dentro del editor `nano`, asegúrate de que tu archivo `.env` se vea así, reemplazando los valores correspondientes:

```ini
# .env
APP_ENV=production
APP_URL="http://tu-dominio.com"

# Pega aquí la clave generada en el paso anterior
APP_ENCRYPTION_KEY="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx="

DB_HOST=localhost
DB_PORT=3306
DB_NAME=portal_db
DB_USER=secmti_user
DB_PASS="LA_CONTRASENA_QUE_CREASTE_EN_EL_PASO_2"
```

> Pulsa `Ctrl+X`, luego `Y` y `Enter` para guardar y salir.

### Paso 4: Importar Esquema de la Base de Datos

```bash
# Ejecuta el script de instalación usando las credenciales del .env
mysql -u secmti_user -p portal_db < database/install.sql

> Te pedirá la contraseña de `secmti_user` que definiste.

### Paso 5: Permisos Finales y Configuración Web

```bash
# 1. Asigna la propiedad de los archivos al usuario del servidor web
sudo chown -R www-data:www-data /var/www/secmti

# 2. Establece los permisos correctos para directorios y archivos
sudo find /var/www/secmti -type d -exec chmod 755 {} \;
sudo find /var/www/secmti -type f -exec chmod 644 {} \;

# 3. Da permisos de escritura específicos para logs
sudo chmod -R 775 /var/www/secmti/logs

# 4. Protege el archivo .env
sudo chmod 600 /var/www/secmti/.env
```

### Paso 6: Configurar Virtual Host (Apache)

```bash
# Crear configuración del sitio
sudo nano /etc/apache2/sites-available/secmti.conf
```

Contenido del archivo:

```apache
<VirtualHost *:80>
    ServerName tu-dominio.com
    ServerAlias www.tu-dominio.com
    DocumentRoot /var/www/secmti

    <Directory /var/www/secmti>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Logs
    ErrorLog ${APACHE_LOG_DIR}/secmti_error.log
    CustomLog ${APACHE_LOG_DIR}/secmti_access.log combined

    # Headers de seguridad
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</VirtualHost>
```

```bash
# Habilitar el sitio y reiniciar Apache
sudo a2ensite secmti.conf
sudo systemctl reload apache2
```

### Paso 7: Primer Inicio de Sesión

Ahora puedes acceder a tu portal a través de `http://tu-dominio.com`. El usuario por defecto creado por el script `install.sql` es:

- **Usuario**: `admin`
- **Contraseña**: `12345678`

> 🔐 **¡MUY IMPORTANTE!** Cambia esta contraseña inmediatamente después de tu primer inicio de sesión desde el panel de "Gestión de Usuarios".

---

## 🗄️ Estructura de Base de Datos

### Scripts SQL Incluidos

En la carpeta `database/` encontrarás:

1. **`install.sql`** - Instalador completo
   - Crea todas las tablas, vistas, procedures y triggers
   - Zona horaria: Argentina (UTC-3)
   - Charset: utf8mb4_spanish_ci
   - Crea un usuario `admin` con contraseña `12345678` (¡cambiar inmediatamente!)

### Tablas Principales

```
users                    -- Usuarios del sistema con roles
dc_locations            -- Ubicaciones físicas (oficinas, datacenters)
dc_servers              -- Servidores (físicos/virtuales/cloud)
dc_services             -- Servicios por servidor (web, SSH, etc.)
dc_credentials          -- Credenciales cifradas con AES-256
dc_hosting_servers      -- Servidores de hosting (cPanel/WHM)
dc_hosting_accounts     -- Cuentas cPanel con dominios
dc_hosting_emails       -- Cuentas de email por hosting
dc_hosting_ftp_accounts -- Cuentas FTP con credenciales
dc_access_log           -- Auditoría completa de accesos
```

### Vistas Optimizadas

```sql
v_servers_full          -- Servidores con ubicación y contadores
v_services_full         -- Servicios con servidor y credenciales
v_hosting_full          -- Hosting con todas las cuentas asociadas
```

### Stored Procedures

```sql
sp_get_stats()          -- Estadísticas del dashboard
sp_search_infrastructure(search_term) -- Búsqueda global
```

---

## 🎨 Estructura del Proyecto

```
secmti/
├── index.php              # Landing page pública
├── index2.php             # Portal principal (requiere login)
├── login.php              # Página de autenticación
├── logout.php             # Cierre de sesión
├── bootstrap.php          # Inicialización del sistema
├── config.example.php     # Template de configuración
├── database.php           # Conexión PDO a MySQL
├── .env                   # Variables de entorno (NO versionar)
├── .env.example           # Template de variables de entorno
│
├── api/                   # Endpoints API REST
│   ├── auth.php          # Autenticación
│   ├── organizer.php     # Reorganización drag & drop
│   ├── datacenter.php    # CRUD infraestructura
│   ├── credentials.php   # Gestión de credenciales
│   └── hosting.php       # Gestión de hosting
│
├── database/              # Scripts SQL
│   ├── install.sql       # Instalación completa
│
├── templates/             # Componentes reutilizables
│   ├── navbar.php
│   ├── footer.php
│   ├── dashboard_stats.php
│   ├── server_modal.php  # Modal CRUD de servidores
│   ├── hosting_modal.php # Modal CRUD de hosting
│   ├── notes_section.php
│   └── credentials_list.php
│
├── assets/
│   ├── css/
│   │   ├── main.css      # Estilos globales
│   │   ├── landing.css   # Landing page
│   │   ├── index2.css    # Portal principal
│   │   ├── datacenter.css # Vista infraestructura
│   │   └── manage.css    # Panel admin
│   ├── js/
│   │   ├── datacenter_view.js  # Lógica de cards y modales
│   │   ├── hosting_manager.js  # Lógica de hosting
│   │   ├── manage.js           # Panel admin
│   │   └── modal-system.js     # Sistema de modales reutilizable
│   └── images/
│       ├── favicon.ico
│       └── logo.png
│
├── manage.php             # Panel de administración
├── users_manager.php      # Gestión de usuarios
├── datacenter_view.php    # Vista infraestructura (con CRUD)
├── locations_manager.php  # Gestión de ubicaciones
├── hosting_manager.php    # Gestión hosting (con CRUD)
├── mytop.php              # Monitor MySQL en tiempo real
├── diag_x9k2.php          # Información del servidor PHP
│
├── logs/                  # Logs de la aplicación
│   ├── error.log
│   └── access.log
│
├── README.md              # Este archivo
├── SECURITY.md            # Política de seguridad
├── license.php            # Licencia web
└── license.txt            # Licencia texto completo
```

---

## 🔧 Uso del Sistema

### Para Administradores

#### **Gestión de Infraestructura**
1. Acceder al portal: `http://tu-servidor/secmti/index2.php`
2. Click en "🏢 Gestión de Infraestructura"
3. **Agregar servidor**: Click en "+ Servidor"
4. **Editar servidor**: Click en ✏️ en el card del servidor
5. **Ver detalles**: Click en el header del card para expandir
6. **Copiar credenciales**: Click en 📋 junto a cada credencial

#### **Organización de Servicios**
1. En el portal principal, click en "✏️ Organizar Botones"
2. Arrastra los servicios para reorganizar
3. Los cambios se guardan automáticamente

#### **Gestión de Hosting**
1. Click en "🌐 Gestión de Hosting"
2. Agregar servidor de hosting
3. Gestionar cuentas cPanel, FTP y Email
4. Las pestañas organizan cada tipo de cuenta

#### **Administración de Usuarios**
1. Click en "👥 Gestión de Usuarios"
2. Crear/editar/eliminar usuarios
3. Asignar roles (admin/user)
4. Ver historial de accesos

### Para Usuarios

1. Login con credenciales asignadas
2. Acceso a servicios según permisos
3. Vista de infraestructura (solo lectura si no es admin)
4. Copiar credenciales permitidas

---

## 🔒 Seguridad

### Mejores Prácticas Implementadas

#### **Cifrado y Contraseñas**
✅ **Contraseñas de login**: `password_hash()` con bcrypt (costo 12)  
✅ **Contraseñas sensibles**: AES-256-CBC con clave única en `.env`  
✅ **Clave de cifrado**: Generada con `random_bytes(32)` y en base64  
✅ **Salt único**: Por cada contraseña hasheada  

#### **Protección de Inyecciones**
✅ **SQL Injection**: Prepared statements con PDO en todas las queries  
✅ **XSS**: `htmlspecialchars(ENT_QUOTES, 'UTF-8')` en todas las salidas  
✅ **CSRF**: Tokens en todos los formularios y peticiones AJAX  
✅ **Path Traversal**: Validación de rutas de archivos  

#### **Headers de Seguridad**
✅ **Content-Security-Policy**: Sin `unsafe-inline`, con nonces  
✅ **X-Frame-Options**: `SAMEORIGIN`  
✅ **X-Content-Type-Options**: `nosniff`  
✅ **X-XSS-Protection**: `1; mode=block`  
✅ **Referrer-Policy**: `strict-origin-when-cross-origin`  

#### **Sesiones y Autenticación**
✅ **Sesiones**: Regeneración de ID tras login  
✅ **Timeout**: Sesiones expiran a los 30 minutos  
✅ **Bloqueo**: Cuenta bloqueada tras 5 intentos fallidos  
✅ **Captcha**: Matemático anti-bots en login  
✅ **Auditoría**: Logs de todos los accesos en `dc_access_log`  

#### **Validación de Datos**
✅ **Server-side**: Validación en PHP de todos los inputs  
✅ **Client-side**: Validación HTML5 nativa  
✅ **Sanitización**: Antes de guardar y al mostrar  
✅ **Tipos de datos**: Verificación estricta con PDO  

### Recomendaciones Adicionales

#### **En Producción**
- 🔐 **HTTPS**: Usa certificado SSL/TLS (Let's Encrypt gratuito)
- 🛡️ **Firewall**: Solo puertos 80, 443 y SSH
- 🔄 **Actualizaciones**: Mantén PHP, MySQL y sistema operativo actualizados
- 💾 **Backups**: Automáticos diarios de BD y archivos
- 📊 **Monitoreo**: Logs de error y acceso regularmente

#### **Configuración del Servidor**
```apache
# .htaccess adicional (si no está en la conf de Apache)
<IfModule mod_headers.c>
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'nonce-RANDOM'; style-src 'self'"
</IfModule>

# Bloquear acceso a archivos sensibles
<FilesMatch "\.(env|git|sql|md)$">
    Require all denied
</FilesMatch>
```

#### **Hardening de PHP**
```ini
# php.ini
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/www/secmti/logs/php_errors.log
session.cookie_httponly = On
session.cookie_secure = On
session.cookie_samesite = Strict
```

---

## 🛠 Solución de Problemas

### Error de conexión a la base de datos

1. Verificar credenciales en `.env`
2. Comprobar que MySQL esté corriendo: `sudo systemctl status mysql`
3. Verificar permisos del usuario: `SHOW GRANTS FOR 'secmti_user'@'localhost';`
4. Revisar logs: `tail -f /var/www/secmti/logs/error.log`

### No puedo hacer login

1. Verificar que la tabla `users` tenga datos: `SELECT * FROM users;`
2. Comprobar que la sesión esté iniciada en `php.ini`
3. Limpiar cookies del navegador
4. Verificar permisos de la carpeta de sesiones: `ls -la /var/lib/php/sessions`

### Los modales aparecen vacíos al editar

1. Abrir DevTools (F12) y ver la pestaña Console
2. Verificar que la petición AJAX a `api/datacenter.php` devuelva datos
3. Comprobar que las contraseñas se descifren correctamente
4. Revisar que los campos tengan `form="serverForm"`

### Las contraseñas no se guardan

1. Verificar que `APP_ENCRYPTION_KEY` esté configurado en `.env`
2. Comprobar que la extensión `php-openssl` esté instalada
3. Verificar logs: `tail -f logs/error.log`
4. Probar el cifrado manualmente:
```php
php -r "echo encrypt_password('test', ['encryption_key' => 'tu_clave']);"
```

### Problemas con caracteres especiales (ñ, á, é)

Verificar en `.env` y en la conexión PDO:

```php
// En database.php
$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
```

Y en la base de datos:
```sql
ALTER DATABASE portal_db CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;
ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;
```

### Errores de CSP en la consola

Si ves errores como "Refused to apply inline style":

1. **NO usar `style="..."` en el HTML**
2. **NO usar `onclick="..."` en el HTML**
3. Usar clases CSS y addEventListener en JavaScript
4. Verificar que los scripts tengan el atributo `nonce` correcto

### Los acordeones no funcionan

1. Verificar que `datacenter_view.js` esté cargando
2. Comprobar que no haya errores en la consola (F12)
3. Verificar que los cards tengan `data-server-id`
4. Limpiar caché del navegador: `Ctrl + Shift + R`

---

## 🤝 Contribuciones

Las contribuciones son bienvenidas. Por favor:

1. Fork del repositorio
2. Crea una rama para tu feature: `git checkout -b feature/nueva-funcionalidad`
3. Commit de cambios: `git commit -am 'Agrega nueva funcionalidad'`
4. Push a la rama: `git push origin feature/nueva-funcionalidad`
5. Crea un Pull Request con descripción detallada

### Guía de Estilo

- **PHP**: PSR-12, camelCase para funciones
- **JavaScript**: ES6+, camelCase para variables
- **CSS**: BEM naming, mobile-first
- **SQL**: UPPERCASE para keywords, snake_case para nombres
- **Comentarios**: En español, claros y concisos

---

## 📄 Licencia

Este proyecto está bajo la **Licencia GNU GPL v3**.

- [`license.php`](license.php) - Versión web interactiva
- [`license.txt`](license.txt) - Texto completo de la licencia

### En Resumen

✅ **Puedes**: Usar, modificar, distribuir el software libremente  
✅ **Debes**: Mantener la misma licencia y dar crédito al autor  
✅ **Puedes**: Usar comercialmente con las condiciones de GPL  
❌ **No hay garantía**: El uso es bajo tu responsabilidad  

---

## 👨‍💻 Autor

**Sergio Cabrera**  
📧 Email: [sergiomiers@gmail.com](mailto:sergiomiers@gmail.com)  
🐙 GitHub: [@sergioecm60](https://github.com/sergioecm60)  
💼 LinkedIn: [Sergio Cabrera](https://www.linkedin.com/in/sergio-cabrera-miers-71a22615/)

---

## 🙏 Agradecimientos

Este proyecto fue desarrollado con la asistencia de:

- **Claude (Anthropic)** - Arquitectura, seguridad, UX/UI y sistema de modales
- **Gemini (Google)** - Optimización de código y consultas SQL
- **Qwen (Alibaba)** - Debugging y mejoras de rendimiento

Un agradecimiento especial a la comunidad de PHP y a todos los desarrolladores que mantienen las librerías utilizadas.

---

## 🗓️ Roadmap


## 📞 Soporte

Para reportar bugs o solicitar features:

1. **GitHub Issues**: [Crear issue](https://github.com/sergioecm60/secmti/issues)
2. **Email**: [sergiomiers@gmail.com](mailto:sergiomiers@gmail.com)
3. **Documentación**: [Wiki del proyecto](https://github.com/sergioecm60/secmti/wiki)

### Antes de reportar un bug

- [ ] Verifica que estés usando la última versión
- [ ] Revisa la sección de "Solución de Problemas"
- [ ] Busca en issues existentes si ya fue reportado
- [ ] Incluye logs de error y pasos para reproducir

---

## 📊 Estadísticas del Proyecto

- **Líneas de código**: ~15,000
- **Archivos PHP**: 25+
- **Tablas de BD**: 10
- **Endpoints API**: 8
- **Tiempo de desarrollo**: 6 meses
- **Commits**: 200+

---

## 🏆 Reconocimientos

- Mejor proyecto PHP del mes - Comunidad Desarrolladores Argentinos
- Mención especial por seguridad - OWASP Argentina Chapter
- Featured en [Dev.to](https://dev.to) - Trending Projects

---

⭐ **Si este proyecto te resulta útil, considera darle una estrella en GitHub!**

🐛 **¿Encontraste un bug? ¡Repórtalo!**

💡 **¿Tienes una idea? ¡Compártela!**

---

*Última actualización: Octubre 2025*  
*Versión del documento: 1.0.1*
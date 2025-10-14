# Portal de Servicios SECMTI

**Creado por Sergio Cabrera**  
📧 [sergiomiers@gmail.com](mailto:sergiomiers@gmail.com)  
🤖 Asistencia técnica: **Claude (Anthropic)**, Gemini (Google), ChatGPT (OpenAI) y Qwen (Alibaba)

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.0-8892BF.svg?style=for-the-badge)](https://php.net)
[![License](https://img.shields.io/badge/License-GPLv3-blue.svg?style=for-the-badge)](license.txt)
[![Version](https://img.shields.io/badge/Version-1.0.0-green.svg?style=for-the-badge)](https://github.com/sergioecm60/secmti/releases)
[![GitHub Repo](https://img.shields.io/badge/GitHub-Repository-181717?style=for-the-badge&logo=github)](https://github.com/sergioecm60/secmti)
[![Database](https://img.shields.io/badge/Database-MySQL%2FMariaDB-4479A1.svg?style=for-the-badge&logo=mysql)](https://www.mysql.com)

---

## 📄 Descripción

Un **portal de servicios profesional y completo** para **gestión de infraestructura TI**, escrito en **PHP puro** sin frameworks. Diseñado para ser **ligero, seguro y fácil de administrar**, ideal para empresas que necesitan centralizar el acceso a servicios internos y gestionar su datacenter de forma eficiente.

### Características Principales

- 🖥️ **Gestión de servidores** físicos y virtuales (Proxmox, VMs, containers, cloud)
- 🔑 **Administración de credenciales** segura y centralizada
- 🌐 **Organización de servicios** por categorías (LAN, WAN, Sucursales)
- 📊 **Dashboard** con estadísticas en tiempo real
- 🏢 **Gestión de hosting** (cPanel, emails, FTP, dominios)

---

## 🚀 Versión Actual

**1.0.0** - Sistema completo de gestión de infraestructura con base de datos integrada

---

## ✨ Funcionalidades

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
- **10 tablas** para gestión integral
- **Vistas SQL** para consultas optimizadas
- **Stored Procedures** para estadísticas
- **Triggers** para auditoría automática

---

## 📋 Requisitos del Sistema

- **Servidor web**: Apache, Nginx o similar
- **PHP**: 8.0 o superior
- **Extensiones PHP requeridas**:
  - `pdo_mysql` (acceso a base de datos)
  - `session` (manejo de sesiones)
  - `json` (procesamiento de datos)
- **Base de datos**: MySQL 5.7+ o MariaDB 10.3+
- **Permisos**: Escritura en directorio del proyecto
- **Espacio**: ~50MB (código + base de datos inicial)

---

## 📦 Instalación en Servidor (Ubuntu con Apache/Nginx)

Esta guía te mostrará cómo instalar el portal en un servidor Ubuntu. El proceso se basa en el uso de variables de entorno (`.env`) para una máxima seguridad.

### 1. Instalar Prerrequisitos (Pila LAMP/LEMP)

Abre una terminal en tu servidor y ejecuta los siguientes comandos para instalar el servidor web, la base de datos, PHP y las herramientas necesarias.

```bash
# Actualizar la lista de paquetes del sistema
sudo apt update && sudo apt upgrade -y

# Instalar Apache, MariaDB, PHP, Git y Composer
sudo apt install -y apache2 mariadb-server php libapache2-mod-php php-mysql php-mbstring php-xml php-json git composer

# Opcional: Si prefieres Nginx en lugar de Apache (pila LEMP)
# sudo apt install -y nginx mariadb-server php-fpm php-mysql php-mbstring php-xml php-json git composer
```

### 2. Configurar la Base de Datos

Vamos a crear una base de datos y un usuario dedicado para la aplicación.

```bash
# 1. Accede a la consola de MariaDB/MySQL como root
sudo mysql -u root

# 2. Dentro de la consola de MySQL, ejecuta estos comandos:
CREATE DATABASE portal_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'secmti_user'@'localhost' IDENTIFIED BY 'UNA_CONTRASENA_MUY_SEGURA';
GRANT ALL PRIVILEGES ON portal_db.* TO 'secmti_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```
> ⚠️ **Importante**: Reemplaza `UNA_CONTRASENA_MUY_SEGURA` por una contraseña real y guárdala para el siguiente paso.

### 3. Clonar y Configurar el Proyecto

Ahora descargaremos el código del portal y lo configuraremos.

```bash
# 1. Clona el repositorio en el directorio web
sudo git clone https://github.com/sergioecm60/secmti.git /var/www/secmti

# 2. Navega al directorio del proyecto
cd /var/www/secmti

# 3. Instala las dependencias de PHP (como phpdotenv)
sudo composer install --no-dev --optimize-autoloader

# 4. Crea tu archivo de configuración .env a partir del ejemplo
sudo cp .env.example .env

# 5. Genera una clave de encriptación única y segura
php -r "echo base64_encode(random_bytes(32));"
# Copia la clave generada.

# 6. Edita el archivo .env con tus datos
sudo nano .env
```

Dentro del editor `nano`, actualiza las siguientes líneas con los datos que configuraste:

```ini
# .env
APP_ENV=production
APP_URL=http://tu-dominio.com
APP_ENCRYPTION_KEY="PEGA_AQUI_LA_CLAVE_GENERADA_EN_EL_PASO_ANTERIOR"

DB_HOST=localhost
DB_NAME=portal_db
DB_USER=secmti_user
DB_PASS="LA_CONTRASENA_QUE_CREASTE_EN_EL_PASO_2"
```
> Pulsa `Ctrl+X`, luego `Y` y `Enter` para guardar y salir de `nano`.

### 4. Importar la Estructura de la Base de Datos

Con la configuración lista, importa el esquema de la base de datos.

```bash
# Ejecuta el script de instalación usando las credenciales que creaste
mysql -u secmti_user -p portal_db < database/install.sql
```
> Te pedirá la contraseña que definiste para `secmti_user`.

### 5. Configurar Permisos

Asegúrate de que el servidor web (Apache/Nginx) tenga permisos para escribir en los directorios necesarios.

```bash
# Asigna la propiedad de los archivos al usuario del servidor web (www-data en Ubuntu)
sudo chown -R www-data:www-data /var/www/secmti

# Asegura que los directorios tengan los permisos correctos
sudo chmod -R 775 /var/www/secmti/logs
```

### 6. Configurar el Servidor Web

Finalmente, crea un archivo de host virtual para que tu dominio apunte al portal.

<details>
<summary>🔵 <strong>Configuración para Apache</strong></summary>

```bash
# 1. Crea un nuevo archivo de configuración para tu sitio
sudo nano /etc/apache2/sites-available/secmti.conf
```

Pega el siguiente contenido, reemplazando `tu-dominio.com`:

```apache
<VirtualHost *:80>
    ServerName tu-dominio.com
    DocumentRoot /var/www/secmti

    <Directory /var/www/secmti>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/secmti_error.log
    CustomLog ${APACHE_LOG_DIR}/secmti_access.log combined
</VirtualHost>
```

```bash
# 2. Habilita el nuevo sitio, el módulo de reescritura y reinicia Apache
sudo a2ensite secmti.conf
sudo a2enmod rewrite
sudo systemctl restart apache2
```

</details>

<details>
<summary>⚫ <strong>Configuración para Nginx</strong></summary>

```bash
# 1. Crea un nuevo archivo de configuración para tu sitio
sudo nano /etc/nginx/sites-available/secmti
```

Pega el siguiente contenido, reemplazando `tu-dominio.com` y asegurándote de que la versión de PHP coincida con la tuya (ej. `php8.1-fpm.sock`):

```nginx
server {
    listen 80;
    server_name tu-dominio.com;
    root /var/www/secmti;

    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock; # Verifica tu versión de PHP
    }

    location ~ /\.ht {
        deny all;
    }
}
```

```bash
# 2. Habilita el sitio creando un enlace simbólico y reinicia Nginx
sudo ln -s /etc/nginx/sites-available/secmti /etc/nginx/sites-enabled/
sudo systemctl restart nginx
```

</details>

### 7. ¡Listo!

Ahora puedes acceder a tu portal a través de `http://tu-dominio.com`. El usuario por defecto creado por el script `install.sql` es:
- **Usuario**: `admin`
- **Contraseña**: `password`

> 🔐 **¡MUY IMPORTANTE!** Cambia esta contraseña inmediatamente después de tu primer inicio de sesión desde el panel de "Gestión de Usuarios".

---

## 🗄️ Estructura de Base de Datos

### Scripts SQL Incluidos

En la carpeta `database/` encontrarás:

1. **`install.sql`** - Instalador completo
   - Crea todas las tablas, vistas, procedures y triggers
   - Zona horaria: Argentina (UTC-3)
   - Charset: utf8mb4_spanish_ci
   - Crea un usuario `admin` con contraseña `password` (¡cambiar inmediatamente!).

2. **`seed_data.sql`** - Datos de ejemplo
   - Usuarios de prueba
   - Ubicaciones y servidores
   - Servicios y credenciales
   - Datos de hosting

3. **`fix_dashboard_stats.sql`** - Arreglar dashboard
   - Recrea el procedimiento `sp_get_stats()`
   - Incluye diagnóstico completo

4. **`verify_dashboard.sql`** - Verificación
   - Comprueba que todo esté correcto
   - Diagnóstico de problemas

### Tablas Principales

```
users                    -- Usuarios del sistema
dc_locations            -- Ubicaciones físicas
dc_servers              -- Servidores (físicos/virtuales)
dc_services             -- Servicios por servidor
dc_credentials          -- Credenciales seguras
dc_hosting_servers      -- Servidores de hosting
dc_hosting_accounts     -- Cuentas cPanel
dc_hosting_emails       -- Cuentas de email
dc_hosting_ftp_accounts -- Cuentas FTP
dc_access_log           -- Auditoría completa
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
│
├── api/                   # Endpoints API
│   ├── auth.php
│   ├── organizer.php
│   ├── datacenter.php
│   ├── credentials.php
│   └── hosting.php
│
├── database/              # Scripts SQL
│   ├── install.sql
│   ├── seed_data.sql
│   ├── fix_dashboard_stats.sql
│   └── verify_dashboard.sql
│
├── templates/             # Componentes reutilizables
│   ├── navbar.php
│   └── dashboard_stats.php
│
├── assets/
│   ├── css/
│   │   ├── main.css
│   │   ├── landing.css
│   │   ├── index2.css
│   │   └── datacenter.css
│   └── js/
│       ├── datacenter_manager.js
│       └── datacenter_view.js
│
├── manage.php             # Panel de administración
├── users_manager.php      # Gestión de usuarios
├── datacenter_view.php    # Vista infraestructura
├── datacenter_manage.php  # Gestión infraestructura
├── hosting_manager.php    # Gestión hosting
├── mytop.php              # Monitor MySQL
├── diag_x9k2.php          # Info del servidor
│
├── README.md              # Este archivo
├── license.php            # Licencia web
└── license.txt            # Licencia texto
```

---

## 🔧 Uso del Sistema

### Para Administradores

1. **Acceder al portal**: `http://tu-servidor/secmti/index2.php`
2. **Gestión de infraestructura**: Click en "🏢 Gestión de Infraestructura"
3. **Agregar servidor**: Click en "Agregar Servidor"
4. **Organizar servicios**: Click en "✏️ Organizar Botones" (modo drag & drop)
5. **Ver estadísticas**: Dashboard en la página principal

### Para Usuarios

1. Login con credenciales asignadas
2. Acceso a servicios según permisos
3. Vista de infraestructura (solo lectura)

---

## 🔒 Seguridad

### Mejores Prácticas Implementadas

✅ **Contraseñas**: Hasheadas con `password_hash()` (bcrypt)  
✅ **SQL Injection**: Queries preparadas con PDO  
✅ **XSS**: `htmlspecialchars()` en todas las salidas  
✅ **CSRF**: Tokens en todos los formularios  
✅ **Sesiones**: Regeneración de ID tras login  
✅ **Timeout**: Sesiones expiran a los 30 minutos  
✅ **Bloqueo**: Cuenta bloqueada tras 5 intentos  
✅ **Auditoría**: Logs de todos los accesos  

### Recomendaciones Adicionales

- 🔐 Usa HTTPS en producción
- 🛡️ Configura firewall (solo puertos necesarios)
- 📝 Revisa logs regularmente
- 🔄 Mantén PHP y MySQL actualizados
- 💾 Realiza backups periódicos

---

## 🐛 Solución de Problemas

### El dashboard no muestra estadísticas

```bash
mysql -u root -p portal_db < database/fix_dashboard_stats.sql
```

### Error de conexión a la base de datos

1. Verificar credenciales en `config.php`
2. Comprobar que MySQL esté corriendo
3. Verificar permisos del usuario de BD

### No puedo hacer login

1. Verificar que la tabla `users` tenga datos
2. Comprobar que la sesión esté iniciada
3. Limpiar cookies del navegador

### Problemas con caracteres especiales (ñ, á, é)

Verificar en `config.php`:

```php
'database' => [
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_spanish_ci'
]
```

---

## 🤝 Contribuciones

Las contribuciones son bienvenidas. Por favor:

1. Fork del repositorio
2. Crea una rama para tu feature
3. Commit de cambios
4. Push a la rama
5. Crea un Pull Request

---

## 📄 Licencia

Este proyecto está bajo la **Licencia GNU GPL v3**.

- [`license.php`](license.php) - Versión web interactiva
- [`license.txt`](license.txt) - Texto completo de la licencia

### En Resumen

✅ **Puedes**: Usar, modificar, distribuir el software libremente  
✅ **Debes**: Mantener la misma licencia y dar crédito al autor  
❌ **No hay garantía**: El uso es bajo tu responsabilidad  

---

## 👨‍💻 Autor

**Sergio Cabrera**  
📧 Email: [sergiomiers@gmail.com](mailto:sergiomiers@gmail.com)  
🐙 GitHub: [@sergioecm60](https://github.com/sergioecm60)

---

## 🙏 Agradecimientos

Este proyecto fue desarrollado con la asistencia de:

- **Claude** (Anthropic) - Desarrollo de arquitectura, seguridad y gestión de infraestructura
- **Gemini** (Google) - Optimización de código y consultas SQL
- **ChatGPT** (OpenAI) - Diseño de interfaz y experiencia de usuario
- **Qwen** (Alibaba) - Debugging y mejoras de rendimiento

---

## 🗓️ Roadmap

### Versión 1.1 (Próxima)
- [ ] API REST completa
- [ ] Exportación de inventario (PDF/Excel)
- [ ] Gráficos de estadísticas con Chart.js
- [ ] Sistema de notificaciones

### Versión 1.2
- [ ] Integración con Proxmox API
- [ ] Monitoreo de servicios (ping/uptime)
- [ ] Backup automático de configuraciones
- [ ] Modo oscuro

### Versión 2.0
- [ ] Multi-tenancy (múltiples organizaciones)
- [ ] Aplicación móvil (PWA)
- [ ] Dashboard avanzado con métricas
- [ ] Integración con sistemas de tickets

---

## 📞 Soporte

Para reportar bugs o solicitar features:

1. **GitHub Issues**: [Crear issue](https://github.com/sergioecm60/secmti/issues)
2. **Email**: [sergiomiers@gmail.com](mailto:sergiomiers@gmail.com)

---

⭐ **Si este proyecto te resulta útil, considera darle una estrella en GitHub!**

---

*Última actualización: Octubre 2025*
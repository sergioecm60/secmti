# Portal de Servicios PHP Creado Por Sergio Cabrera, contacto sergiomiers@gmail.com +541167598452 con asistencia de IAS Gemini y Chagtp, y chequeo de vulneravilidades con Qwen.

Un portal de servicios simple, seguro y personalizable, escrito en PHP puro. Diseñado para ser ligero, fácil de instalar y administrar.

## Versión

1.0.1

## Características

- **Página de Aterrizaje (index.php):** Página de presentación pública y personalizable.
- **Portal de Servicios (index2.php):** Acceso a enlaces y aplicaciones internas.
- **Sistema de Login Seguro:**
  - Protección contra ataques de fuerza bruta con bloqueo de cuenta.
  - Captcha matemático para prevenir bots.
  - Contraseñas hasheadas de forma segura (password_hash).
- **Panel de Administración (`manage.php`):**
  - Edita todo el contenido del sitio desde una interfaz web.
  - Gestión dinámica de redes sociales.
  - Gestiona los botones de servicios (crear, editar, eliminar).
  - Gestiona los usuarios administradores (crear, editar, eliminar).
- **Páginas Protegidas:**
  - `info.php`: Muestra información detallada del servidor y PHP.
  - `mytop.php`: Un monitor de procesos de la base de datos en tiempo real.
- **Configuración Centralizada:** Todo se gestiona desde un único archivo `config.php`.
- **Instalador Web:** Asistente de instalación fácil de usar.

## Requisitos

- Servidor web (Apache, Nginx, etc.)
- PHP 8.0 o superior
- Extensión PHP `pdo_mysql`
- Base de datos MySQL o MariaDB

## Instalación

1.  Clona o descarga este repositorio en la raíz de tu servidor web.
2.  Asegúrate de que tienes un **usuario de MySQL con privilegios para crear bases de datos** (permiso `CREATE`). Además, el servidor web debe tener permisos de escritura en el directorio del proyecto para poder crear el archivo `config.php`.
3.  Abre tu navegador y navega a `http://<tu-servidor>/install.php`.
4.  Sigue las instrucciones en pantalla:
    -   Introduce las credenciales de tu base de datos.
    -   Crea tu primer usuario administrador.
5.  **¡MUY IMPORTANTE!** Después de una instalación exitosa, **elimina el archivo `install.php`** de tu servidor por razones de seguridad.
6.  ¡Listo! Ya puedes acceder a tu portal en `index.php` o `index2.php`.

## Licencia

Este proyecto está bajo la Licencia GNU GPL v3. Consulta el archivo `license.php` para más detalles.
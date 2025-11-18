# Plan de Mejoras para el Proyecto SECMTI

Este documento describe una serie de mejoras propuestas para aumentar la seguridad, mantenibilidad y calidad general del código del proyecto SECMTI.

## 1. Mejoras de Seguridad (Prioridad Alta)

### 1.1. Modernizar el Cifrado de Credenciales

*   **Problema:** El sistema utiliza `AES-256-CBC` para cifrar las contraseñas almacenadas. Aunque es un algoritmo fuerte, CBC por sí solo no protege la integridad del texto cifrado. Es vulnerable a ataques de "chosen-ciphertext" (como el Padding Oracle Attack) si no se utiliza junto con un Código de Autenticación de Mensaje (MAC).
*   **Solución Propuesta:**
    1.  **Adoptar un Cifrado Autenticado (AEAD):** Reemplazar `AES-256-CBC` con `AES-256-GCM`. GCM es un modo de operación que proporciona cifrado y autenticación en un solo paso, lo que previene la manipulación del texto cifrado.
    2.  **Alternativa (Encrypt-then-MAC):** Si se prefiere mantener CBC, se debe implementar un esquema "Encrypt-then-MAC". Esto implica generar un HMAC (usando `hash_hmac`) del texto cifrado (incluyendo el IV) y almacenarlo junto con el cifrado. Al descifrar, se debe verificar el HMAC antes de intentar el descifrado.
*   **Impacto:** Muy alto. Mejora significativamente la seguridad de las credenciales almacenadas, protegiéndolas contra manipulación.

### 1.2. Estandarizar el Manejo de Contraseñas de Usuario

*   **Problema:** Las contraseñas de los usuarios para iniciar sesión en el portal deben ser hasheadas, no cifradas. El cifrado es reversible; el hasheo no lo es. Si la `APP_ENCRYPTION_KEY` se ve comprometida, todas las contraseñas cifradas pueden ser descifradas.
*   **Solución Propuesta:**
    1.  Utilizar la función `password_hash()` de PHP con el algoritmo `PASSWORD_ARGON2ID` o `PASSWORD_BCRYPT` para almacenar las contraseñas de los usuarios del portal.
    2.  Utilizar `password_verify()` para comprobar las contraseñas durante el inicio de sesión.
    3.  Crear un script de migración para hashear de forma segura las contraseñas de los usuarios existentes.
*   **Impacto:** Crítico. Es la práctica estándar de la industria para el almacenamiento de credenciales de autenticación.

## 2. Refactorización y Calidad de Código (Prioridad Media)

### 2.1. Unificar el Sistema de Cifrado

*   **Problema:** Existen dos implementaciones de cifrado: las funciones globales `encrypt_password`/`decrypt_password` en `bootstrap.php` y la clase `SecMTI\Util\Encryption`. Esto crea redundancia, confusión y dificulta el mantenimiento.
*   **Solución Propuesta:**
    1.  Eliminar las funciones globales de `bootstrap.php`.
    2.  Refactorizar todo el código que las utiliza (`hosting_manager.php`, etc.) para que instancie y use la clase `SecMTI\Util\Encryption`.
    3.  Aplicar la mejora de cifrado (AES-GCM o Encrypt-then-MAC) directamente en la clase `Encryption`.
*   **Impacto:** Alto. Centraliza la lógica, reduce la duplicación de código y facilita futuras actualizaciones de seguridad.

### 2.2. Encapsular la Lógica de `bootstrap.php`

*   **Problema:** El archivo `bootstrap.php` contiene muchas funciones globales. Aunque funcional, esto contamina el espacio de nombres global y se aleja de un enfoque orientado a objetos que el proyecto ya utiliza parcialmente con PSR-4.
*   **Solución Propuesta:**
    1.  Crear clases dentro del namespace `SecMTI` para manejar responsabilidades específicas. Por ejemplo:
        *   `SecMTI\Core\SessionManager`: Para toda la lógica de inicio, validación y regeneración de sesiones.
        *   `SecMTI\Core\SecurityHeaders`: Para la configuración y envío de cabeceras de seguridad.
        *   `SecMTI\Core\RateLimiter`: Para la lógica de limitación de peticiones.
    2.  Refactorizar `bootstrap.php` para que sea un archivo de "arranque" más limpio que simplemente inicialice y llame a estas clases.
*   **Impacto:** Medio. Mejora la estructura, mantenibilidad y legibilidad del código.

### 2.3. Implementar un Sistema de Plantillas (Templating)

*   **Problema:** Los archivos PHP mezclan lógica de negocio (consultas a la BD, manejo de POST) con la presentación (HTML). Esto dificulta el diseño y el mantenimiento.
*   **Solución Propuesta:**
    1.  Separar la lógica de la presentación. En cada script PHP, realizar todo el procesamiento de datos primero y luego pasar los datos a un archivo de "vista" separado que solo contenga HTML y variables PHP simples.
    2.  **Ejemplo:** En `hosting_manager.php`, toda la lógica `POST` y la carga de datos se mantiene al principio. Al final, en lugar de escribir HTML, se hace un `require 'templates/hosting_manager_view.php';` y se le pasan los datos necesarios en variables.
*   **Impacto:** Medio. Mejora la organización del código y facilita la colaboración entre desarrollo de backend y frontend.

## 3. Mejoras de Frontend (Prioridad Baja)

### 3.1. Centralizar y Minimizar Assets

*   **Problema:** Cada página parece cargar sus propios archivos CSS y JS. No hay un proceso de compilación o minimización.
*   **Solución Propuesta:**
    1.  Implementar una herramienta de compilación de frontend como [Vite](https://vitejs.dev/) o [Webpack](https://webpack.js.org/).
    2.  Utilizarla para combinar y minimizar los archivos JavaScript y CSS en un único archivo (o unos pocos) para producción. Esto reduce el número de peticiones HTTP y mejora el tiempo de carga.
*   **Impacto:** Bajo. Mejora el rendimiento del frontend.

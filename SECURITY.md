# Política de Seguridad

Gracias por interesarte en la seguridad del Portal secmti.  
Valoramos cualquier reporte de vulnerabilidades.

## 📬 Reportar una Vulnerabilidad

Por favor, **no abras un issue público**.  
Envía un correo detallado a: [sergiomiers@gmail.com](mailto:sergiomiers@gmail.com)

Incluye:
- Descripción clara del problema
- Pasos para reproducir
- Impacto esperado
- Versión del software
- Entorno (PHP, servidor, etc.)

## ⏳ Tiempo de Respuesta

- Respuesta inicial: máximo 48 horas
- Validación y corrección: entre 1 y 7 días (según criticidad)
- Agradecimiento público (opcional): sí, si lo deseas

## ✅ Prácticas de Seguridad

- Contraseñas hasheadas con `password_hash()`
- Bloqueo de intentos fallidos
- Captcha anti-bots
- Sesiones seguras
- Archivo `install.php` debe eliminarse tras instalación
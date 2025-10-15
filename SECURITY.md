# Política de Seguridad

Gracias por interesarte en la seguridad del Portal SECMTI.  
Valoramos cualquier reporte de vulnerabilidades y nos comprometemos a abordarlas de manera responsable.

---

## 🔐 Versiones Soportadas

| Versión | Soportada          | Notas                                    |
| ------- | ------------------ | ---------------------------------------- |
| 1.0.1   | ✅ Sí             | Versión actual con mejoras de seguridad  |
| 1.0.0   | ⚠️ Limitado       | Actualizar a 1.0.1 lo antes posible      |
| < 1.0   | ❌ No             | No recibe actualizaciones de seguridad   |

---

## 📬 Reportar una Vulnerabilidad

Por favor, **no abras un issue público** para vulnerabilidades de seguridad.

### Proceso de Reporte

1. **Envía un correo detallado** a: [sergiomiers@gmail.com](mailto:sergiomiers@gmail.com)
2. **Asunto del email**: `[SECURITY] Descripción breve de la vulnerabilidad`

### Información a Incluir

Para ayudarnos a resolver el problema rápidamente, incluye:

- ✅ **Descripción clara** del problema de seguridad
- ✅ **Pasos detallados** para reproducir la vulnerabilidad
- ✅ **Impacto esperado** (qué puede hacer un atacante)
- ✅ **Versión del software** afectada
- ✅ **Entorno** (PHP, servidor web, navegador)
- ✅ **Prueba de concepto** (PoC) si es posible
- ✅ **Capturas de pantalla** si son relevantes

### Ejemplo de Reporte

```
Asunto: [SECURITY] SQL Injection en módulo de búsqueda

Versión: 1.0.0
Severidad: Alta

Descripción:
He encontrado una vulnerabilidad de SQL Injection en el módulo de búsqueda 
que permite a un atacante extraer información sensible de la base de datos.

Pasos para reproducir:
1. Ir a http://ejemplo.com/secmti/datacenter_view.php
2. En el campo de búsqueda, ingresar: ' OR 1=1 --
3. El sistema devuelve todos los registros sin filtrar

Impacto:
Un atacante podría extraer credenciales cifradas, información de servidores 
y datos de usuarios.

Entorno:
- SECMTI v1.0.0
- PHP 8.1
- Apache 2.4
- MySQL 8.0
```

---

## ⏳ Tiempo de Respuesta

Nos comprometemos a:

| Fase                          | Tiempo Estimado      |
|-------------------------------|----------------------|
| **Respuesta inicial**         | Máximo 48 horas      |
| **Evaluación de severidad**   | 3-5 días             |
| **Desarrollo de parche**      | 1-14 días (según criticidad) |
| **Lanzamiento de actualización** | Según ciclo de release |
| **Divulgación pública**       | 30 días después del parche |

### Clasificación de Severidad

- **🔴 Crítica**: Explotable remotamente, sin autenticación, alto impacto (< 7 días)
- **🟠 Alta**: Requiere autenticación, impacto significativo (< 14 días)
- **🟡 Media**: Requiere condiciones específicas, impacto moderado (< 30 días)
- **🟢 Baja**: Difícil explotación, impacto limitado (siguiente release)

---

## 🎁 Programa de Reconocimiento

### Hall of Fame

Agradecemos públicamente a los investigadores de seguridad que contribuyen responsablemente:

**2025**
- *Próximamente...*

### Recompensas

Aunque actualmente no ofrecemos recompensas monetarias, sí ofrecemos:

- ✨ **Reconocimiento público** en el archivo SECURITY.md
- 📜 **Certificado digital** de reconocimiento
- 🎖️ **Insignia especial** en el README del proyecto
- 🔗 **Link a tu perfil** (GitHub, LinkedIn, sitio web)

---

## ✅ Prácticas de Seguridad Implementadas

### Autenticación y Autorización

✅ **Contraseñas de login**
- Hasheadas con `password_hash()` usando bcrypt
- Costo de hashing: 12
- Sin límite de longitud (recomendado 12+ caracteres)

✅ **Contraseñas sensibles en BD**
- Cifrado AES-256-CBC
- Clave de cifrado única por instalación en `.env`
- Rotación de clave recomendada anualmente

✅ **Protección contra fuerza bruta**
- Bloqueo de cuenta tras 5 intentos fallidos
- Captcha matemático anti-bots
- Registro de intentos en logs

✅ **Gestión de sesiones**
- Regeneración de ID tras login exitoso
- Timeout automático a los 30 minutos
- Cookies con flags `HttpOnly`, `Secure`, `SameSite=Strict`

### Protección de Datos

✅ **SQL Injection**
- Prepared statements con PDO en todas las queries
- Sin concatenación de strings en SQL
- Validación de tipos de datos

✅ **Cross-Site Scripting (XSS)**
- `htmlspecialchars(ENT_QUOTES, 'UTF-8')` en todas las salidas
- Content Security Policy (CSP) estricto
- Sin `innerHTML` con datos no sanitizados
- Sin event handlers inline (`onclick`, etc.)

✅ **Cross-Site Request Forgery (CSRF)**
- Tokens únicos en todos los formularios
- Validación en servidor de tokens
- Tokens en headers de peticiones AJAX
- Regeneración de tokens por sesión

✅ **Content Security Policy (CSP)**
```
default-src 'self';
script-src 'self' 'nonce-{random}';
style-src 'self';
img-src 'self' data:;
```

### Headers de Seguridad

```
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Strict-Transport-Security: max-age=31536000; includeSubDomains
```

### Auditoría y Logs

✅ **Registro de actividad**
- Tabla `dc_access_log` con todos los accesos
- Campos: usuario, acción, IP, timestamp
- Retención: configurable (por defecto 90 días)

✅ **Logs de error**
- Archivo `logs/error.log`
- Sin información sensible en logs
- Rotación automática recomendada

### Validación de Datos

✅ **Server-side**
- Validación en PHP de todos los inputs
- Sanitización antes de guardar en BD
- Verificación de tipos y rangos

✅ **Client-side**
- Validación HTML5 nativa
- JavaScript solo como complemento
- Mensajes de error claros

### Cifrado

✅ **HTTPS**
- Recomendado en producción
- Certificado SSL/TLS válido
- Redireccionamiento HTTP → HTTPS

✅ **Cifrado de datos sensibles**
- AES-256-CBC para contraseñas
- IV único por cada cifrado
- Clave en variable de entorno

---

## 🚫 Vulnerabilidades Conocidas (Mitigadas)

### Resueltas en v1.0.1

1. **Inline Styles/Scripts** (CSP Bypass)
   - **Descripción**: Posibilidad de inyectar código malicioso via estilos/scripts inline
   - **Solución**: Eliminados todos los inline styles/scripts, CSP estricto con nonces
   - **Severidad**: Media

2. **Contraseñas en texto plano en respuestas API**
   - **Descripción**: API devolvía contraseñas cifradas sin descifrar
   - **Solución**: Implementado descifrado correcto en endpoints
   - **Severidad**: Alta

3. **Validación inconsistente en formularios**
   - **Descripción**: Algunos campos no se validaban correctamente
   - **Solución**: Sistema de validación unificado con HTML5 + JavaScript
   - **Severidad**: Baja

---

## 🔒 Recomendaciones para Usuarios

### Configuración Segura

#### 1. Durante la Instalación

```bash
# Generar clave de cifrado fuerte
php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"

# Proteger archivo .env
chmod 600 .env
chown www-data:www-data .env
```

#### 2. Después de la Instalación

- [ ] Cambiar contraseña de usuario `admin` inmediatamente
- [ ] Eliminar o renombrar `install.php` si existe
- [ ] Revisar permisos de archivos (755 para directorios, 644 para archivos)
- [ ] Configurar backup automático de base de datos
- [ ] Habilitar logs de acceso

#### 3. En Producción

```apache
# .htaccess - Bloquear archivos sensibles
<FilesMatch "\.(env|git|sql|md|log)$">
    Require all denied
</FilesMatch>

# Bloquear acceso a directorios
Options -Indexes
```

```bash
# Firewall - Solo puertos necesarios
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 22/tcp
sudo ufw enable
```

#### 4. Mantenimiento Regular

- 🔄 **Actualizar sistema operativo** semanalmente
- 🔄 **Actualizar PHP y MySQL** mensualmente
- 🔄 **Revisar logs** semanalmente
- 🔄 **Backup de BD** diariamente
- 🔄 **Rotar logs** mensualmente
- 🔄 **Auditoría de usuarios** trimestralmente

### Contraseñas Seguras

Recomendaciones para contraseñas:

- ✅ Mínimo 12 caracteres
- ✅ Combinar mayúsculas, minúsculas, números y símbolos
- ✅ Usar un gestor de contraseñas (1Password, Bitwarden, KeePass)
- ✅ Cambiar cada 90 días las contraseñas críticas
- ❌ No reutilizar contraseñas entre sistemas
- ❌ No usar información personal (fechas, nombres)

---

## 🛡️ Hardening Adicional

### PHP Configuration

```ini
; php.ini - Configuración de seguridad

; Ocultar versión de PHP
expose_php = Off

; Deshabilitar funciones peligrosas
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source

; Logs
display_errors = Off
log_errors = On
error_log = /var/www/secmti/logs/php_errors.log

; Sesiones
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = Strict
session.use_strict_mode = 1

; Subida de archivos
file_uploads = Off  ; Si no se necesita
upload_max_filesize = 2M
max_file_uploads = 3

; Límites
memory_limit = 128M
max_execution_time = 30
max_input_time = 30
```

### MySQL Hardening

```sql
-- Eliminar usuarios anónimos
DELETE FROM mysql.user WHERE User='';

-- Eliminar base de datos de test
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';

-- Restringir acceso remoto de root
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');

-- Aplicar cambios
FLUSH PRIVILEGES;
```

### Firewall Application (ModSecurity)

```apache
# Instalar ModSecurity
sudo apt install libapache2-mod-security2

# Activar reglas OWASP Core Rule Set
sudo cp /etc/modsecurity/modsecurity.conf-recommended /etc/modsecurity/modsecurity.conf
sudo sed -i 's/SecRuleEngine DetectionOnly/SecRuleEngine On/' /etc/modsecurity/modsecurity.conf
```

---

## 📚 Recursos de Seguridad

### Para Desarrolladores

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html)
- [MySQL Security Best Practices](https://dev.mysql.com/doc/refman/8.0/en/security-guidelines.html)
- [Content Security Policy Guide](https://content-security-policy.com/)

### Para Administradores

- [Linux Server Security Checklist](https://www.codelitt.com/blog/my-first-10-minutes-on-a-server-primer-for-securing-ubuntu/)
- [Apache Security Tips](https://httpd.apache.org/docs/2.4/misc/security_tips.html)
- [Let's Encrypt - HTTPS gratuito](https://letsencrypt.org/)

### Herramientas de Testing

- [OWASP ZAP](https://www.zaproxy.org/) - Escáner de vulnerabilidades web
- [SQLMap](https://sqlmap.org/) - Testing de SQL Injection
- [Nikto](https://github.com/sullo/nikto) - Escáner de servidores web
- [Burp Suite](https://portswigger.net/burp) - Testing de seguridad web

---

## 📋 Checklist de Seguridad

### Pre-Producción

- [ ] Cambiar todas las contraseñas por defecto
- [ ] Generar clave de cifrado única
- [ ] Configurar HTTPS con certificado válido
- [ ] Habilitar firewall
- [ ] Configurar backup automático
- [ ] Revisar permisos de archivos
- [ ] Deshabilitar mensajes de error en pantalla
- [ ] Configurar logs
- [ ] Testing de vulnerabilidades básicas

### Post-Despliegue

- [ ] Monitorear logs diariamente (primera semana)
- [ ] Verificar que HTTPS funcione correctamente
- [ ] Probar recuperación de backup
- [ ] Documentar configuración de seguridad
- [ ] Capacitar a usuarios sobre seguridad

### Mantenimiento Continuo

- [ ] Revisar logs de error semanalmente
- [ ] Actualizar sistema mensualmente
- [ ] Auditoría de usuarios trimestralmente
- [ ] Pruebas de penetración anualmente
- [ ] Revisión de políticas de seguridad anualmente

---

## 🔔 Suscríbete a Alertas de Seguridad

Para recibir notificaciones de actualizaciones de seguridad:

1. **Watch** el repositorio en GitHub
2. Configura notificaciones para releases
3. Sígueme en [LinkedIn](https://www.linkedin.com/in/sergio-cabrera-miers-71a22615/)

---

## 📞 Contacto de Seguridad

**Email principal**: [sergiomiers@gmail.com](mailto:sergiomiers@gmail.com) 
**GitHub Security Advisories**: [Ver advisories](https://github.com/sergioecm60/secmti/security/advisories)

---

## 📜 Política de Divulgación Responsable

Seguimos el modelo de **divulgación coordinada**:

1. **Reporte privado** → Investigador contacta al desarrollador
2. **Validación** → Confirmamos la vulnerabilidad
3. **Desarrollo** → Creamos y testeamos el parche
4. **Release** → Lanzamos actualización de seguridad
5. **Divulgación** → 30 días después del parche, se hace pública

Durante el período de embargo:
- ✅ Mantenemos comunicación con el reportero
- ✅ Proporcionamos actualizaciones del progreso
- ✅ Coordinamos la fecha de divulgación pública
- ❌ No compartimos detalles con terceros sin consentimiento

---

## 🏆 Agradecimientos Especiales

Gracias a estos proyectos de código abierto que hacen posible la seguridad de SECMTI:

- **PHP** - Lenguaje de programación
- **PDO** - Capa de abstracción de base de datos
- **OpenSSL** - Biblioteca de cifrado
- **OWASP** - Recursos y guías de seguridad
- **Let's Encrypt** - Certificados SSL/TLS gratuitos

---

*Esta política fue actualizada por última vez el: Octubre 2025*  
*Versión del documento: 1.0.1*

---

**🔐 La seguridad es responsabilidad de todos. Gracias por ayudarnos a mantener SECMTI seguro.**
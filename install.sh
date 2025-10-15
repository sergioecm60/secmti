#!/bin/bash
# install.sh - Script de instalación interactivo para el Portal SECMTI
#
# Este script automatiza la configuración inicial del proyecto después de
# haber sido clonado desde Git.
#
# Uso:
# 1. Clona el repositorio: git clone https://github.com/sergioecm60/secmti.git
# 2. Entra al directorio: cd secmti
# 3. Ejecuta el script: sudo bash install.sh

set -e # Salir inmediatamente si un comando falla.

# --- Colores para la salida ---
C_RESET='\033[0m'
C_RED='\033[0;31m'
C_GREEN='\033[0;32m'
C_YELLOW='\033[0;33m'
C_BLUE='\033[0;34m'
C_BOLD='\033[1m'

echo -e "${C_BLUE}${C_BOLD}🚀 Iniciando la instalación del Portal SECMTI...${C_RESET}"

# --- 1. Verificaciones iniciales ---
echo -e "\n${C_BLUE}1. Verificando requisitos del sistema...${C_RESET}"

if [ "$EUID" -ne 0 ]; then
  echo -e "${C_RED}❌ Error: Este script debe ser ejecutado con privilegios de root (sudo).${C_RESET}"
  exit 1
fi

if ! command -v php &> /dev/null || ! php -r "exit(PHP_VERSION_ID >= 80000 ? 0 : 1);"; then
    echo -e "${C_RED}❌ Error: PHP 8.0 o superior es requerido.${C_RESET}"
    read -p "¿Deseas intentar instalar PHP y las extensiones necesarias? (s/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Ss]$ ]]; then
        if command -v apt-get &> /dev/null; then
            echo -e "${C_YELLOW}Instalando PHP y extensiones para Debian/Ubuntu...${C_RESET}"
            apt-get update
            apt-get install -y php php-mysql php-mbstring php-xml php-json php-curl php-openssl
        elif command -v yum &> /dev/null; then
            echo -e "${C_YELLOW}Instalando PHP y extensiones para CentOS/RHEL...${C_RESET}"
            yum install -y php php-mysqlnd php-mbstring php-xml php-json php-curl php-openssl
        else
            echo -e "${C_YELLOW}No se pudo detectar un gestor de paquetes compatible (apt/yum). Por favor, instala PHP 8.0+ manualmente.${C_RESET}"
            exit 1
        fi
    else
        exit 1
    fi
fi
echo -e "${C_GREEN}✅ PHP 8.0+ detectado.${C_RESET}"

# Verificar extensiones PHP requeridas
REQUIRED_EXTENSIONS=("pdo_mysql" "mbstring" "openssl" "json" "curl" "xml")
echo -e "${C_YELLOW}Verificando extensiones PHP...${C_RESET}"
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if ! php -m | grep -qi "^${ext}$"; then
        echo -e "${C_RED}❌ Error: La extensión de PHP '${ext}' es requerida y no está instalada/habilitada.${C_RESET}"
        echo -e "${C_YELLOW}Por favor, instálala (ej: sudo apt install php-${ext}) y vuelve a ejecutar el script.${C_RESET}"
        exit 1
    fi
done
echo -e "${C_GREEN}✅ Todas las extensiones PHP requeridas están presentes.${C_RESET}"

if ! command -v composer &> /dev/null; then
    echo -e "${C_YELLOW}⚠️ Composer no está instalado.${C_RESET}"
    read -p "¿Deseas descargarlo e instalarlo globalmente? (s/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Ss]$ ]]; then
        EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
        php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
        ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
        if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
            echo -e "${C_RED}❌ Error: La firma del instalador de Composer es inválida. Abortando.${C_RESET}"
            rm composer-setup.php
            exit 1
        fi
        php composer-setup.php --install-dir=/usr/local/bin --filename=composer
        rm composer-setup.php
    else
        echo -e "${C_RED}Composer es necesario para continuar. Abortando.${C_RESET}"
        exit 1
    fi
fi
echo -e "${C_GREEN}✅ Composer detectado.${C_RESET}"

if ! command -v mysql &> /dev/null; then
    echo -e "${C_YELLOW}⚠️ Cliente MySQL/MariaDB no detectado. La configuración de la base de datos deberá hacerse manualmente.${C_RESET}"
fi

# --- 2. Configuración del entorno (.env) ---
echo -e "\n${C_BLUE}2. Configurando el archivo de entorno (.env)...${C_RESET}"
PROJECT_DIR=$(pwd)

if [ ! -f ".env" ]; then
    cp .env.example .env
    echo -e "${C_GREEN}✅ Archivo .env creado desde .env.example.${C_RESET}"
else
    echo -e "${C_YELLOW}⚠️ El archivo .env ya existe. Omitiendo creación.${C_RESET}"
fi

# Generar y establecer la clave de encriptación
ENCRYPTION_KEY=$(php -r "echo base64_encode(random_bytes(32));")
sed -i "s|^APP_ENCRYPTION_KEY=.*|APP_ENCRYPTION_KEY=${ENCRYPTION_KEY}|" .env
echo -e "${C_GREEN}✅ Clave de encriptación única generada y guardada en .env.${C_RESET}"

# Pedir URL de la aplicación
echo -e "\n${C_YELLOW}Introduce la URL base de la aplicación (ej: http://portal.miempresa.com):${C_RESET}"
read -p "URL de la aplicación: " APP_URL
sed -i "s|^APP_URL=.*|APP_URL=\"${APP_URL}\"|" .env
echo -e "${C_GREEN}✅ URL de la aplicación guardada en .env.${C_RESET}"

# Pedir datos de la base de datos
echo -e "\n${C_YELLOW}Por favor, introduce los datos de tu base de datos:${C_RESET}"
read -p "Nombre de la base de datos [portal_db]: " DB_NAME
DB_NAME=${DB_NAME:-portal_db}
read -p "Usuario de la base de datos [secmti_user]: " DB_USER
DB_USER=${DB_USER:-secmti_user}
read -s -p "Contraseña de la base de datos: " DB_PASS
echo

sed -i "s/^DB_NAME=.*/DB_NAME=${DB_NAME}/" .env
sed -i "s/^DB_USER=.*/DB_USER=${DB_USER}/" .env
sed -i "s/^DB_PASS=.*/DB_PASS=\"${DB_PASS}\"/" .env
echo -e "${C_GREEN}✅ Credenciales de la base de datos guardadas en .env.${C_RESET}"

# --- 3. Instalación de dependencias de Composer ---
echo -e "\n${C_BLUE}3. Instalando dependencias de PHP con Composer...${C_RESET}"
composer install --no-dev --optimize-autoloader

# --- 4. Configuración de la Base de Datos ---
echo -e "\n${C_BLUE}4. Configurando la base de datos...${C_RESET}"

if command -v mysql &> /dev/null; then
    read -p "¿Deseas que el script intente crear la base de datos '${DB_NAME}' y el usuario '${DB_USER}'? (s/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Ss]$ ]]; then
        echo -e "${C_YELLOW}Introduce la contraseña de root de MySQL/MariaDB para crear la base de datos y el usuario:${C_RESET}"
        mysql -u root -p <<EOF
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
        echo -e "${C_GREEN}✅ Base de datos y usuario configurados.${C_RESET}"
    fi

    echo -e "\n${C_YELLOW}Importando el esquema de la base de datos desde 'database/install.sql'...${C_RESET}"
    mysql -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" < database/install.sql
    echo -e "${C_GREEN}✅ Esquema de la base de datos importado con éxito.${C_RESET}"
else
    echo -e "${C_YELLOW}Cliente MySQL no encontrado. Por favor, crea la base de datos e importa 'database/install.sql' manualmente.${C_RESET}"
fi

# --- 5. Asignación de Permisos ---
echo -e "\n${C_BLUE}5. Asignando permisos de archivos y directorios...${C_RESET}"

WEB_USER="www-data" # Default para Debian/Ubuntu
if ! id -u $WEB_USER > /dev/null 2>&1; then
    WEB_USER="apache" # Default para CentOS/RHEL
fi

if ! id -u $WEB_USER > /dev/null 2>&1; then
    echo -e "${C_YELLOW}⚠️ No se pudo determinar el usuario del servidor web (www-data o apache). Usando el usuario actual.${C_RESET}"
    WEB_USER=$(whoami)
fi

chown -R "${WEB_USER}":"${WEB_USER}" "${PROJECT_DIR}"
find "${PROJECT_DIR}" -type d -exec chmod 755 {} \;
find "${PROJECT_DIR}" -type f -exec chmod 644 {} \;

# Permisos especiales
chmod 600 "${PROJECT_DIR}/.env"
if [ -d "${PROJECT_DIR}/logs" ]; then
    chmod -R 775 "${PROJECT_DIR}/logs"
fi

echo -e "${C_GREEN}✅ Permisos asignados correctamente.${C_RESET}"

# --- 6. Verificación Final ---
echo -e "\n${C_BLUE}6. Verificando la instalación...${C_RESET}"
if [ -f "test_db.php" ]; then
    echo -e "${C_YELLOW}Ejecutando test de conexión a la base de datos...${C_RESET}"
    # Ejecutar el script de test y capturar la salida.
    # Usamos '|| true' para que el script no termine si test_db.php falla.
    TEST_OUTPUT=$(php test_db.php || true)
    if echo "${TEST_OUTPUT}" | grep -q "¡Conexión exitosa!"; then
        echo -e "${C_GREEN}✅ Test de base de datos superado con éxito.${C_RESET}"
    else
        echo -e "${C_RED}❌ El test de conexión a la base de datos ha fallado. Revisa la salida:${C_RESET}"
        # Mostramos la salida del test sin las etiquetas HTML para mejor legibilidad en consola.
        echo -e "${C_YELLOW}$(echo "${TEST_OUTPUT}" | sed -e 's/<[^>]*>//g' | sed '/^\s*$/d')${C_RESET}"
        echo -e "${C_RED}La instalación puede no haber sido exitosa. Revisa los datos en .env y los permisos.${C_RESET}"
    fi
else
    echo -e "${C_YELLOW}⚠️ No se encontró 'test_db.php'. Omitiendo verificación final.${C_RESET}"
fi

# --- 7. Finalización ---
echo -e "\n\n${C_GREEN}${C_BOLD}🎉 ¡Instalación completada! 🎉${C_RESET}"
echo -e "\n${C_YELLOW}Pasos siguientes recomendados:${C_RESET}"
echo -e "1. Configura tu servidor web (Apache/Nginx) para que apunte a: ${C_BOLD}${PROJECT_DIR}${C_RESET}"
echo -e "2. Accede al portal en tu navegador."
echo -e "3. El usuario por defecto es ${C_BOLD}admin${C_RESET} con contraseña ${C_BOLD}12345678${C_RESET}. ¡Cámbiala inmediatamente!"

echo -e "\n${C_RED}${C_BOLD}🚨 ¡IMPORTANTE! Por seguridad, elimina este script de instalación ahora:${C_RESET}"
echo -e "${C_BOLD}   rm ${PROJECT_DIR}/install.sh${C_RESET}"

exit 0

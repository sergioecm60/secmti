#!/bin/bash
# install.sh - Instalador automático para secmti
# Uso: sudo ./install.sh [dominio]

set -e  # Salir si hay errores

# Verificar si se ejecuta como root
if [ "$EUID" -ne 0 ]; then
  echo "❌ Por favor, ejecuta este script con sudo: sudo ./install.sh"
  exit 1
fi

WEB_ROOT="/var/www/html"
REPO_URL="https://github.com/sergioecm60/secmti.git"
PROJECT_DIR="$WEB_ROOT/secmti"
DOMAIN="${1:-localhost}"

echo "🚀 Instalando Portal secmti..."

# Verificar requisitos
command -v git >/dev/null 2>&1 || { echo "❌ git no está instalado"; exit 1; }
command -v php >/dev/null 2>&1 || { echo "❌ PHP no está instalado"; exit 1; }
php -r "exit(PHP_VERSION_ID < 80000 ? 1 : 0);" 2>/dev/null || { echo "❌ PHP 8.0+ requerido"; exit 1; }

# Clonar repositorio
if [ ! -d "$PROJECT_DIR" ]; then
    echo "📦 Clonando repositorio..."
    git clone "$REPO_URL" "$PROJECT_DIR"
else
    echo "✅ Directorio ya existe, omitiendo clonado."
fi

# Permisos
echo "🔧 Asignando permisos..."
chown -R www-data:www-data "$PROJECT_DIR"
chmod -R 755 "$PROJECT_DIR"

# Mostrar siguiente paso
echo ""
echo "✅ Instalación básica completada."
echo "🌐 Accede ahora al instalador web:"
echo "   http://$DOMAIN/secmti/install.php"
echo ""
echo "⚠️  Después de instalar, ELIMINA el instalador:"
echo "   rm $PROJECT_DIR/install.php"

exit 0
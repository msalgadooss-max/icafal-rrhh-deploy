#!/bin/bash
set -e

DATADIR=/var/lib/mysql
PORT="${PORT:-8000}"

# --- 1) Inicializa MariaDB si el datadir esta vacio (disco efimero: -----
#         esto corre de nuevo en cada arranque del contenedor en Render) --
if [ ! -d "$DATADIR/mysql" ]; then
  echo "[entrypoint] Inicializando MariaDB..."
  mariadb-install-db --user=mysql --datadir="$DATADIR" --skip-test-db > /dev/null
fi

echo "[entrypoint] Iniciando MariaDB..."
mysqld_safe --datadir="$DATADIR" --skip-networking=0 --bind-address=127.0.0.1 &

# --- 2) Espera a que acepte conexiones (por el socket local) -------------
for i in $(seq 1 30); do
  if mysqladmin ping --silent 2>/dev/null; then
    break
  fi
  sleep 1
done

# --- 2.5) MariaDB deja a root autenticado SOLO por socket local por -----
#           defecto -- el propio PHP (PDO, via TCP a 127.0.0.1) y los
#           comandos de abajo necesitan una cuenta que acepte conexion
#           por red. Esto se hace una sola vez, por el socket local
#           (donde root SI puede entrar sin clave).
mysql -uroot <<'SQL'
ALTER USER 'root'@'localhost' IDENTIFIED BY '';
CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL
echo "[entrypoint] Cuenta root habilitada para conexiones TCP."

# --- 3) Crea el esquema + datos semilla si la base aun no existe --------
if ! mysql -h127.0.0.1 -uroot -e "USE icafal_rrhh" 2>/dev/null; then
  echo "[entrypoint] Cargando schema.sql + seed_deploy.sql..."
  mysql -h127.0.0.1 -uroot --default-character-set=utf8mb4 < /app/database/schema.sql
  mysql -h127.0.0.1 -uroot --default-character-set=utf8mb4 < /app/database/seed_deploy.sql
fi

# --- 4) Genera config.php desde variables de entorno (nunca desde git) --
cat > /app/backend/config/config.php <<PHP
<?php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'icafal_rrhh');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('BASE_URL', '${BASE_URL}');

define('SMTP_HOST', '${SMTP_HOST}');
define('SMTP_PORT', ${SMTP_PORT:-587});
define('SMTP_USER', '${SMTP_USER}');
define('SMTP_PASS', '${SMTP_PASS}');
define('SMTP_SECURE', '${SMTP_SECURE:-tls}');
define('SMTP_FROM_EMAIL', '${SMTP_FROM_EMAIL}');
define('SMTP_FROM_NAME', 'RRHH ICAFAL');

// v6.3: si esta definida, el correo sale por la API HTTPS de Brevo en
// vez de SMTP (necesario en hostings que bloquean los puertos salientes).
define('BREVO_API_KEY', '${BREVO_API_KEY}');

define('TOKEN_PRIVADO_HORAS_VALIDEZ', 72);
define('TOKEN_SUBSANACION_HORAS_VALIDEZ', 72);
define('CODIGO_SEGUIMIENTO_LARGO', 6);
define('BANCO_RETENCION_MESES', 6);

define('OBRA_NOMBRE', 'Obra H57 Padre Hurtado IV');
define('LIMITE_APROBACIONES_DIARIAS_TERRENO', 25);

define('MODULO_PREVENCION_ACTIVO', false);
define('MODULO_BODEGA_ACTIVO', false);

define('SESSION_NAME', 'icafal_rrhh_sesion');
define('APP_DEBUG', false);
PHP

mkdir -p /app/backend/uploads /app/backend/carpetas_postulantes

echo "[entrypoint] Arrancando PHP en el puerto $PORT..."
# v6.6: la imagen php:8.3-cli no trae un php.ini activo, asi que sin esto
# rigen los limites hardcodeados de PHP (upload_max_filesize=2M,
# post_max_size=8M) -- un CV o una foto de celular de mas de 2MB fallaba
# la subida ANTES de que nuestro propio limite de 8MB por archivo llegara
# a evaluarse ("No fue posible recibir tu CV. Intenta nuevamente."). La
# Etapa 2 ademas sube hasta 6 documentos en un solo POST, por eso
# post_max_size va bastante mas holgado que un solo archivo.
exec php \
  -d upload_max_filesize=10M \
  -d post_max_size=60M \
  -d max_file_uploads=20 \
  -d memory_limit=256M \
  -d max_execution_time=60 \
  -S 0.0.0.0:"$PORT" -t /app

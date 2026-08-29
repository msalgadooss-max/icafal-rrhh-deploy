# Imagen unica: PHP + Apache (servidor de PRODUCCION, no el embebido de
# desarrollo) + MariaDB en el mismo contenedor. Pensada para un demo/piloto
# en el plan gratuito de Render -- el disco es efimero (se reinicia con cada
# deploy/reinicio), asi que la base de datos se re-siembra sola cada vez que
# arranca (ver docker-entrypoint.sh).
#
# v6.8: antes usaba "php -S" (el servidor embebido de PHP), que PHP mismo
# advierte que NO esta pensado para produccion: atiende una peticion a la
# vez, sin importar cuantas lleguen al mismo tiempo. Se confirmo con una
# prueba de carga real (10/30/60 peticiones simultaneas -> tiempo total
# crecia exactamente lineal, sin paralelismo real). Apache con mpm_prefork
# sí atiende varias peticiones EN PARALELO, con un numero de workers
# acotado (ver mpm_prefork.conf mas abajo) para no quedarse sin memoria
# compitiendo con MariaDB en el mismo contenedor chico del plan gratuito.
FROM php:8.3-apache-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        default-mysql-server default-mysql-client \
        libpng-dev libjpeg-dev libfreetype6-dev libzip-dev unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd zip pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# El DocumentRoot de la imagen oficial de Apache es /var/www/html; lo
# apuntamos a /app (donde vive el proyecto) en vez de mover archivos.
RUN sed -i 's#/var/www/html#/app#g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf

# Mismos limites de subida que antes (ver docker-entrypoint.sh viejo),
# ahora como conf.d en vez de flags de "php -S" -- aplican igual con
# Apache/mod_php. La Etapa 2 sube hasta 6 documentos en un solo POST, por
# eso post_max_size va bastante mas holgado que un solo archivo.
RUN { \
        echo 'upload_max_filesize=10M'; \
        echo 'post_max_size=60M'; \
        echo 'max_file_uploads=20'; \
        echo 'memory_limit=256M'; \
        echo 'max_execution_time=60'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

# mpm_prefork acotado: suficiente paralelismo real para no volver a la fila
# de a uno (ver v6.8 arriba), pero sin arriesgarse a quedar sin memoria en
# el plan gratuito, que comparte RAM con MariaDB en el mismo contenedor.
RUN { \
        echo '<IfModule mpm_prefork_module>'; \
        echo '    StartServers 3'; \
        echo '    MinSpareServers 2'; \
        echo '    MaxSpareServers 6'; \
        echo '    MaxRequestWorkers 15'; \
        echo '    MaxConnectionsPerChild 200'; \
        echo '</IfModule>'; \
    } > /etc/apache2/mods-available/mpm_prefork.conf

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

# Dependencias PHP (vendor/ nunca se sube al repo, se instala en la build)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# v6.8: con Apache, PHP corre como el usuario "www-data" (antes, con
# "php -S", corria como root) -- www-data necesita permiso de escritura en
# las carpetas donde el propio codigo PHP crea archivos en tiempo de
# ejecucion (subidas de documentos, carpetas por postulante).
RUN chmod +x /app/docker-entrypoint.sh \
    && mkdir -p /var/lib/mysql /run/mysqld /app/backend/uploads /app/backend/carpetas_postulantes \
    && chown -R mysql:mysql /var/lib/mysql /run/mysqld \
    && chown -R www-data:www-data /app/backend/uploads /app/backend/carpetas_postulantes

EXPOSE 8000
ENTRYPOINT ["/app/docker-entrypoint.sh"]

# Imagen unica: PHP (servidor embebido) + MariaDB en el mismo contenedor.
# Pensada para un demo/piloto en el plan gratuito de Render -- el disco
# es efimero (se reinicia con cada deploy/reinicio), asi que la base de
# datos se re-siembra sola cada vez que arranca (ver docker-entrypoint.sh).
FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        default-mysql-server default-mysql-client \
        libpng-dev libjpeg-dev libfreetype6-dev libzip-dev unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd zip pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

# Dependencias PHP (vendor/ nunca se sube al repo, se instala en la build)
RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN chmod +x /app/docker-entrypoint.sh \
    && mkdir -p /var/lib/mysql /run/mysqld \
    && chown -R mysql:mysql /var/lib/mysql /run/mysqld

EXPOSE 8000
ENTRYPOINT ["/app/docker-entrypoint.sh"]

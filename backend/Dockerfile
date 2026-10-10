# Imagen de desarrollo del backend Laravel (PHP 8.4 CLI).
# Referencia: docs/architecture/deployment-architecture.md, seccion 3.
FROM php:8.4-cli

# Instalo las extensiones que el proyecto necesita y las utilidades que
# requiere Composer. Uso --no-install-recommends para mantener la imagen ligera,
# porque el equipo local tiene memoria ajustada (riesgo R-10 del acta).
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        default-mysql-client \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring bcmath intl zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Reutilizo el binario oficial de Composer en vez de descargarlo en cada build.
# Uso el nombre completo del registro porque Buildah (Podman) no resuelve
# nombres cortos en una instruccion COPY --from; con "composer:2" falla.
COPY --from=docker.io/library/composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copio primero los manifiestos para aprovechar la cache de capas de Docker:
# mientras no cambien las dependencias, no se reinstala todo.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-progress

# Copio el resto del codigo de la aplicacion.
COPY . .

EXPOSE 8000

# En desarrollo sirvo la API con el servidor embebido de Laravel; en ensayo se
# usaria PHP-FPM tras un servidor web (ver deployment-architecture.md).
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

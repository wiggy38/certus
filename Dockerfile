# ============================================================
# Certus — PHP 8.3-FPM production image
# ============================================================
FROM php:8.3-fpm

# --- Dépendances système ----------------------------------------
RUN apt-get update && apt-get install -y \
        nginx \
        git \
        curl \
        unzip \
        libzip-dev \
        libonig-dev \
        libxml2-dev \
        libpng-dev \
        libicu-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# --- Extensions PHP ---------------------------------------------
RUN docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        ctype \
        bcmath \
        zip \
        xml \
        intl \
        opcache

# --- Composer ---------------------------------------------------
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# --- Nginx config -----------------------------------------------
COPY docker/nginx.conf /etc/nginx/sites-available/default

# --- OPcache production -----------------------------------------
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.fast_shutdown=1'; \
} > /usr/local/etc/php/conf.d/opcache-prod.ini

# --- Application ------------------------------------------------
WORKDIR /var/www/certus

COPY . .

RUN composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --no-progress \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# --- Entrypoint -------------------------------------------------
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

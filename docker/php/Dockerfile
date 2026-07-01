FROM php:8.2-apache

# Laravel runtime dependencies
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        unzip \
        git \
        curl \
    && docker-php-ext-install \
        pdo_mysql \
        intl \
        zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Install Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Point Apache to Laravel public directory
ENV APACHE_DOCUMENT_ROOT=/var/www/html/laravel_app/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Reduce request queueing under prefork by shortening keep-alive hold time
# and allowing more workers for concurrent in-app API hops.
RUN sed -ri 's/^KeepAliveTimeout\s+.*/KeepAliveTimeout 1/' /etc/apache2/apache2.conf \
    && sed -ri 's/^StartServers\s+.*/StartServers 10/' /etc/apache2/mods-available/mpm_prefork.conf \
    && sed -ri 's/^MinSpareServers\s+.*/MinSpareServers 10/' /etc/apache2/mods-available/mpm_prefork.conf \
    && sed -ri 's/^MaxSpareServers\s+.*/MaxSpareServers 20/' /etc/apache2/mods-available/mpm_prefork.conf \
    && sed -ri 's/^MaxRequestWorkers\s+.*/MaxRequestWorkers 200/' /etc/apache2/mods-available/mpm_prefork.conf \
    && sed -ri 's/^MaxConnectionsPerChild\s+.*/MaxConnectionsPerChild 2000/' /etc/apache2/mods-available/mpm_prefork.conf

# Improve Laravel bootstrap performance on Docker Desktop bind mounts.
RUN { \
            echo 'opcache.enable=1'; \
            echo 'opcache.validate_timestamps=0'; \
            echo 'opcache.revalidate_freq=0'; \
            echo 'opcache.max_accelerated_files=50000'; \
            echo 'opcache.memory_consumption=192'; \
            echo 'realpath_cache_size=4096K'; \
            echo 'realpath_cache_ttl=600'; \
        } > /usr/local/etc/php/conf.d/99-performance.ini

# Allow .htaccess overrides for Laravel routing
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

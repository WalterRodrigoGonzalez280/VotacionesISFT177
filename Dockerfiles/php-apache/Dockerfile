# =============================================================================
# PHP 8.4 + Apache — Desarrollo
# =============================================================================
FROM php:8.4-apache

# Extensiones: pdo + pdo_pgsql
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends libpq-dev; \
    rm -rf /var/lib/apt/lists/*; \
    docker-php-ext-install -j"$(nproc)" pdo pdo_pgsql; \
    rm -rf /var/tmp/*

# OPcache
RUN set -eux; \
    ls /usr/local/lib/php/extensions/*/opcache.so; \
    docker-php-ext-enable opcache

# php.ini — configuración del proyecto
RUN { \
      echo 'date.timezone = America/Argentina/Buenos_Aires'; \
      echo 'session.cookie_lifetime = 86400'; \
      echo 'session.gc_maxlifetime = 86400'; \
      echo ''; \
      echo '; --- OPcache ---'; \
      echo 'opcache.enable = 1'; \
      echo 'opcache.memory_consumption = 128'; \
      echo 'opcache.interned_strings_buffer = 16'; \
      echo 'opcache.max_accelerated_files = 10000'; \
      echo 'opcache.max_wasted_percentage = 10'; \
      echo 'opcache.validate_timestamps = 1'; \
      echo 'opcache.revalidate_freq = 2'; \
      echo 'opcache.enable_cli = 0'; \
    } > /usr/local/etc/php/php.ini

# Cabeceras de caché de Apache
COPY cache.conf /etc/apache2/conf-available/isft177-cache.conf
RUN set -eux; \
    a2enmod headers; \
    a2enconf isft177-cache; \
    test -e /etc/apache2/mods-enabled/headers.load; \
    test -e /etc/apache2/conf-enabled/isft177-cache.conf; \
    apache2ctl configtest
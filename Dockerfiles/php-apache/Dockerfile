FROM php:7.4-apache

# Extensiones que el proyecto necesita: `pdo` (viene en la imagen, se deja) y
# `pdo_pgsql`, que es la que usa conexion/conexion.php para abrir la conexión
# con PDO contra Postgres.
#
# `mysqli` y `pdo_mysql` se fueron con la migración a Postgres: no los usaba
# nadie. Un grep del proyecto entero no encuentra un solo `mysqli`, y todo el
# SQL pasa por PDO, asi que eran dos lineas de build que compilan una libreria
# para nada.
#
# pdo_pgsql compila contra libpq, que no viene en la imagen. La base
# php:7.4-apache es Debian 10 (buster), EOL y RETIRADO de deb.debian.org, asi
# que cualquier `apt-get update` falla con exit code 100 (ver README, seccion
# "Build"). La salida es apuntar sources.list a archive.debian.org, que es
# donde queda buster archived.
#
# Se reescribe sources.list entero en vez de sed: el contenido del archivo de
# la imagen base cambia entre tags y esta forma no depende de eso. Solo va
# `main`, porque libpq-dev esta ahi y `buster-updates` NO existe en
# archive.debian.org: dejarla apuntando a un repo inexistente hace fallar el
# update con 404 otra vez, que es el error que venia a impedir hacer esto.
RUN set -eux; \
    printf 'deb http://archive.debian.org/debian buster main\n' > /etc/apt/sources.list; \
    apt-get update; \
    apt-get install -y --no-install-recommends libpq-dev; \
    rm -rf /var/lib/apt/lists/*; \
    docker-php-ext-install -j"$(nproc)" pdo pdo_pgsql; \
    rm -rf /var/tmp/*

# ---------------------------------------------------------------------------
# OPcache
# ---------------------------------------------------------------------------
# Sin esto, cada request vuelve a compilar el PHP a opcode y a tirarlo. Con
# esto el opcode queda en memoria compartida entre requests, que es el mayor
# ahorro de CPU que se puede hacer sin tocar el código de la app.
#
# opcache.so viene incluido en la imagen oficial pero no siempre habilitado, así
# que se habilita explícitamente. `docker-php-ext-enable` es idempotente: si ya
# estaba, solo reescribe el .ini de conf.d. El test previo da un error claro en
# lugar de un "Unable to load dynamic library" en cada request.
RUN set -eux; \
    ls /usr/local/lib/php/extensions/*/opcache.so; \
    docker-php-ext-enable opcache

# La imagen oficial no trae php.ini (solo php.ini-development / -production),
# por eso se genera acá con los valores que exige el proyecto.
#
# Sobre los valores de OPcache:
# - validate_timestamps=1 con revalidate_freq=2 porque en desarrollo el código
#   está montado desde ./www y los cambios tienen que verse enseguida. En un
#   entorno de producción se pondría validate_timestamps=0 (más rápido todavía,
#   pero exige reiniciar PHP tras cada despliegue porque los cambios ya no se
#   detectan solos).
# - max_accelerated_files es el número de scripts que caben en la caché. El
#   proyecto tiene unas decenas de archivos, así que 10000 es de sobra; si
#   Apache llegara al tope, opcache deja de cachear y avisa en el log.
# - enable_cli=0 porque no hay benefit en CLI y solo consumiría memoria.
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

# ---------------------------------------------------------------------------
# Cabeceras de caché de Apache
# ---------------------------------------------------------------------------
# La imagen `php:7.4-apache` instala el PAQUETE `apache2` de Debian, no una
# compilación desde fuente. Por eso la config vive en /etc/apache2/ y no en
# /usr/local/apache2/ (que no existe: el `Server` lo delata como
# "Apache/2.4.54 (Debian)").
#
# mod_headers es lo que emite Cache-Control, y se habilita con `a2enmod`, que
# crea el symlink en /etc/apache2/mods-enabled/ que apache2.conf incluye. El
# archivo de cache.conf se deja en conf-available/ y `a2enconf` lo enlaza en
# conf-enabled/, que también está incluido. No hace falta tocar apache2.conf a
# mano, y no hay que acordarse del `Include`: lo hacen los dos helpers.
#
# Los `test -e` son la aserción. a2enmod/a2enconf son idempotentes (si ya está
# activo, lo dicen y salen con 0), así que sin ellos un módulo que no estuviera
# en la imagen pasaría el build en silencio y la app serviría todo sin cachear
# sin avisar. Con ellos, build rojo.
#
# `apache2ctl configtest` valida la config ya ARMADA, con el symlink puesto: sin
# esto, una sintaxis mal escrita en cache.conf no se descubre hasta que Apache
# no arranca y el contenedor entra en loop de reinicio. Equivale a `apachectl -t`
# y devuelve exit != 0 si algo no cierra.
#
# Ojo: los comentarios van AFUERA del RUN. Dentro de un comando con continuaciones
# `\`, un `#` se come el resto de la línea unida y la instrucción se pierde.
COPY cache.conf /etc/apache2/conf-available/isft177-cache.conf
RUN set -eux; \
    a2enmod headers; \
    a2enconf isft177-cache; \
    test -e /etc/apache2/mods-enabled/headers.load; \
    test -e /etc/apache2/conf-enabled/isft177-cache.conf; \
    apache2ctl configtest

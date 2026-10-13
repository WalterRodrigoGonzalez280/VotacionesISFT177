FROM postgres:9.5.25-alpine

# ---------------------------------------------------------------------------
# tzdata
# ---------------------------------------------------------------------------
# Alpine no trae la base de zonas horarias: son los ~430 KB del paquete tzdata,
# que se instala aparte. Postgres las necesita para resolver un nombre de zona,
# y conexion/conexion.php hace exactamente eso en CADA conexion:
#
#     SET TIME ZONE 'America/Argentina/Buenos_Aires'
#
# Sin el paquete, ese SET falla, la funcion fijarZonaHoraria() cae al respaldo
# de '-03:00', y el sitio sigue andando pero con el horario de verano corrido
# una hora (Argentina es UTC-2 de octubre a marzo). El `apk add` es idempotente:
# si la imagen base ya lo trajera, no cambia nada.
#
# Para que la zona se pueda resolver hace falta ademas /usr/share/zoneinfo, que
# es lo que instala este paquete.
RUN apk add --no-cache tzdata

# ---------------------------------------------------------------------------
# Zona horaria del contenedor
# ---------------------------------------------------------------------------
# La app fija la zona por sesion (arriba), asi que esto NO es lo que hace que
# las fechas salgan bien: es para que las herramientas de mantenimiento que se
# conectan sin fijar sesion - psql a pelo, adminer, un `docker compose exec db
# psql` - no muestren UTC. musl lee TZ del entorno y lo busca en
# /usr/share/zoneinfo, que es lo que instalamos arriba.
ENV TZ=America/Argentina/Buenos_Aires

# ---------------------------------------------------------------------------
# Alcance: por que 9.5
# ---------------------------------------------------------------------------
# PostgreSQL 9.5 quedo fuera de soporte en noviembre de 2021 y esta rama no
# recibe mas parches, ni de seguridad. MySQL 5.7, que es de donde venia el
# proyecto, quedo en octubre de 2023: migrar de uno a otro deja la base MAS
# vieja, no menos.
#
# No es una decision que se pueda revertir desde el Dockerfile, asi que queda
# escrita acá y en el README: si algun dia hay margen para elegir motor,
# cualquier version 12 o posterior evita casi todas las limitaciones que
# condicionaron el diseno del schema (IDENTITY en vez de SERIAL, collations con
# provider ICU en vez de depender de citext, y un pdo_pgsql cuyos booleanos se
# comportan de forma predecible).
#
# Lo que NO se negocia es que el sitio tenga la zona horaria correcta: por eso
# el tzdata de arriba es parte del build y no un extra.

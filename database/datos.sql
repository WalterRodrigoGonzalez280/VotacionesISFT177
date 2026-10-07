-- ---------------------------------------------------------------------------
-- Datos que venir del MySQL, mas el avance de las secuencias.
--
-- Corre despues de 01-schema.sql, solo en el arranque en frio del volumen.
--
-- OJO: este archivo tiene los hashes de password de los usuarios. Los hashes
-- no son el password (no se pueden leer), pero si alcanzan para attacking
-- offline y para probar el mismo password en otros lados. El .env y el dump
-- crudo SI tienen secretos de verdad y estan fuera del repo; despues de
-- verificar la migracion conviene borrar este archivo y el mount que lo carga
-- de docker-compose.yml.
--
-- Proviene del dump que se sacaste de MySQL con
-- `SET time_zone = "+00:00"`, asi que TODO lo que era TIMESTAMP va en UTC.
-- ---------------------------------------------------------------------------

SET TIME ZONE 'UTC';


-- ---------------------------------------------------------------------------
-- anio_votacion
-- ---------------------------------------------------------------------------
-- El año de la edicion. Solo la fila 5 esta activa: es la que lee
-- anioVotacion() con `ORDER BY id DESC LIMIT 1`, o sea 2026. Las otras cuatro
-- son el historial de los intentos que se guardaron desde el panel; las 1, 3
-- y 5 tienen 2026 y la 4 tiene 2027 porque se cambio a mitad de camino.
INSERT INTO anio_votacion (id, anio, activo, creado_en) VALUES
  (1, 2026, 0, '2026-09-28 17:18:15'),
  (2, 2031, 0, '2026-09-28 17:22:08'),
  (3, 2026, 0, '2026-09-28 17:22:08'),
  (4, 2027, 0, '2026-09-28 22:13:35'),
  (5, 2026, 1, '2026-09-28 22:13:41');


-- ---------------------------------------------------------------------------
-- configuracion_votacion
-- ---------------------------------------------------------------------------
-- Solo la fila 7 esta activa: del 2026-09-20 al 2026-09-30 23:59. Las seis
-- anteriores son el historial de cambios de la ventana.
--
-- Estas dos columnas son `timestamp` SIN zona, asi que lo que va aca es el
-- reloj de pared y se guarda tal cual, sin convertir por el `SET TIME ZONE`
-- de arriba. Eso es lo correcto: el admin escribio "2026-09-20 00:00:00"
-- queriendo decir las 00:00 de Argentina.
INSERT INTO configuracion_votacion (id, inicio_votacion, fin_votacion, activo, creado_en) VALUES
  (1, '2026-01-01 00:00:00', '2099-12-31 23:59:59', 0, '2026-09-27 16:22:24'),
  (2, '2026-01-01 00:00:00', '2026-12-31 23:59:00', 0, '2026-09-27 19:05:59'),
  (3, '2026-09-27 00:00:00', '2026-12-31 23:59:00', 0, '2026-09-27 19:06:10'),
  (4, '2026-09-20 00:00:00', '2026-09-26 23:59:00', 0, '2026-09-27 22:24:38'),
  (5, '2026-09-20 00:00:00', '2026-09-30 23:59:00', 0, '2026-09-27 22:44:46'),
  (6, '2026-09-20 00:00:00', '2026-09-30 23:59:00', 0, '2026-09-28 22:13:35'),
  (7, '2026-09-20 00:00:00', '2026-09-30 23:59:00', 1, '2026-09-28 22:13:41');


-- ---------------------------------------------------------------------------
-- proyectos
-- ---------------------------------------------------------------------------
-- Los 9 que se votan, 3 por anio. Todos activos.
INSERT INTO proyectos (id, anio_id, nombre, activo) VALUES
  (13, 1, 'Grupo A - Tienda Online',           1),
  (14, 1, 'Grupo B - App de Turnos Médicos',   1),
  (15, 1, 'Grupo C - Control de Stock',        1),
  (16, 2, 'Grupo D - Blog de Noticias',        1),
  (17, 2, 'Grupo E - Portafolio Personal',     1),
  (18, 2, 'Grupo F - Enciclopedia Jurídica',    1),
  (19, 3, 'Grupo G - Sistema de Reservas',     1),
  (20, 3, 'Grupo H - Dashboard de Ventas',     1),
  (21, 3, 'Grupo I - Gestor de Tareas',        1);


-- ---------------------------------------------------------------------------
-- usuarios
-- ---------------------------------------------------------------------------
-- Los hashes bcrypt se copiaron tal cual: `password_verify()` no depende del
-- motor, asi que las cuentas entran igual que antes de la migracion.
--
-- Falta el id 2 (se borro en algun momento), por eso hace falta setval mas
-- abajo: la secuencia arranca en 1 y el primer INSERT que venga choca contra
-- la primary key.
--
-- Ojo con `es_admin` vs `rol_id`: aca no coinciden. Los usuarios 1 y 3 tienen
-- rol_id = 1 y es_admin = 0. login.php decide por `rol_id` y api.php lee
-- `es_admin`, asi que la columna no dice quien es admin: es el bug 4 del
-- README, sigue abierto, y la migracion lo arrastra tal cual. No se "arregla"
-- aca a proposito, porque cambiar quien es admin es una decision del negocio
-- y no una conversion de formato.
INSERT INTO usuarios (id, nombre, email, password, token, activo, rol_id, es_admin, votos_1er_ano, votos_2do_ano, votos_3er_ano, fecha_registro) VALUES
  (1, 'rodrigo',     'rodrigogonzalez29494@gmail.com', '$2y$10$na5ZbKII/KThrzQYOdmfEufD8ThoZMlbcHi6ppv1sh2.TGeEODUTa', NULL, 1, 1, 0, 0, 0, 0, '2026-09-27 16:32:51'),
  (3, 'rodrigo',     'rg371733@gmail.com',             '$2y$10$4OIBBEbh2aVApiuqhjJpxeVDQlzMZSZc4jOPJLuhBOKh3VgBJ3V9K', NULL, 1, 1, 0, 0, 0, 0, '2026-09-27 16:54:35'),
  (4, 'Temporal',    'temp2@check.local',              '$2b$10$TR2nwhxXH.2QPzUsW12/u.eBN7McLIcgWPi1JPWhwE7PXGBo8vlRO', NULL, 1, 1, 1, 0, 0, 0, '2026-09-27 17:26:05'),
  (5, 'Temporal',    'nav@check.local',                '$2b$10$1xjYV6pc3PtTmbsk0gXv2uw9dV3WkrCkE/ljG0QCRYRTWTS32PbT.', NULL, 1, 1, 1, 0, 0, 0, '2026-09-27 17:56:04'),
  (6, 'Votante',     'votante@check.local',            '$2b$10$UVVXMG5lZoxFL5ySfxDq4eorAm0YmFCTiRME5SGY/cthsTk5rwZPa', NULL, 1, 2, 0, 0, 0, 0, '2026-09-27 18:25:28'),
  (7, 'Admin',       'adminv@check.local',             '$2b$10$9UyUARgtWZ2aqtm95EQl8OZknW8H2CTVZM8ih2iJ3i9jbfmU9qKhO', NULL, 1, 1, 1, 0, 0, 0, '2026-09-27 18:25:28'),
  (8, 'rodrigoxd',   'rg37156@gmail.com',              '$2y$10$l1ta2Kh1feM5ONENdWXJQOJ/ekgfVWc44XEi3i7H5CAoW/yRTwRre', NULL, 1, 2, 0, 0, 0, 0, '2026-09-27 20:04:38');


-- ---------------------------------------------------------------------------
-- votos
-- ---------------------------------------------------------------------------
-- El dump no tenia ninguno, asi que no hay INSERT. La tabla queda vacia pero
-- existe: es la que votacion.php y api.php leen para contar, y la que tiene el
-- UNIQUE (usuario_id, anio_id) que impide el voto doble.
--
-- Para empezar de verdad, se insertan a mano desde el sitio: la votacion es la
-- que arma esta tabla.


-- ---------------------------------------------------------------------------
-- Secuencias
-- ---------------------------------------------------------------------------
-- Imprescindible. `serial` crea la secuencia arrancando en 1, y los INSERT de
-- arriba van con id explicito, asi que la secuencia nunca se entero. Sin esto
-- el primer usuario que se registre recibe id 1, choca contra la primary key y
-- el registro falla con "duplicate key value" sin que se entienda porque.
--
-- El tercer argumento en false deja la secuencia en "todavia no usada", para
-- que el proximo nextval devuelva exactamente COALESCE(MAX(id), 0) + 1 y no
-- salte un numero.
--
-- pg_get_serial_sequence() se usa en vez del nombre a mano ('tabla_id_seq') para
-- que el nombre no quede desincronizado si alguna vez se renombra algo.
SELECT setval(pg_get_serial_sequence('roles', 'id'),                    COALESCE((SELECT MAX(id) FROM roles),                    0) + 1, false);
SELECT setval(pg_get_serial_sequence('anios', 'id'),                    COALESCE((SELECT MAX(id) FROM anios),                    0) + 1, false);
SELECT setval(pg_get_serial_sequence('email_config', 'id'),             COALESCE((SELECT MAX(id) FROM email_config),             0) + 1, false);
SELECT setval(pg_get_serial_sequence('anio_votacion', 'id'),            COALESCE((SELECT MAX(id) FROM anio_votacion),            0) + 1, false);
SELECT setval(pg_get_serial_sequence('configuracion_votacion', 'id'),   COALESCE((SELECT MAX(id) FROM configuracion_votacion),   0) + 1, false);
SELECT setval(pg_get_serial_sequence('proyectos', 'id'),                COALESCE((SELECT MAX(id) FROM proyectos),                0) + 1, false);
SELECT setval(pg_get_serial_sequence('usuarios', 'id'),                 COALESCE((SELECT MAX(id) FROM usuarios),                 0) + 1, false);
SELECT setval(pg_get_serial_sequence('votos', 'id'),                    COALESCE((SELECT MAX(id) FROM votos),                    0) + 1, false);


-- ---------------------------------------------------------------------------
-- Verificacion
-- ---------------------------------------------------------------------------
-- Salen por el log del contenedor (`docker compose logs db`). Los numeros
-- tienen que coincidir con los del dump de MySQL.
--
--   anios 3 | anio_votacion 5 | configuracion_votacion 7 | email_config 6
--   proyectos 9 | usuarios 7 | votos 0
SELECT 'roles' t, COUNT(*) n FROM roles
UNION ALL SELECT 'anios', COUNT(*) FROM anios
UNION ALL SELECT 'anio_votacion', COUNT(*) FROM anio_votacion
UNION ALL SELECT 'configuracion_votacion', COUNT(*) FROM configuracion_votacion
UNION ALL SELECT 'email_config', COUNT(*) FROM email_config
UNION ALL SELECT 'proyectos', COUNT(*) FROM proyectos
UNION ALL SELECT 'usuarios', COUNT(*) FROM usuarios
UNION ALL SELECT 'votos', COUNT(*) FROM votos;

-- Lo que tiene que devolver: 2026.
SELECT anio FROM anio_votacion WHERE activo = 1 ORDER BY id DESC LIMIT 1;

-- Ventana activa. Las dos columnas son reloj de pared, asi que se ven tal
-- cual, sin conversion: si uviera que multiplicar por 3 la diferencia, el
-- mapeo de timestamp vs timestamptz quedo al reves.
SELECT inicio_votacion, fin_votacion FROM configuracion_votacion
 WHERE activo = 1 ORDER BY id DESC LIMIT 1;

-- La zona de la sesion. Tiene que decir America/Argentina/Buenos_Aires: si
-- dice Etc/UTC, el tzdata no se instalo en la imagen y la app va a caer al
-- respaldo de '-03:00'.
SHOW TIME ZONE;

-- ---------------------------------------------------------------------------
-- Schema de la base de votacion, para PostgreSQL 9.5.
--
-- Corre solo la primera vez, cuando el volumen pgdata esta vacio (ver
-- docker-compose.yml). Postgres lo ejecuta con ON_ERROR_STOP=1: si algo de
-- aca esta mal, el arranque del contenedor falla en vez de dejar una base a
-- medias que despues nadie entiende. Para recargarlo a mano ver README.md.
--
-- No lleva DROP TABLE a proposito: es un archivo de schema, no un reset. Si
-- lo que se quiere es empezar de cero, se borra el volumen
-- (`docker compose down -v`) y se vuelve a levantar.
-- ---------------------------------------------------------------------------

-- citext es lo que hace que `usuarios.email` se compare sin distinguir
-- mayusculas. MySQL lo tenia gratis con `utf8mb4_unicode_ci`, que es
-- case-insensitive; Postgres con el default NO lo es, asi que sin esto
-- `Juan@mail.com` y `juan@mail.com` entran como dos cuentas distintas y el
-- UNIQUE no frena nada. Con citext el UNIQUE y el `WHERE email = ?` de
-- login.php vuelven a comportarse como antes.
--
-- Lo que citext NO hace es ignorar acentos, asi que `jose@` y `josé@` si se
-- tratan como cuentas distintas. Con utf8mb4_unicode_ci no. Es una diferencia
-- que queda, y para correo de Gmail no llega a importar.
--
-- Si `CREATE EXTENSION` falla, es que la imagen no trae contrib:
-- `apk add postgresql-contrib` en Dockerfiles/postgres/Dockerfile.
CREATE EXTENSION IF NOT EXISTS citext;


-- ---------------------------------------------------------------------------
-- Tablas de referencia
-- ---------------------------------------------------------------------------

CREATE TABLE roles (
  id     serial PRIMARY KEY,
  nombre varchar(50) NOT NULL,
  CONSTRAINT uq_roles_nombre UNIQUE (nombre)
);

CREATE TABLE anios (
  id     serial PRIMARY KEY,
  nombre varchar(100) NOT NULL,
  CONSTRAINT uq_anios_nombre UNIQUE (nombre)
);

-- `nombre` guarda '1er Año', '2do Año', '3er Año': el VALOR lleva ñ, el
-- IDENTIFICADOR no. Esa distinción es la del bug 6 del README y no se toca
-- nunca mas. El valor viaja como dato, no como nombre de objeto, asi que la ñ
-- no estorba.


-- ---------------------------------------------------------------------------
-- anio_votacion: el año de la edicion
-- ---------------------------------------------------------------------------
-- NO es la tabla `anios`, que tiene los años de cursada (1.o, 2.o, 3.o).
-- Esta guarda en que año se vota, y se cambia desde la pestana Configuracion
-- del panel. De las filas, una sola tiene activo = 1; anioVotacion() en
-- conexion/conexion.php la elige con `ORDER BY id DESC LIMIT 1`.
CREATE TABLE anio_votacion (
  id        serial PRIMARY KEY,
  anio      smallint NOT NULL,
  activo    smallint NOT NULL DEFAULT 0,
  -- MySQL lo declaraba TIMESTAMP, que es un instante guardado en UTC y
  -- convertido con la zona de la sesion. timestamptz es exactamente eso, asi
  -- que el cambio conserva el valor. Ver la nota de timestamps al final.
  creado_en timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX idx_anio_votacion_activo ON anio_votacion (activo, id);


-- ---------------------------------------------------------------------------
-- configuracion_votacion: la ventana en la que se puede votar
-- ---------------------------------------------------------------------------
CREATE TABLE configuracion_votacion (
  id serial PRIMARY KEY,

  -- ACASO son el unico lugar donde NO va timestamptz. En MySQL eran DATETIME,
  -- que no es un instante sino un reloj de pared: lo que la persona admin
  -- tipea en un <input type="datetime-local">. Se compara en PHP contra
  -- new DateTime(), que tambien es horario de Argentina, asi que tiene que
  -- seguir siendo un reloj de pared: si fuera timestamptz, el instante
  -- dependeria de en que momento se lo cargo y la ventana se moveria.
  inicio_votacion timestamp NOT NULL,
  fin_votacion    timestamp NOT NULL,

  activo    smallint NOT NULL DEFAULT 0,
  creado_en timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX idx_cfg_activo_id ON configuracion_votacion (activo, id);


-- ---------------------------------------------------------------------------
-- email_config: los ajustes de SMTP
-- ---------------------------------------------------------------------------
CREATE TABLE email_config (
  id     serial PRIMARY KEY,
  clave  varchar(100) NOT NULL,
  valor  varchar(255),
  activo smallint NOT NULL DEFAULT 1,
  CONSTRAINT uq_email_config_clave UNIQUE (clave)
);


-- ---------------------------------------------------------------------------
-- proyectos: lo que se vota
-- ---------------------------------------------------------------------------
-- Antes se llamaba `subgrupos` y `grupos` era la tabla de los años. Los
-- nombres mentian sobre el dominio; renombrados el 2026-09-27.
CREATE TABLE proyectos (
  id      serial PRIMARY KEY,
  anio_id integer NOT NULL,
  nombre  varchar(150) NOT NULL,
  activo  smallint NOT NULL DEFAULT 1,
  CONSTRAINT fk_proyectos_anio FOREIGN KEY (anio_id) REFERENCES anios (id) ON UPDATE CASCADE
);

CREATE INDEX idx_proyectos_anio_activo ON proyectos (anio_id, activo);


-- ---------------------------------------------------------------------------
-- usuarios
-- ---------------------------------------------------------------------------
CREATE TABLE usuarios (
  id    serial PRIMARY KEY,
  nombre varchar(50) NOT NULL,
  email citext NOT NULL,
  password varchar(255),
  -- Era CHAR(4) en MySQL. En Postgres el CHAR es un tipo "blank padded" que
  -- mete espacios a la derecha y complica las comparaciones; como el token
  -- siempre son 4 digitos (str_pad en generar_token.php), varchar(4) es
  -- equivalente y sin sorpresas.
  token varchar(4),

  -- active, es_admin y los tres votos_* son SMALLINT y no boolean. Es a
  -- proposito: pdo_pgsql no siempre devuelve los booleanos como boolean de
  -- PHP sino como los strings 't' y 'f', y en JavaScript un string no vacio
  -- es truthy, asi que un `if (data.es_admin)` con 'f' dentro le muestra el
  -- panel de admin a cualquiera. Con smallint viaja 0 o 1, igual que antes de
  -- la migracion, y votacion.js y admin.js no necesitan un cambio.
  activo smallint NOT NULL DEFAULT 0,

  rol_id integer NOT NULL DEFAULT 2,
  es_admin smallint NOT NULL DEFAULT 0,

  votos_1er_ano smallint NOT NULL DEFAULT 0,
  votos_2do_ano smallint NOT NULL DEFAULT 0,
  votos_3er_ano smallint NOT NULL DEFAULT 0,

  fecha_registro timestamptz NOT NULL DEFAULT now(),

  CONSTRAINT uq_usuarios_email UNIQUE (email),
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles (id) ON UPDATE CASCADE
);

CREATE INDEX idx_usuarios_rol ON usuarios (rol_id);


-- ---------------------------------------------------------------------------
-- votos
-- ---------------------------------------------------------------------------
CREATE TABLE votos (
  id          serial PRIMARY KEY,
  usuario_id  integer NOT NULL,
  proyecto_id integer NOT NULL,
  anio_id     integer NOT NULL,
  fecha_voto  timestamptz NOT NULL DEFAULT now(),

  -- El UNIQUE es lo que hace imposible votar dos veces en el mismo año. La app
  -- tambien lo chequea con los flags votos_*_ano antes de insertar, pero eso es
  -- un leer-y-despues-escribir: dos peticiones simultaneas pueden pasar las dos
  -- la comprobacion. Este UNIQUE no se puede esquivar, porque lo aplica la base.
  CONSTRAINT uq_votos_usuario_anio UNIQUE (usuario_id, anio_id),
  CONSTRAINT fk_votos_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios  (id) ON DELETE CASCADE,
  CONSTRAINT fk_votos_proyecto FOREIGN KEY (proyecto_id) REFERENCES proyectos (id) ON DELETE CASCADE,
  CONSTRAINT fk_votos_anio     FOREIGN KEY (anio_id)     REFERENCES anios     (id) ON UPDATE CASCADE
);

CREATE INDEX idx_votos_proyecto ON votos (proyecto_id);
CREATE INDEX idx_votos_anio     ON votos (anio_id);


-- ---------------------------------------------------------------------------
-- Datos de referencia
-- ---------------------------------------------------------------------------
-- Los que el sitio no puede arrancar sin: los tres años, los dos roles, y las
-- claves de SMTP con su valor. MAIL_PASSWORD va en NULL a proposito, y el
-- motivo esta arriba del archivo datos.sql: el dump de MySQL traia la password
-- de verdad en texto plano y este archivo va al repo.

INSERT INTO roles (id, nombre) VALUES
  (1, 'admin'),
  (2, 'usuario');

INSERT INTO anios (id, nombre) VALUES
  (1, '1er Año'),
  (2, '2do Año'),
  (3, '3er Año');

INSERT INTO email_config (clave, valor, activo) VALUES
  ('MAIL_HOST',        'smtp.gmail.com', 1),
  ('MAIL_PORT',        '587',            1),
  ('MAIL_ENCRYPTION',  'tls',            1),
  ('MAIL_USERNAME',    'prueba3530@gmail.com', 1),
  ('MAIL_PASSWORD',    NULL,             1),
  ('MAIL_FROM_EMAIL',  'prueba3530@gmail.com', 1);

-- SITE_URL es opcional: si no esta, la app usa el origen de la peticion. Se
-- carga si el sitio se sirve con un dominio propio y los correos tienen que
-- apuntar ahi.
-- INSERT INTO email_config (clave, valor, activo)
--   VALUES ('SITE_URL', 'https://votaciones.isft177.edu.ar', 1);


-- ---------------------------------------------------------------------------
-- Nota sobre los timestamps
-- ---------------------------------------------------------------------------
-- El dump de MySQL se exporto con `SET time_zone = "+00:00"`, asi que los
-- valores de las columnas TIMESTAMP que vienen en datos.sql estan en UTC. Por
-- eso ese archivo arranca con `SET TIME ZONE 'UTC'`: si no, Postgres los
-- interpretaria como horario de Argentina y cada fecha quedaria 3 horas antes.
--
-- El mapeo de tipos:
--
--   MySQL TIMESTAMP  -> timestamptz   instante, guardado en UTC
--   MySQL DATETIME   -> timestamp     reloj de pared, sin zona
--
-- Mezclarlos seria el error clasico de esta migracion: no da error de sintaxis,
-- las fechas quedan 3 horas corridas y como el sitio esta en Argentina el
-- desfase parece "que se hizo de dia" y nadie lo busca.
-- ---------------------------------------------------------------------------

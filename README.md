# VotacionesISFT177

Sistema de votación de proyectos para el ISFT N° 177. PHP 7.4 sobre Apache en
Docker, con PostgreSQL 9.5. Sin framework: las páginas son PHP plano y el
JavaScript son módulos ES.

---

## Servicios

| Servicio | Imagen | Puerto host | Qué es |
|---|---|---|---|
| `db` | build local → `postgres:9.5.25-alpine` | — | Base de datos, red interna |
| `php-apache` | build local | `${PHP_PORT}` (4200) | La app |
| `adminer` | `adminer:latest` | `${ADMINER_PORT}` (4300) | Administración de la base |

El build de `php-apache` vive en `Dockerfiles/php-apache/` y su contexto de build
es ese directorio (no la raíz), así que cualquier archivo que el `Dockerfile` copie
tiene que estar ahí. El de `db` vive en `Dockerfiles/postgres/` y es una imagen
de una línea: le agrega `tzdata` a Alpine (ver "Zona horaria").

---

## Puesta en marcha

```bash
cp .env.example .env      # y cambiar POSTGRES_PASSWORD
docker compose up -d --build
```

La app queda en `http://localhost:4200` y Adminer en `http://localhost:4300`.
El `schema.sql` y el `datos.sql` se montan en `/docker-entrypoint-initdb.d/`, así
que **la base solo se crea la primera vez** que arranca un contenedor limpio.

### Variables de entorno

Las lee `docker-compose.yml` por interpolación y la app por `env_file`. La
plantilla es `.env.example`.

| Variable | Para qué |
|---|---|
| `PHP_PORT` / `ADMINER_PORT` | Puertos en el host |
| `POSTGRES_DB` | Nombre de la base, lo crea el entrypoint |
| `POSTGRES_USER` / `POSTGRES_PASSWORD` | Rol que crea el entrypoint, y es superusuario |
| `DB_HOST` `DB_PORT` `DB_USER` `DB_PASS` `DB_NAME` | Los lee `conexion/conexion.php` |
| `APP_ENV` | `development` / `production` |

`POSTGRES_*` y `DB_*` tienen que **coincidir**: es el mismo rol y la misma base,
pero los leen dos procesos distintos. El entrypoint del contenedor los usa para
`CREATE ROLE`/`CREATE DATABASE`, y la app entra por PDO. Si se separan, la base se
crea con una clave y la app intenta entrar con otra, y el error es el genérico
"password authentication failed", que no dice de cuál de las dos viene el problema.

`DB_PORT` es **5432**. El `3306` que estaba antes es el de MySQL: si queda un
`3306` por ahí el síntoma es *connection refused*, no un error de contraseña, que
es lo que hace pensar menos.

`DB_HOST=db` es el **nombre del servicio** en la red de Compose. Fuera de Docker
(`php -S`) hay que poner la IP o el hostname real.

> `POSTGRES_USER` queda como superusuario del cluster, así que el rol de la app
> puede saltarse los permisos. Si eso molesta, crear el rol de la app aparte
> (`CREATE ROLE votacion_app LOGIN`) y darle solo `CONNECT` y `USAGE` sobre
> `public`.

### Crear el administrador

No hay registro de admin: se promotes una cuenta a mano, una vez, desde la base.

```sql
UPDATE usuarios SET es_admin = 1, rol_id = 1 WHERE email = 'tu@correo.com';
```

`es_admin` y `rol_id` tienen que coincidir (ver bug 4).

### Configurar el correo

Para activar cuentas y recuperar contraseñas hace falta SMTP. En la tabla
`email_config` (clave/valor) o por variables. Con Gmail usá una **contraseña de
aplicación**, no la clave de la cuenta.

`MAIL_PASSWORD` viene en `NULL` en `database/schema.sql`, a propósito: el dump de
MySQL traía la app password en texto plano y ese archivo está en git. Cargala a
mano la primera vez:

```sql
UPDATE email_config SET valor = '<la contraseña de aplicación>' WHERE clave = 'MAIL_PASSWORD';
```

Mientras esté en `NULL`, `diagnosticoSmtp()` la reporta como faltante y el
registro no llega a enviar el código.

`www/phpMail/enviar_correo.php` devuelve **`true` o un string de error**, nunca un
string *truthy* por accidente. Quien lo llama tiene que comparar con `=== true`.

Opcional: `SITE_URL` en la misma tabla es el origen del sitio y es lo que hace
que el correo lleve el botón "Activar mi cuenta". Vacío = el correo sale sin
links, que es como venía funcionando.

```sql
UPDATE email_config SET valor = 'http://localhost:4200' WHERE clave = 'SITE_URL';
```

### El correo y la paleta

El template de `enviar_correo.php` no usa colores propios: `correoPaleta()`
repite los valores del tema claro de `tokens.css`, con el nombre del token al
lado de cada hex. Si se cambia la paleta hay que cambiar los dos lados, porque
el correo no puede leer el CSS (los clientes no resuelven variables ni cargan
hojas de estilo). La única excepción son `--c-line` y `--c-line-strong`, que
en el CSS son `rgb(... / alfa)` y acá van aplastados a su equivalente sobre
blanco.

Sobre el tema oscuro: el correo va siempre en claro. No puede leer la
preferencia guardada en el navegador, y la marca dorada sobre carb tiene menos
contraste para texto chico. Las etiquetas `color-scheme` del `<head>` están
para que el cliente no lo invierta por su cuenta.

### Zona horaria

El sitio corre en hora de Argentina, y eso son tres lugares distintos que
tienen que coincidir:

| Dónde | Qué |
|---|---|
| `date.timezone` en el `php.ini` del `Dockerfile` | `America/Argentina/Buenos_Aires` |
| `date_default_timezone_set()` en `conexion/conexion.php` | Idem, para el `php -S` sin Docker |
| `SET TIME ZONE` en `conexion/conexion.php` | Por conexión, para lo que devuelve la base |

Los dos primeros son PHP y hace tiempo que están. El tercero es el que se
agregó con la migración, y es el que importa de verdad.

**Por qué el nombre y no `-03:00`.** El código anterior hacía
`SET time_zone = '-03:00'`, que es el horario **estándar**. Argentina aplica
hora de verano (UTC-2) de mediados de octubre a mediados de marzo, así que con
un offset fijo, durante esos meses la base queda **una hora atrás** de la que ve
el usuario. No es un error: es una diferencia de una hora en un dato, y por eso
no se detecta. Con el nombre de la zona, el servidor resuelve el offset solo y el
cambio de horario pasa sin tocar una línea.

**Por qué el `tzdata` es parte del build.** Alpine no trae la base de zonas
horarias: es el paquete `tzdata`, que hay que instalar aparte, y sin él Postgres
no conoce ningún nombre de zona. Por eso existe `Dockerfiles/postgres/` (una
línea: `apk add --no-cache tzdata`) en vez de usar la imagen oficial directo.

**Por qué hay un fallback.** `fijarZonaHoraria()` intenta el nombre y, si el
servidor no lo conoce, cae a `-03:00` y lo anota en el log. Un `SET TIME ZONE`
que falla **no** tira la conexión: si dejara caer el error en el `catch` de
conexión, un problema de zona horaria llevaría el sitio entero a un 503, que es
una falta desproporcionada para una hora de diferencia.

Para verificar que quedó bien, en cualquier conexión:

```sql
SHOW TIME ZONE;    -- tiene que decir America/Argentina/Buenos_Aires
```

Si dice `Etc/UTC`, el `tzdata` no se instaló en la imagen.

### Recargar el schema a mano

```bash
docker compose exec -T db psql -U votacion_user -d votacion_db < database/schema.sql
```

O para empezar de cero, `docker compose down -v` y `docker compose up -d`: eso
borra el volumen y los dos archivos se vuelven a correr en el arranque.

> Ojo: si el contenedor de `db` ya existe, `docker-entrypoint-initdb.d` **no** se
> vuelve a ejecutar, y Postgres corre esos `.sql` con `ON_ERROR_STOP=1`, así que
> un error de tipeo en el schema deja el contenedor sin arrancar en vez de dejar
> una base a medias. Para recargar hay que hacerlo a mano.

### Sobre el encoding: por qué `SET NAMES utf8mb4` ya no está

En MySQL la primera línea útil del `schema.sql` era `SET NAMES utf8mb4;` y **no
era decorativa**: sin ella el cliente entraba con `latin1` (es lo que hace la
imagen `mysql:5.7`) y todo texto UTF-8 se guardaba doble-codificado.

En Postgres el problema no existe: el encoding se negocia entre cliente y
servidor, así que los dos quedan en UTF-8 sin que nadie lo pida. No hay `SET
NAMES` que poner, ni `charset` en el DSN.

Lo que **sí** quedó, y por qué:

Al renombrar las tablas y columnas (ver migración del 2026-09-27) el schema ya
no tiene ninguna `ñ` en identificadores: `votos_1er_año` pasó a ser
`votos_1er_ano` y las tablas quedaron en `anios` y `proyectos`. Ese fue el
escenario peor del bug 6, porque un identificador mal creado se rompía **para
siempre**: la columna quedaba llamada `votos_1er_aÃ±o` y ninguna consulta la
encontraba, así que la app entera caía.

El valor `'1er Año'` de `anios.nombre` también necesita entrar como UTF-8, y en
Postgres eso ya está garantizado. Un valor roto se corrige con un `UPDATE`; un
identificador roto obliga a recrear la tabla.

Y hay un detalle de Postgres que no existía en MySQL: **los identificadores sin
comillas se pasan a minúscula**. Todas las tablas y columnas del proyecto ya
están en minúscula, así que no cambia nada, pero un `nombre` o `Anio` nuevo
tendría que definirse entre comillas dobles para conservar la mayúscula.

---

## Rutas

| Ruta | Qué es |
|---|---|
| `/index.php` | Landing pública |
| `/login/login.php` | Ingresar |
| `/login/recuperar.php` | Pedir nueva contraseña por correo |
| `/registro/registro.php` | Crear cuenta (envía código por correo) |
| `/registro/activar.php` | Activar cuenta **y** definir contraseña nueva |
| `/votacion.php` | Votar. Visible sin sesión; el contador es solo de admin |
| `/gracias.php` | Confirmación de voto |
| `/despedida.php` | Cierre de la votación, con cuenta regresiva |
| `/admin.php` | Panel: usuarios, subgrupos, config, resultados |
| `/logout.php` | Salir |
| `/api.php` | Backend por `POST action=...` |

Acciones de `api.php`: `verificar_usuario`, `votar`, `obtener_resultados`,
`obtener_usuarios`, `obtener_subgrupos_admin`, `agregar_subgrupo`,
`actualizar_subgrupo`, `eliminar_subgrupo`, `eliminar_usuario`,
`guardar_configuracion`, `obtener_configuracion`, `resetear_votos_usuario`,
`resetear_todos_los_votos`, `limpiar_votos_huerfanos`.

`votar` exige sesión (401 sin ella) y `obtener_usuarios` está detrás del chequeo de
admin. Ese endpoint **no** hace `SELECT u.*`: filtra las columnas para no mandar
hashes bcrypt al navegador.

### Base de datos

PostgreSQL 9.5. **8 tablas**, no 7: `anio_votacion` se agregó el 2026-09-28 y el
README nunca se actualizó. **Ningún identificador lleva `ñ`** (ver "Sobre el
encoding" y bug 6, ya cerrado):

| Tabla | Qué guarda realmente | Columnas |
|---|---|---|
| `roles` | Roles de usuario | `id`, `nombre` |
| `usuarios` | Cuentas y sus votos por año | `id`, `nombre`, `email`, `password`, `token`, `activo`, `rol_id`, `es_admin`, `votos_1er_ano`, `votos_2do_ano`, `votos_3er_ano`, `fecha_registro` |
| `anios` | **Los años** (antes se llamaba `grupos`) | `id`, `nombre` |
| `anio_votacion` | **El año de la edición**, que cambia el admin | `id`, `anio`, `activo`, `creado_en` |
| `proyectos` | **Los proyectos que se votan** (antes `subgrupos`) | `id`, `anio_id`, `nombre`, `activo` |
| `votos` | Un voto por usuario y año | `id`, `usuario_id`, `proyecto_id`, `anio_id`, `fecha_voto` |
| `configuracion_votacion` | Ventana de votación | `id`, `inicio_votacion`, `fin_votacion`, `activo`, `creado_en` |
| `email_config` | Ajustes de SMTP | `id`, `clave`, `valor`, `activo` |

`anios` y `anio_votacion` son tablas distintas y confundirlas rompe el sitio:
la primera tiene los años de cursada (1.º, 2.º, 3.º) con ids fijos en 1, 2 y 3;
la segunda tiene el año en que se vota, con un solo `activo = 1`. Lo lee
`anioVotacion()` en `conexion/conexion.php`.

#### Los tipos, y por qué no son los de MySQL

| Concepto | MySQL 5.7 | PostgreSQL 9.5 | Por qué |
|---|---|---|---|
| Primary key | `int UNSIGNED AUTO_INCREMENT` | `serial` | `GENERATED AS IDENTITY` es de Postgres 10. |
| `activo`, `es_admin`, `votos_*` | `tinyint(1)` | `smallint` | Ver abajo. |
| Instantes (`fecha_registro`, `fecha_voto`, `creado_en`) | `timestamp` | `timestamptz` | MySQL `TIMESTAMP` guarda UTC; `timestamptz` es lo mismo. |
| Relojes de pared (`inicio_votacion`, `fin_votacion`) | `datetime` | `timestamp` | No son instantes: son lo que el admin tipea. |
| `usuarios.email` | `varchar(150)` + collation | `citext` | Ver abajo. |
| `usuarios.token` | `char(4)` | `varchar(4)` | El `char` de Postgres es *blank padded* y complica comparar. |

**`smallint` y no `boolean`.** Es la decisión que más conviene no deshacer:
`pdo_pgsql` no siempre devuelve las columnas booleanas como boolean de PHP sino
como los strings `'t'` y `'f'`, y en JavaScript **un string no vacío es truthy**.
Con `es_admin` como boolean, un `if (data.es_admin)` con `'f'` dentro le
muestra el panel de admin a cualquiera. Con `smallint` viaja `0` o `1`, igual
que antes de la migración, y `votacion.js` y `admin.js` no necesitaron un cambio.

**`citext` y no `varchar`.** MySQL con `utf8mb4_unicode_ci` comparaba el correo
sin distinguir mayúsculas, así que `uq_usuarios_email` frenaba los duplicados con
distinto casing. Postgres con el default es **case-sensitive**: sin `citext`,
`Juan@mail.com` y `juan@mail.com` entran como dos cuentas distintas y el
`UNIQUE` no frena nada. Con `citext` el `UNIQUE` y el `WHERE email = ?` de
`login.php` vuelven a comportarse como antes. Lo que `citext` **no** hace es
ignorar acentos, así que `jose@` y `josé@` sí se tratan como distintas.

**Timestamps: el error que casi se cuela.** Mezclar `timestamp` y `timestamptz`
no da error de sintaxis. Las fechas quedan 3 horas corridas y, como el sitio
está en Argentina, el síntoma parece "que se hizo de día" y nadie lo busca. El
dump de MySQL se exportó con `SET time_zone = "+00:00"`, así que sus timestamps
son UTC: por eso `datos.sql` arranca con `SET TIME ZONE 'UTC'`, sin lo cual
cada fecha entra 3 horas antes.

#### El `GROUP BY` que hubo que ampliar

MySQL 5.7 resolvía `GROUP BY s.id` solo: como `s.id` es la primary key, deduce
que el resto de las columnas de esa fila son constantes (**functional
dependency**). Postgres **no implementa esa deducción** y rechaza la consulta
con `column "s.nombre" must appear in the GROUP BY clause`.

Las dos consultas de `obtener_subgrupos_admin` en `api.php` agrupaban solo por
`s.id` y listaban `s.nombre`, `s.activo` y `g.nombre`. Ahora agrupan por la
lista completa. Fue el único cambio de la migración que no es una traducción
literal.

`anios.nombre` guarda `'1er Año'`, `'2do Año'`, `'3er Año'`: el **valor** lleva ñ,
el **identificador** no. Esa distinción es el bug 6 cerrado.

Los `id` de los años son fijos en 1, 2 y 3 y la app los valida hardcodeados
(`in_array($anio_id, [1, 2, 3])` en `api.php`, en `votar` y en
`agregar_proyecto`). Agregar un cuarto año obliga a tocar esas dos validaciones y
la tabla `usuarios`, que tiene una columna por año.

#### Migración `grupos` → `anios`, `subgrupos` → `proyectos`

> Ya no se puede ejecutar: es de la época de MySQL y `database/migrar-2026-09-27-anios-proyectos.sql`
> no está en el repo. Queda como registro de por qué los nombres son estos.

Las tablas se llamaban `grupos` y `subgrupos`, pero `grupos` contenía los años y
`subgrupos` los proyectos: los nombres mentían sobre el dominio. Renombradas el
2026-09-27, junto con las 3 columnas `votos_*_año` → `votos_*_ano`.

El archivo usaba `RENAME TABLE` y `CHANGE COLUMN`, que **conservan los datos, los
índices y las claves foráneas**; no hacía falta recrear nada.

Renombró también el código: variables PHP, claves del `POST`, los alias del
`SELECT` y las 4 acciones de la API (`agregar_subgrupo` → `agregar_proyecto`, etc.,
con su único consumidor en `admin.js`) y los atributos `data-*` del frontend.

#### Migración MySQL 5.7 → PostgreSQL 9.5

Del 2026-09-30. La base no se editó con herramientas de conversión: el schema se
reescribió a mano en `database/schema.sql` y los datos se copiaron a
`database/datos.sql`. Con 8 tablas y 28 filas era más barato y más revisable que
confiarle el schema a un conversor.

**Lo que cambió en el código**, todo en consultas y en la conexión:

| Cambio | Dónde |
|---|---|
| `activo = TRUE` / `= FALSE` → `= 1` / `= 0` | 6 sitios (`api.php`, `votacion.php`) |
| Se borran los backticks de los identificadores | 6 usos, todos en `api.php` |
| `GROUP_CONCAT(x SEPARATOR ', ')` → `STRING_AGG(x, ', ')` | `api.php`, obtener usuarios |
| `CONCAT(a, b)` → `a \|\| b` | Mismo `SELECT`, por fidelidad |
| `GROUP BY s.id` → lista completa de columnas | 2 sitios, ver arriba |
| DSN `mysql:` → `pgsql:` y se saca `SET NAMES` | `conexion/conexion.php` |
| `SET time_zone = '-03:00'` → `SET TIME ZONE 'America/Argentina/Buenos_Aires'` | `conexion/conexion.php` |
| `mysqli pdo_mysql` → `pdo pdo_pgsql` | `Dockerfiles/php-apache/` |
| `mysql:5.7` → `postgres:9.5.25-alpine` + `tzdata` | `Dockerfiles/postgres/` |
| `phpmyadmin` → `adminer` | `docker-compose.yml` |
| El datadir deja de ser `./dump/` y pasa a un volumen con nombre | `docker-compose.yml` |

`CONCAT()` en Postgres convierte `NULL` en string vacío, y en MySQL devuelve
`NULL`. Con el `LEFT JOIN` de los votos, eso decide si un usuario sin votos
aparece con `votos_detalle` como `''` o como `null`. El operador `||` sí
devuelve `NULL`, que es lo que hacía MySQL.

**Lo que se rompió de paso y se arregló:** `eliminar_subgrupo` leía
`$voto['grupo_id']` de una consulta que devuelve la columna `anio_id`. El índice
no existía, el nombre de columna llegaba vacío al `UPDATE` y la acción tiraba
siempre 500 sin borrar nada. Ahora lee `anio_id` y saltea los votos cuyo año no
está en el mapa, en vez de armar SQL inválido.

**Lo que no se puede recuperar:** el `schema.sql` viejo. Era un **directorio
vacío** y `docker-compose.yml` lo montaba igual, así que Docker lo tomaba por un
volumen y el arranque en frío de la base nunca corrió el schema. El único
ejemplar estaba en el datadir de `./dump/`, sin versionar. Por eso ahora el
schema está en git, en los dos motores: `database/schema.sql` (Postgres) y
`database/schema-mysql.sql` (el de MySQL, que no usa nadie y está para poder
volver atrás).

**Por qué 9.5 y no algo más nuevo**, porque conviene dejarlo escrito:
PostgreSQL 9.5 quedó fuera de soporte en noviembre de 2021 y no recibe más
parches. MySQL 5.7 quedó en octubre de 2023, así que **la migración deja la base
más vieja, no menos**. Si la plataforma ofrece margen para elegir, cualquier
versión 12 o posterior evita casi todas las limitaciones que condicionaron el
diseño del schema: `IDENTITY` en vez de `SERIAL`, collations con provider ICU en
vez de depender de `citext`, y un `pdo_pgsql` cuyos booleanos se comportan de
forma predecible (lo que habría permitido `boolean` de verdad en vez de
`smallint`).

**Lo que se gana y lo que se pierde:** el `UNIQUE (usuario_id, anio_id)` de `votos`
—que es lo que hace imposible el voto doble— ya existía en MySQL y sigue igual.
No se agregó nada de seguridad con la migración: `pdo_pgsql` en Debian 10 es
posible porque un cliente más nuevo que el servidor es la dirección soportada,
pero también es un driver con más superficie.

---

## Paleta y tema

Estilo heráldico oscuro. Reparto 60-30-10: fondo oscuro, superficies de tono medio para
separar, dorado como acento (no como fondo de texto).

| Rol | Claro | Oscuro |
|---|---|---|
| Fondo | `#FAF8F0` | `#0D0D0D` |
| Superficie | `#FFFFFF` | `#2B2B2B` |
| Superficie hundida | `#F2EEE1` | `#3A3A3A` |
| Crema | `#F5F0B5` | `#F5F0B5` |
| Dorado | `#D4A73E` | `#D4A73E` |
| Texto | `#1A1A1A` | `#FFFFFF` |
| Texto secundario | `#5C574D` | `#9A9A9A` |

Todos los colores viven como variables en `:root` dentro de `www/css/tokens.css`.
No hay ningún color suelto en los demás archivos.

### Por qué `--c-gold-text` existe

El dorado puro `#D4A73E` da **2,10:1** sobre el fondo claro: ilegible como texto.
Por eso hay un token aparte para texto dorado:

| Token | Claro | Oscuro |
|---|---|---|
| `--c-gold` | `#D4A73E` | `#D4A73E` |
| `--c-gold-text` | `#8A6622` | `#D4A73E` |
| `--c-gold-soft` | `#86621C` | `#E8C97A` |
| `--c-gold-hover` | `#7C5B1C` | `#E8C97A` |

`--c-gold-soft` cumple la misma regla: en claro vale `#86621C` y **no** el crema
`#E8C97A`, que en claro daba 1,38:1. El crema queda solo para el modo oscuro,
donde sí se lee.

Hay una regla más, inversa: **el texto sobre dorado es negro**, y para eso existe
`--c-on-gold`. Usar `--c-bg` (que es el color del *fondo de la página*) como
"texto sobre dorado" es un error que en modo claro daba texto hueso sobre dorado a
2,10:1. Todo texto que va sobre dorado o crema usa su token `--c-on-*`.

### Contraste (medido, no estimado)

Los números salen de parsear `tokens.css` y calcular la fórmula de WCAG, no de
estimar a mano.

| Par | Claro | Oscuro |
|---|---|---|
| titular sobre fondo | 4,93:1 | 8,70:1 |
| titular sobre tarjeta | 5,24:1 | 6,34:1 |
| titular sobre superficie hundida | 4,52:1 | 7,79:1 |
| dorado suave (rótulo, icono) sobre fondo | 5,23:1 | 12,09:1 |
| dorado suave sobre superficie hundida | 4,79:1 | 10,83:1 |
| cuerpo sobre fondo | 6,75:1 | 13,77:1 |
| cuerpo sobre tarjeta | 7,18:1 | 10,03:1 |
| negro sobre dorado (botón) | 8,70:1 | 8,70:1 |
| negro sobre crema | 14,93:1 | 14,93:1 |
| gris sobre crema (subtítulo ganador) | 6,65:1 | 5,80:1 |
| texto de error | 8,54:1 | 8,56:1 |
| texto de éxito | 8,65:1 | 11,17:1 |
| placeholder | 5,07:1 | 6,91:1 |
| enlace hover | 5,86:1 | 12,09:1 |

**Ningún par queda bajo 4,5:1** en ninguno de los dos temas.

Combinaciones **prohibidas**: dorado sobre crema (1,92:1), blanco sobre dorado
(2,23:1), gris claro sobre crema (1,21:1). De ahí la regla: **la crema es una
superficie y lleva texto negro**, nunca dorado encima.

### Cómo funciona el tema

1. Sin preferencia guardada, se sigue al del sistema (`prefers-color-scheme`).
2. Con preferencia en `localStorage` (clave `isft177-tema`), manda la elección.
3. El atributo `data-theme` del `<html>` lo escribe `www/includes/theme-inline.php`,
   un script en línea en el `<head>`. Va ahí a propósito: si se escribiera desde
   un módulo al final del body, la página se pintaría con el tema del sistema y
   recién después saltaría al elegido, y eso se ve como un destello.
4. `www/js/theme.js` maneja el botón. Si la persona cambia el tema del sistema y no
   eligió uno a mano, la página lo sigue en vivo.

El botón usa `aria-pressed` y cambia de icono (luna/sol) según el estado.

Hay tres bloques de tokens en `tokens.css`: `:root, :root[data-theme='light']`,
`:root[data-theme='dark']` y, dentro de `@media (prefers-color-scheme: dark)`, un
`:root:not([data-theme='light'])`. Los tres deben declarar el mismo set de tokens.

### El menú hamburguesa de mobile

Por debajo de 48rem la navegación va plegada detrás del botón hamburguesa, que
renderiza `site-header.php` y maneja `www/js/menu.js` (el módulo lo carga el
propio parcial, no cada página: ver bug 7).

Tres decisiones que no son obvias:

1. **El switch de tema va primero del panel y a la izquierda**, con el nombre de
   usuario y "Salir" a la derecha de la misma fila. El orden lo fija `nav.css`
   con `order`, no el HTML, para que el marcado sea el mismo en mobile y en
   escritorio, donde el switch va en el medio entre el nav y "Salir".
2. **Sin JS el menú no se pliega.** El botón y el plegado dependen de la clase
   `js` que escribe el script inline de `theme-inline.php`; sin ella el panel se
   dibuja siempre. Es el bug 7 aplicado al revés: un control que no hace nada
   es peor que un control que no está. Y como la clase se escribe antes del
   primer pintado, plegar el menú no produce salto de layout.
3. **El estado son dos atributos en sincronía**: la clase `is-open` del panel (lo
   que lee el CSS) y `aria-expanded` del botón (lo que anuncia el lector de
   pantalla). No se usa `hidden` porque en escritorio el panel tiene que verse
   igual con el menú "cerrado", y el CSS tiene que poder ganarle al atributo.

Es un panel de revelación, no un modal: el foco se queda en el botón y no se
atrapa. Escape lo cierra y devuelve el foco al botón, y un toque fuera también.

### Tipografía

- **Titulares (h1, h2):** `Cinzel` 500/600/700 en `--c-gold-text`. Capitular
  romana ("Roman inscriptional"), la misma que se ve tallada en inscripciones y
  escudos: mantiene el tono heráldico y **se lee**. Reemplaza a la gótica
  `UnifrakturMaguntia`, que era ilegible en cuerpo de texto.
- **Cuerpo:** `Lato` 300/400/700.
- **Las fuentes son locales**: `www/fonts/*.woff2` (8 archivos) con los
  `@font-face` en `css/fonts.css`, y el `<head>` los pide con
  `includes/fonts.php`. Se movieron desde Google Fonts **para no depender de un
  tercero**, no por rendimiento: eso se midió y no se comprueba (ver "Rendimiento
  medido con Lighthouse"). **Sin internet cae en Georgia/serif** y el diseño no se
  rompe. Las dos familias son OFL 1.1, que permite servirlas desde el propio
  dominio; la licencia está en `www/fonts/OFL.txt`. La página tiene que verse
  **idéntica**: eso se verifica pixel a pixel contra la misma página con el
  `<link>` de Google, y tiene que dar 0 píxeles.

### Iconos

`www/includes/iconos.php` es un sprite SVG con **26 `<symbol>`**, en línea en cada
página. Se usan con `<svg class="icon"><use href="#i-luna"></use></svg>`. Cero
emojis en toda la interfaz.

### Layout

CSS mobile-first con `min-width`, Grid para lo 2D y Flexbox para lo de un eje, en
unidades relativas (`rem`, `em`, `ch`, `%`). Sin estilos en línea ni `!important`.
El JS es ES modules con `const`, `async`/`await`, `try-catch` y delegación de
eventos. El código y los comentarios están en español; los identificadores, en
inglés.

---

## Navegación y pie

`www/includes/site-header.php` y `site-footer.php` son parciales compartidos.

El header **cambia según la sesión**: con sesión aparecen las secciones privadas y
el botón de salir; sin sesión, las de crear cuenta e ingresar. La sección actual se
marca con `aria-current="page"` y con fondo, no solo con color: el color solo no
alcanza para quien no distingue el dorado del gris.

**Todos los links de los parciales llevan raíz absoluta** (`/index.php`,
`/logout.php`). No es cuestión de estilo: el navegador resuelve un `href` relativo
contra la URL de la **página**, no contra la del include, así que desde
`/registro/registro.php` un `href="logout.php"` apuntaría a
`/registro/logout.php`. El mismo bug en forma de `__DIR__ . 'includes/x.php'`
(sin barra inicial) produce `/var/www/htmlincludes/x.php`; `check-rutas.py` lo caza.

El footer usa `.auth > .sitefoot` (selector de hijo, no clase propia) en las
páginas de auth, para que el pie no se pueda volver a partir.

Las 4 páginas de auth son *split-screen* y no llevan header: ahí el botón de tema
va flotante. El resto de páginas lo llevan en el nav.

**Un parcial que renderiza un control interactivo carga su módulo.** El
`<script>` de `theme.js` vive dentro de `site-header.php`, no en cada página: si
viviera en la página, basta con que una se olvide para dejar un botón muerto
(ver bug 7).

---

## Rendimiento: OPcache y caché de estáticos

Las dos cosas viven en `Dockerfiles/php-apache/` y **requieren rebuild**:

```bash
docker compose build php-apache
docker compose up -d
```

Un `docker compose restart` no alcanza: son cambios de la imagen.

### Rendimiento medido con Lighthouse

Contra `index.php` en el perfil **mobile** de Lighthouse 12 (4× CPU, 1,6 Mbps,
150 ms de RTT), con `--throttling-method=devtools`.

**Lo importante del método es otro: hay que repetir.** El throttling de
Lighthouse tiene un ruido de ±10-25 % entre corridas idénticas. Con una sola
corrida por lado se sacaron dos conclusiones que después resultaron falsas (el
`backdrop-filter` del header y el tamaño de la fusión de CSS), así que **todo
número de acá es la mediana de 3 corridas como mínimo**:

```bash
CHROME_PATH=/usr/bin/chromium lighthouse http://localhost:4200/index.php \
  --only-categories=performance --form-factor=mobile --screenEmulation.mobile \
  --throttling-method=devtools --chrome-flags="--headless=new --no-sandbox"
```

Ojo también con `--throttling-method=simulate`: proyecta una simulación sobre la
traza en vez de estrangular de verdad, ysale mucho más optimista (1,5 s de FCP
donde el real da 2,2 s). Para decidir sirve, para informar engaña.

| | antes | después |
|---|---|---|
| Performance | 91 | **92-94** |
| FCP | 2,1 s | **2,2 s** |
| LCP | 2,1 s | **2,2 s** |
| CLS | 0,122 | 0,122 (sin cambio) |
| Requests bloqueantes | 6 hojas | **1 hoja** |

Lo único que se puede afirmar que **`ganó` fue juntar los CSS** (mediana 2,60 s
→ 2,10 s medido en el estado previo, 3 corridas por lado). El mecanismo no es
el peso: en HTTP/1.1 el navegador abre 6 conexiones, los pedidos se encolan, y la
**última** hoja de estilos es la que fija el primer pintado.

Lo que **no** pasó, y conviene anotarlo para no volver a intentarlo:

- **Self-hostear las fuentes nozczó FCP.** La expectativa era de 1,0 s y la
  diferencia real está dentro del ruido (mediana 2,1 s con Google Fonts contra
  2,2 s local, 3 corridas por lado). Se mantiene igual porque quita dos orígenes
  de terceros y porque las fuentes quedan servidas sin depender de Google, pero
  no por rendimiento. Ver abajo la lección de Lato.
- **`width`/`height` en los `<img>` del logo** no bajó el CLS: el culpable del
  0,122 es un **nodo de texto** que se reacomoda cuando la fuente web entra
  (`font-display: swap` sin un fallback de métricas compatibles). Es
  preexistente: el sitio con Google Fonts medía 0,112-0,122 también. Los
  atributos igual se quedan, porque son lo correcto y no cuesta nada.
- **Bajar el logo a 192 px** se probó y **se revirtió**: el escudo tiene
  lettering chico y se veía borroso. Volvió el de 480 px, que igual es 12 KB más
  liviano que el `.webp` de 480 px que se servía antes.
- **`backdrop-filter: blur(8px)` del header**: se sospechó que costaba 800 ms de
  CPU en mobile y se quitó. Repetido el A/B con 3 corridas por lado, no se
  comprueba (mediana 2,70 s con contra 2,60 s sin, rango 2,40-3,10 en ambos).
  Se restauró.
- **`font-display: optional`**: medido, no cambia el FCP.
- **Quitar Lato de la URL**: solo 100 ms; el costo no es el volumen.

### La lección de Lato: no toda fuente variable

Cinzel es variable: un solo `.woff2` cubre 500, 600 y 700. **Lato no**: Google lo
sirve como un archivo estático por peso, y son 6 en total (3 pesos × 2
subconjuntos). Servirle uno solo —que fue lo que se hizo al principio— deja los
textos del cuerpo dibujados con el archivo del peso 300, y **la página se ve
distinta**. La forma de no CFO: `fonts.css` es la salida literal de la API de
Google con las URLs cambiapdas a locales, y los archivos se nombran por peso
cuando la familia es estática.

Para no volver a hacerlo a ojo, la verificación de una fuente self-hosteada es
**pixel a pixel contra la misma página con el `<link>` de Google**: con eso, la
diferencia tiene que dar 0 píxeles en mobile, desktop y en una página de auth.

### OPcache

Sin OPcache cada request recompila el PHP a opcode y lo descarta. Con OPcache el
opcode queda en memoria compartida entre requests, que es el mayor ahorro de CPU
posible sin tocar el código.

`opcache.so` viene en la imagen oficial pero no siempre habilitado, así que el
`Dockerfile` lo habilita con `docker-php-ext-enable opcache` (idempotente),
precedido de un `ls` del `.so`: si el módulo no estuviera, el build falla con un
mensaje claro en vez de dejar el contenedor avisando "Unable to load dynamic
library" en cada request.

Los valores van en el `php.ini` que genera el propio `Dockerfile`:

| Ajuste | Valor | Por qué |
|---|---|---|
| `memory_consumption` | 128 MB | Sobra para este tamaño de proyecto |
| `interned_strings_buffer` | 16 MB | Margen de sobra sobre el default |
| `max_accelerated_files` | 10000 | El proyecto tiene decenas de scripts. Si Apache llega al tope, OPcache deja de cachear y **avisa en el log** |
| `validate_timestamps` | 1 | En desarrollo el código está montado desde `./www` y los cambios tienen que verse |
| `revalidate_freq` | 2 | Como mucho mira el archivo cada 2 s, así que editar y recargar funciona al instante |
| `enable_cli` | 0 | En CLI no hay benefit y solo consumiría memoria |

En producción se pondría `validate_timestamps=0` (más rápido todavía), pero eso
exige **reiniciar PHP tras cada despliegue**: los cambios dejan de detectarse
solos. Con el código montado en un bind mount como en desarrollo, no conviene.

Comprobar:

```bash
docker compose exec php-apache php -r 'var_dump(opcache_get_status(false)["opcache_enabled"]);'
```

Debe imprimir `bool(true)`.

### Cabeceras de caché

`Dockerfiles/php-apache/cache.conf` se copia a
`/etc/apache2/conf-available/isft177-cache.conf` y se activa con `a2enconf`.

> **Ojo con las rutas.** `php:7.4-apache` instala el **paquete `apache2` de
> Debian**, no una compilación desde fuente. La config está en `/etc/apache2/` y
> **no** existe `/usr/local/apache2/`. El `Server` lo delata:
> `Apache/2.4.54 (Debian)`. Por eso los helpers son `a2enmod` / `a2enconf` /
> `apache2ctl`, y no hay que editar `apache2.conf` a mano.

Apache ya enviaba `ETag` y `Last-Modified`, pero ningún `Cache-Control`: el
navegador tenía que preguntar en cada visita.

| Tipo | `Cache-Control` |
|---|---|
| `.css`, `.js` | `public, max-age=3600, must-revalidate` |
| imágenes, fuentes, iconos | `public, max-age=604800, must-revalidate` |
| `.php` | `private, no-store, max-age=0` |

Tres decisiones:

- **Nada de `immutable`.** Los assets no llevan hash en el nombre
  (`href="css/tokens.css"`, sin `?v=`), así que un navegador que ya se guardó el
  archivo no tiene forma de saber que cambió. Con `immutable` un cambio de CSS no
  llegaría a quien sirvió la versión vieja durante semanas. En su lugar,
  `must-revalidate`: pasado el `max-age` revalida contra el `ETag` y recibe un 304
  vacío, casi gratis frente a volver a bajar el archivo. Los valores priorizan
  **"el cambio se ve rápido"** sobre "cero requests".
- **El `.php` lleva `no-store`.** El HTML depende de la sesión y lleva token CSRF,
  así que no debe quedar en disco ni en proxies compartidos. `private` además de
  `no-store` deja explícito que ni un caché intermedio lo guarde. (PHP ya ponía
  `no-store, no-cache, must-revalidate` por su cuenta; esto lo hace explícito y con
  `max-age=0`.)
- **`mod_headers` se asegura en el `Dockerfile`.** `a2enmod headers` crea el
  symlink en `mods-enabled/`, y los `test -e` que van después son la aserción:
  `a2enmod` es idempotente, así que sin ellos un módulo ausente pasaría el build
  en silencio y la app serviría todo sin cachear sin avisar. Con ellos, build rojo.

Para endurecer esto en producción: subir los `max-age` **y** pasar los assets a
nombres con hash (`tokens.<hash>.css`), que es la condición para que `immutable`
sea seguro.

Comprobar que llegó al navegador:

```bash
curl -sSI http://localhost:4200/css/tokens.css | grep -i cache-control
# public, max-age=3600, must-revalidate
```

El `Dockerfile` termina con `apache2ctl configtest`, que valida la config ya
armada. Sin eso, una sintaxis mal escrita en `cache.conf` no se descubre hasta que
Apache no arranca y el contenedor entra en loop de reinicio.

> Detalle de escritura: los comentarios van **fuera** del `RUN`. Dentro de un
> comando con continuaciones `\`, un `#` se come el resto de la línea unida y la
> instrucción se pierde en silencio.

### Lo que falta, y es lo siguiente que rendaría

- **Compresión.** `mod_deflate` para CSS/JS/HTML. El CSS son ~50 KB de texto y
  comprime 4-5x; es el cambio más grande que queda y es el mismo mecanismo (una
  directiva en el `.conf`).
- **Nombres con hash en los assets.** Habilita `immutable` y un `max-age` de un
  año, el estado final de cualquier setup de estáticos.

---

## Build: por qué el `Dockerfile` ahora sí corre `apt-get`

`php:7.4-apache` es **Debian 10 (buster)**, que quedó EOL y fue **retirado de
`deb.debian.org`**. Cualquier `apt-get update` contra los repos de la imagen base
devuelve 404 y el build muere con exit code 100.

Hasta la migración a Postgres eso no molestaba: el proyecto no usa ni `git` ni
`composer` (no hay `composer.json` ni un solo `shell_exec`/`exec`/`system`), así
que el build no tocaba la red después de la imagen base.

Ahora **sí hace falta**: `pdo_pgsql` compila contra `libpq`, que no está en la
imagen. El `Dockerfile` reescribe `sources.list` a `archive.debian.org` (donde
queda buster archived), instala `libpq-dev` y compila la extensión.

Dos detalles que hacen fallar el build si se olvidan:

- Va **solo `buster main`**. `buster-updates` no existe en `archive.debian.org`,
  así que dejarlo produce un 404 en el `apt-get update`, que es el mismo error
  que impedía hacer esto antes.
- Se **reescribe** `sources.list` entero en vez de hacer un `sed`: el contenido
  del archivo cambia entre tags de la imagen base, y reescribirlo no depende de
  eso.

Lo que **no** se negocia es que el build no tenga red para el resto: `git` y
`composer` siguen sin instalarse, y lo único que se agrega a la imagen es
`libpq-dev` (las cabeceras de compilación, que no se usan en runtime).

La imagen de la base es otra historia: `Dockerfiles/postgres/` sí necesita la red
en cada build, pero solo para `apk add tzdata` (ver "Zona horaria").

---

## Bugs conocidos

Encontrados al reconstruir el schema. Los marcados `(CORREGIDO)` están
arreglados; el resto queda pendiente de decisión.

1. **(CORREGIDO) `enviar_correo.php` devolvía strings de error que son
   *truthy*.** En los fallos devolvía `"Error: ..."`. Como un string no vacío es
   truthy, `registro_validar.php` y `activar.php` tomaban la rama de éxito: el
   usuario avanzaba **sin haber recibido el código**. Ahora el contrato es `true`
   o string de error, y quien llama compara con `=== true`.

2. **(CORREGIDO) XSS reflejado en `admin.php`.** `htmlspecialchars($_GET['tab'])`
   no escapaba el apóstrofo (el default es `ENT_COMPAT`) y el valor se inyectaba
   dentro de un string JS de comillas simples. Un `?tab=');alert(1)//` escapaba
   del string. Está detrás del chequeo de admin, así que requiere engañar a un
   admin.

3. **(CORREGIDO) `activar.php` bloqueaba la recuperación de contraseña.**
   Mostraba el formulario solo si `!$user['activo']`, usando el mismo flag para
   dos cosas distintas: "la cuenta puede entrar" y "la cuenta ya pasó la
   activación". Un usuario que había olvidado la contraseña estaba activo, así que
   le decía "tu cuenta ya está activada" y nunca lo dejaba definir una nueva.
   Corregido: la condición es si hay **token** pendiente
   (`$user['token'] !== null`), no el flag `activo`.

   Además `generar_token.php` escribía el token nuevo en la base **antes** de
   intentar enviarlo. Con el SMTP sin configurar, un fallo de envío invalidaba
   el token anterior sin que nadie recibiera nada. Ahora **envía primero** y solo
   persiste si salió bien.

4. **`es_admin` y `rol_id` no están sincronizados.** `login.php` decide por
   `rol_id`, `api.php` lee `es_admin`. Si se actualiza una sola, el front y el
   backend discrepan en quién es admin.

   Confirmado con datos en la migración del 2026-09-30: de los 7 usuarios, **dos
   tienen `rol_id = 1` y `es_admin = 0`**. Como `login.php` decide por `rol_id`,
   esos dos son admin en la sesión y la columna `es_admin` no dice la verdad.
   No se "corrigió" al migrar: cambiar quién es admin es una decisión del
   negocio, no una conversión de formato. Lo que sí se dejó escrito es que
   `es_admin` es decorativa, y por eso la migración la dejó en `smallint` en vez
   de aprovecharla para volverla un `boolean` de verdad.

5. **`actualizarResultados()` está muerta.** Está definida pero no la llama nadie.
   Junto con `admin.js`, que accede al DOM en el tope del módulo sin esperar
   `DOMContentLoaded`: hoy funciona porque el `<script>` está al final del
   `<body>`, pero bajo cualquier bundler los scripts se diferirían y rompería.

6. **(CORREGIDO) `votos_1er_año` / `votos_2do_año` / `votos_3er_año` tenían `ñ`
   en el identificador.** Era la fragilidad más real del schema: dependía del
   charset con el que el cliente interprete el SQL, y ya una vez rompió la app
   entera (ver "Sobre el encoding" arriba). Renombradas a `votos_1er_ano` /
   `votos_2do_ano` / `votos_3er_ano` en la migración del 2026-09-27, junto con
   `grupos` → `anios` y `subgrupos` → `proyectos`. **Ahora el schema no tiene
   ninguna `ñ` en identificadores**: el *valor* `'1er Año'` sí la tiene, y eso
   no molesta porque viaja como dato, no como nombre de objeto.

7. **(CORREGIDO) El botón de tema del nav no hacía nada.** `site-header.php`
   renderizaba el botón, pero el módulo que lo ata (`theme.js`) lo cargaba cada
   página por su cuenta, y tres se lo olvidaron: `index.php`, `votacion.php` y
   `admin.php`. Como `theme.js` usa delegación de eventos sobre
   `[data-theme-toggle]`, sin el script no hay nadie escuchando y el clic se
   pierde: **en el nav no se podía cambiar de tema**, mientras que en las páginas
   de auth el botón flotante sí funcionaba. El atributo `data-theme` igual lo
   escribía `theme-inline.php`, así que la página respetaba la preferencia
   guardada: el síntoma exacto era "no puedo *cambiar* el tema desde el nav".

   Fix: el `<script type="module" src="/js/theme.js">` se movió **dentro de
   `site-header.php`**, que es donde vive el control, y se le sacó el que tenía
   `gracias.php`. La ruta es **absoluta a propósito**: el navegador resuelve un
   `src` relativo contra la URL de la página, no contra la del parcial, así que
   `js/theme.js` se habría roto (`/login/js/theme.js`) en cuanto una página de
   subdirectorio usara ese encabezado.

   La lección: **un parcial que renderiza un control interactivo carga su
   módulo.** Si el `script` vive en la página, alcanza con que una se olvide para
   dejar un botón muerto, y nada lo detecta: el HTML es válido, el 200 es correcto
   y `check-rutas.py` da todo bien.

8. **Falta una verificación automática de controles huérfanos.** La única forma de
   que el bug 7 no vuelva a colarse es cruzar los `data-*` que cada módulo JS
   escucha contra los que cada página emite, y avisar si un control no tiene su
   módulo. `check-rutas.py` hoy no lo hace.

9. **Un error de base de datos es indistinguible de una base vacía.** Casi todos
   los `catch (PDOException)` solo hacen `error_log($e->getMessage())` y después
   la página sigue y renderiza el estado vacío. En `votacion.php:100` eso
   significa que, si `proyectos` no existe, la página responde **200** con
   "Todavía no hay proyectos cargados": el mismo HTML que si la tabla estuviera
   vacía y el mismo código que devuelve `check-rutas.py`.

   Se descubrió justo al renombrar las tablas: el código pasó a pedir
   `proyectos`, la base seguía con `subgrupos`, y por HTTP no había forma de
   distinguirlo. La única señal estaba en `error_log`.

   `index.php` ya lo hace bien (pone `$error_bd = true` y muestra un aviso), así
   que el patrón correcto existe en el proyecto y falta extenderlo. Fuera de
   `development` el mensaje al usuario debería ser genérico ("no pudimos cargar
   los proyectos") y el detalle técnico quedarse en el log.

10. **(CORREGIDO) `database/schema.sql` era un directorio vacío.** `docker-compose.yml`
    lo montaba en `/docker-entrypoint-initdb.d/01-schema.sql`, así que Docker lo
    tomaba por un volumen: la base se creaba **sin ninguna tabla**. No se
    notaba porque la base venía de `./dump/`, que ya tenía un datadir, y MySQL no
    corre los scripts de init si el directorio de datos no está vacío. O sea
    que el schema nunca se ejecutó: el único ejemplar estaba en el datadir, sin
    versionar, y se reconstruyó a mano desde un export de phpMyAdmin.

    Esto es el bug 9 en su versión más cara: la base "funcionaba" y el archivo
    que la documentaba no era un archivo. Ahora el schema está en git y en los
    dos motores.

11. **(CORREGIDO) `eliminar_subgrupo` siempre devolvía 500.** La consulta pide
    `v.anio_id` y el código leía `$voto['grupo_id']`: el índice no existía, el
    nombre de columna llegaba vacío al `UPDATE usuarios SET  = 0`, y eso es SQL
    inválido. Con algún voto en el proyecto, la acción no borraba nada y
    respondía error; sin votos, el `foreach` no iteraba y el resto de la
    operación sí pasaba, que es el peor caso: parecía funcionar.

12. **No hay forma de comprobar que un `GROUP BY` agrupa lo que dice.**
    El bug del `GROUP BY s.id` estaba desde antes de Postgres y llevaba meses
    sin aparecer, porque MySQL lo aceptaba por *functional dependency*. Después
    de la migración, un `GROUP BY` incompleto **mata la consulta** en vez de
    devolver algo raro, que es una mejora enorme para detectarlo. El problema es
    el contrario: con `catch (PDOException)` por todos lados, "mató la consulta" y
    "no hay datos" se ven iguales desde afuera (bug 9). Por eso los `setval` y
    las consultas de verificación de `datos.sql` imprimen los números de fila en
    el log del contenedor: es la única señal de que la carga salió bien.

---

## Verificación

> `database/check-rutas.py` **no está en el repo**. El proyecto tiene
> verificación documentada pero le falta la herramienta que la hacía, y sin ella
> la migración del 2026-09-30 salió sin red de seguridad automática. Es lo primero
> que conviene recuperar: el bug 12 explica por qué importa justo ahora.

```bash
python3 database/check-rutas.py     # o: BASE_URL=http://localhost:4200 python3 ...
```

Recorre las 9 páginas, resuelve los `include` de cada una, pide por HTTP cada CSS y
JS que la página linkea, comprueba que haya **un solo `<h1>`** y que la navegación
entre páginas responda. Es la red de seguridad del bug de `__DIR__` sin barra
inicial: reintroducir ese bug a propósito hace que el script detecte las 24 rutas
rotas.

Vale con `BASE_URL` como variable de entorno, así que sirve para un despliegue
remoto.

Lo que **no** cubre: que un control interactivo tenga su módulo (bug 7), y el
contraste, que hay que medirlo aparte parseando `tokens.css`.

Antes de cada commit conviene:

```bash
python3 database/check-rutas.py
grep -rnP '[\x{3000}-\x{9FFF}]' www/ database/ README.md   # CJK accidental
grep -rn '!important' www/css/                               # prioridades
grep -rnP '[\x{1F300}-\x{1FAFF}]' www/                       # emojis colados
```

Dos falsos positivos conocidos, para no perseguirlos:

- `grep -rn 'style=' www/*.php` marca `www/phpMail/enviar_correo.php`, y está
  **bien**: los clientes de correo no soportan variables CSS ni hojas de
  estilo, así que el template del email lleva estilos inline y sus propios
  colores a propósito. Los colores no son propios de todas formas: son los
  del tema claro de `tokens.css`, transcritos en `correoPaleta()` con el token
  de origen al lado (ver "El correo y la paleta"). Excepción consciente a la
  regla de "sin estilos en línea".
- Los `width`/`height` de los `<img>` del logo están para que el navegador reserve
  el espacio. **No son los que arreglan el CLS**: el culpable medido es un nodo de
  texto que se reacomoda cuando entra la fuente web.
- El logo va siempre en el PNG de 480 px. Se probó una versión de 192 px para
  header y pie y **se revirtió** por borrosa (el escudo tiene lettering chico).
  Sigue siendo más liviano que lo que se servía antes, que era el `.webp` de
  480 px: 23 KB contra 35. El `.webp` se reencodificó sin pérdida para que, donde
  se use, no estire; para este escudo el PNG igual comprime mejor (son 256
  colores planos).

---

## Estructura

```
.
├── docker-compose.yml
├── docker-compose.prod.yml
├── .env / .env.example
├── database/
│   ├── schema.sql          8 tablas de Postgres + datos de referencia
│   ├── datos.sql           los datos que venían de MySQL + setval de secuencias
│   └── schema-mysql.sql    el schema de MySQL, para poder volver atrás
├── Dockerfiles/php-apache/
│   ├── Dockerfile          pdo_pgsql, OPcache, php.ini, cabeceras de caché
│   └── cache.conf          Cache-Control de los estáticos
└── www/
    ├── index.php           landing
    ├── votacion.php        votación
    ├── gracias.php
    ├── despedida.php       cierre con cuenta regresiva
    ├── admin.php           panel
    ├── api.php             backend por POST action=
    ├── logout.php
    ├── conexion/conexion.php
    ├── includes/
    │   ├── site-header.php     nav compartida (carga theme.js y menu.js)
    │   ├── site-footer.php     pie compartido
    │   ├── iconos.php          sprite SVG, 26 símbolos
    │   ├── theme-inline.php    aplica el tema antes del primer pintado
    │   ├── fonts.php           precarga de las tipografías locales
    │   └── flash.php
    ├── css/
    │   ├── tokens.css      variables, los 3 bloques de tema
    │   ├── fonts.css       @font-face de Cinzel y Lato, locales
    │   ├── components.css  primitivas (btn, card, field, dialog, alert)
    │   ├── nav.css         encabezado y pie; el menu hamburguesa de mobile
    │   ├── icons.css
    │   ├── theme-toggle.css
    │   ├── landing.css
    │   ├── auth.css
    │   ├── votacion.css
    │   ├── admin.css
    │   └── closing.css
    ├── js/                 módulos ES, sin framework
    │   ├── theme.js
    │   ├── menu.js
    │   ├── validate.js
    │   ├── toggle-password.js
    │   ├── votacion.js
    │   ├── admin.js
    │   └── auto-redirect.js
    ├── login/              login.php, recuperar.php, funciones_login.php
    ├── registro/           registro.php, registro_validar.php, activar.php, generar_token.php
    ├── phpMail/            PHPMailer 5.2.16 + enviar_correo.php
    ├── fonts/              Cinzel + Lato en woff2 (8 archivos), con OFL.txt
    └── Logo/               logo 480 px para la web + 256 para el correo
```

`dump/` era el datadir de MySQL, montada con `./dump/:/var/lib/mysql`. Desde la
migración a Postgres el datadir es el volumen `pgdata` y `dump/` quedó sin usar:
se puede borrar, pero **no antes** de verificar que la base nueva tiene todo.

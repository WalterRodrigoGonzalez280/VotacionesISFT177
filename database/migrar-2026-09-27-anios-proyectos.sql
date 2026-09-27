-- ===========================================================================
-- Migracion idempotente: grupos -> anios, subgrupos -> proyectos
-- ===========================================================================
-- ESTA VERSION REEMPLAZA a la primera, que no lo era. Que no lo era es
-- exactamente lo que dejo la base a medias: un RENAME TABLE que se aplico y
-- cinco ALTER que no, con el cliente mysql cortando en el primer error.
-- Correrla dos veces no es seguro si ya se aplico a medias, y el sintoma
-- (tablas nuevas, columnas viejas) no dice que paso.
--
-- Esta version consulta information_schema antes de cada cambio y solo aplica
-- lo que falta. Se puede correr tantas veces como haga falta: siempre
-- converge al mismo estado.
--
-- De paso cierra el bug 6: las columnas votos_*_ano de usuarios salen de la
-- base. La eñe en un identificador depende del charset con el que el cliente
-- de MySQL interprete el SQL; la imagen mysql:5.7 entra con latin1, y sin
-- `SET NAMES utf8mb4` la columna queda creada como `votos_1er_aÃ±o` con doble
-- codificacion y ninguna consulta la encuentra. Ese error ya paso una vez.
--
-- Por que `anios` y no `años`: el VALOR visible sigue siendo '1er Año' con
-- eñe; solo el identificador va sin ella.
--
-- COMO APLICAR (con un backup previo):
--
--   sudo docker-compose exec -T db mysql -uvotacion_user -p"$MYSQL_PASSWORD" \
--       votacion_db < database/migrar-2026-09-27-anios-proyectos.sql
--
-- Al final trae las consultas de verificacion.
-- ===========================================================================

SET NAMES utf8mb4;

-- Para cada cambio: si el objeto viejo existe y el nuevo no, se aplica. Si ya
-- esta hecho, no pasa nada. `DO 0` es un no-op valido en MySQL 5.7.

-- ---------------------------------------------------------------------------
-- 1. Tablas
-- ---------------------------------------------------------------------------
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grupos') = 1,
  'RENAME TABLE `grupos` TO `anios`', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subgrupos') = 1,
  'RENAME TABLE `subgrupos` TO `proyectos`', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------------
-- 2. Columnas de proyectos y votos
-- ---------------------------------------------------------------------------
-- Se comparan los nombres exactos, que no tienen eñe.
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proyectos'
      AND COLUMN_NAME = 'grupo_id') = 1,
  'ALTER TABLE `proyectos` CHANGE COLUMN `grupo_id` `anio_id` INT UNSIGNED NOT NULL',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'votos'
      AND COLUMN_NAME = 'subgrupo_id') = 1,
  'ALTER TABLE `votos` CHANGE COLUMN `subgrupo_id` `proyecto_id` INT UNSIGNED NOT NULL',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'votos'
      AND COLUMN_NAME = 'grupo_id') = 1,
  'ALTER TABLE `votos` CHANGE COLUMN `grupo_id` `anio_id` INT UNSIGNED NOT NULL',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------------
-- 3. Las tres columnas de usuarios
-- ---------------------------------------------------------------------------
-- Estas NO se buscan por nombre exacto, y a proposito. La columna puede estar
-- como `votos_1er_ano` (utf8mb4 correcto, eñe = C3B1) o como
-- `votos_1er_aÃ±o` (doble codificada, eñe = C383C2B1) segun como se haya
-- creado la tabla. Escribir el nombre a mano fallaria en uno de los dos casos,
-- asi que se busca por prefijo y se usa el nombre REAL que devuelve la base.
--
-- SUBSTRING en vez de LIKE a proposito: en un literal SQL el `_` de `votos_1er`
-- es un comodin, y hay que escaparlo. Con SUBSTRING no hay escapes.
SET @col := (SELECT COLUMN_NAME FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
    AND SUBSTRING(COLUMN_NAME, 1, 9) = 'votos_1er' LIMIT 1);
SET @sql := IF(@col IS NULL, 'DO 0', CONCAT(
  'ALTER TABLE `usuarios` CHANGE COLUMN `', @col,
  '` `votos_1er_ano` TINYINT(1) NOT NULL DEFAULT 0'));
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @col := (SELECT COLUMN_NAME FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
    AND SUBSTRING(COLUMN_NAME, 1, 9) = 'votos_2do' LIMIT 1);
SET @sql := IF(@col IS NULL, 'DO 0', CONCAT(
  'ALTER TABLE `usuarios` CHANGE COLUMN `', @col,
  '` `votos_2do_ano` TINYINT(1) NOT NULL DEFAULT 0'));
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @col := (SELECT COLUMN_NAME FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
    AND SUBSTRING(COLUMN_NAME, 1, 9) = 'votos_3er' LIMIT 1);
SET @sql := IF(@col IS NULL, 'DO 0', CONCAT(
  'ALTER TABLE `usuarios` CHANGE COLUMN `', @col,
  '` `votos_3er_ano` TINYINT(1) NOT NULL DEFAULT 0'));
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------------
-- 4. Nombres de indices y claves foraneas
-- ---------------------------------------------------------------------------
-- No son cosmeticos: en MySQL forman parte de la definicion de la tabla, y
-- dejarlos con el nombre viejo deja objetos huerfanos que un DROP posterior no
-- podria borrar.
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'anios'
      AND INDEX_NAME = 'uq_grupos_nombre') = 1,
  'ALTER TABLE `anios` RENAME KEY `uq_grupos_nombre` TO `uq_anios_nombre`', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proyectos'
      AND INDEX_NAME = 'idx_subgrupos_grupo') = 1,
  'ALTER TABLE `proyectos` RENAME KEY `idx_subgrupos_grupo` TO `idx_proyectos_anio`', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proyectos'
      AND INDEX_NAME = 'fk_subgrupos_grupo') = 1,
  'ALTER TABLE `proyectos` RENAME KEY `fk_subgrupos_grupo` TO `fk_proyectos_anio`', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'votos'
      AND INDEX_NAME = 'idx_votos_subgrupo') = 1,
  'ALTER TABLE `votos` RENAME KEY `idx_votos_subgrupo` TO `idx_votos_proyecto`', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'votos'
      AND INDEX_NAME = 'idx_votos_grupo') = 1,
  'ALTER TABLE `votos` RENAME KEY `idx_votos_grupo` TO `idx_votos_anio`', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'votos'
      AND INDEX_NAME = 'uq_votos_usuario_grupo') = 1,
  'ALTER TABLE `votos` RENAME KEY `uq_votos_usuario_grupo` TO `uq_votos_usuario_anio`', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'votos'
      AND INDEX_NAME = 'fk_votos_subgrupo') = 1,
  'ALTER TABLE `votos` RENAME KEY `fk_votos_subgrupo` TO `fk_votos_proyecto`', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'votos'
      AND INDEX_NAME = 'fk_votos_grupo') = 1,
  'ALTER TABLE `votos` RENAME KEY `fk_votos_grupo` TO `fk_votos_anio`', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------------
-- 5. Verificacion
-- ---------------------------------------------------------------------------
-- Debe listar 7: anios, configuracion_votacion, email_config, proyectos, roles,
-- usuarios, votos.
SELECT TABLE_NAME
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE()
 ORDER BY TABLE_NAME;

-- Debe dar 0 filas: no puede quedar ningun objeto con el nombre viejo.
SELECT TABLE_NAME, COLUMN_NAME
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND (COLUMN_NAME LIKE '%subgrupo%' OR COLUMN_NAME LIKE '%grupo%'
        OR COLUMN_NAME LIKE '%_a%o' AND COLUMN_NAME LIKE 'votos%');

SELECT TABLE_NAME, INDEX_NAME
  FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA = DATABASE()
   AND (INDEX_NAME LIKE '%subgrupo%' OR INDEX_NAME LIKE '%grupo%')
 GROUP BY TABLE_NAME, INDEX_NAME;

-- ===========================================================================
-- ROLLBACK
-- ===========================================================================
-- Ojo: deshace el orden INVERSO, y hay que revertir tambien el commit de la
-- app, porque si el codigo PHP queda desalineado con la base las paginas
-- dejan de funcionar. Y la eñe de votos_*_ano vuelve a aparecer, con el
-- riesgo de encoding que ya se pago una vez.
--
-- SET @col := (SELECT COLUMN_NAME FROM information_schema.COLUMNS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
--     AND COLUMN_NAME = 'votos_1er_ano' LIMIT 1);
-- SET @sql := CONCAT('ALTER TABLE `usuarios` CHANGE COLUMN `', @col,
--   '` `', @col, '_anio` TINYINT(1) NOT NULL DEFAULT 0');
-- PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;   -- repetir 2do y 3ro
--
-- ALTER TABLE `votos`
--   CHANGE COLUMN `proyecto_id` `subgrupo_id` INT UNSIGNED NOT NULL,
--   CHANGE COLUMN `anio_id`    `grupo_id`    INT UNSIGNED NOT NULL,
--   RENAME KEY `fk_votos_anio`         TO `fk_votos_grupo`,
--   RENAME KEY `fk_votes_proyecto`     TO `fk_votes_subgrupo`,
--   RENAME KEY `uq_votes_usuario_anio` TO `uq_votos_usuario_grupo`,
--   RENAME KEY `idx_votos_anio`        TO `idx_votos_grupo`,
--   RENAME KEY `idx_votes_proyecto`    TO `idx_votes_subgrupo`;
--
-- ALTER TABLE `proyectos`
--   CHANGE COLUMN `anio_id` `grupo_id` INT UNSIGNED NOT NULL,
--   RENAME KEY `fk_proyectos_anio`  TO `fk_subgrupos_grupo`,
--   RENAME KEY `idx_proyectos_anio` TO `idx_subgrupos_grupo`;
--
-- ALTER TABLE `anios`
--   RENAME KEY `uq_anios_nombre` TO `uq_grupos_nombre`;
--
-- RENAME TABLE `proyectos` TO `subgrupos`, `anios` TO `grupos`;
-- ===========================================================================

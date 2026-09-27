-- ============================================================================
-- Reset total de la base: BORRA TODOS LOS DATOS.
--
-- Necesario cuando el schema quedo con el encoding roto (columnas y textos
-- con "Ã±" en lugar de "ñ"). Uso tipico durante desarrollo:
--
--   docker compose down
--   rm -rf dump/            # borra el volumen de MySQL
--   docker compose up -d     # el init aplica schema.sql de nuevo
--
-- O, sin tocar el volumen, sobre la base ya inicializada:
--
--   docker compose exec -T db mysql -uroot -p"$MYSQL_ROOT_PASSWORD" < database/reset.sql
--   docker compose exec -T db mysql -uroot -p"$MYSQL_ROOT_PASSWORD" < database/schema.sql
--
-- El orden importa: reset primero, schema despues.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `votos`;
DROP TABLE IF EXISTS `proyectos`;
DROP TABLE IF EXISTS `usuarios`;
DROP TABLE IF EXISTS `email_config`;
DROP TABLE IF EXISTS `configuracion_votacion`;
DROP TABLE IF EXISTS `anios`;
DROP TABLE IF EXISTS `roles`;
SET FOREIGN_KEY_CHECKS = 1;

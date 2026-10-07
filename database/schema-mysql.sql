-- ---------------------------------------------------------------------------
-- Schema de MySQL 5.7, el que tenia el proyecto antes de la migracion.
--
-- ESTE ARCHIVO NO LO USA NADA. docker-compose.yml corre
-- Dockerfiles/postgres/ y database/schema.sql, que es el de Postgres.
--
-- esta aca por dos razones:
--
--   1. Que la migracion sea reversible. `docker compose down -v` borra el
--      volumen de Postgres y con el los datos; este archivo permite
--      reconstruir la base vieja desde cero si hay que volver atras.
--
--   2. Porque el schema MySQL NUNCA estuvo en el repo. database/schema.sql era
--      un DIRECTORIO VACIO y docker-compose.yml lo montaba igual, asi que el
--      unico ejemplar del schema vivia en el datadir de ./dump/, sin versionar.
--      El que se conoce hoy se reconstruyo a mano desde un export de phpMyAdmin.
--      Por eso ahora esta en git, en los dos motores.
--
-- La contraparte de Postgres esta en database/schema.sql y sus comentarios
-- explican las diferencias de tipo (SERIAL, timestamptz, smallint, citext).
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_nombre` (`nombre`);

INSERT INTO `roles` (`id`, `nombre`) VALUES
(1, 'admin'),
(2, 'usuario');

-- --------------------------------------------------------

CREATE TABLE `anios` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `anios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_anios_nombre` (`nombre`),
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

INSERT INTO `anios` (`id`, `nombre`) VALUES
(1, '1er Año'),
(2, '2do Año'),
(3, '3er Año');

-- --------------------------------------------------------

-- El año de la edicion. No es `anios`, que tiene los años de cursada.
CREATE TABLE `anio_votacion` (
  `id` int(10) UNSIGNED NOT NULL,
  `anio` smallint(5) UNSIGNED NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '0',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `anio_votacion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_anio_votacion_activo` (`activo`,`id`),
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

-- --------------------------------------------------------

CREATE TABLE `configuracion_votacion` (
  `id` int(10) UNSIGNED NOT NULL,
  `inicio_votacion` datetime NOT NULL,
  `fin_votacion` datetime NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '0',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `configuracion_votacion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cfg_activo_id` (`activo`,`id`),
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

-- --------------------------------------------------------

CREATE TABLE `email_config` (
  `id` int(10) UNSIGNED NOT NULL,
  `clave` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `email_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email_config_clave` (`clave`),
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

-- OJO: el password de SMTP NO va aca. Sale de aca, en claro, y este archivo
-- esta en git. Se carga a mano en la base.
INSERT INTO `email_config` (`id`, `clave`, `valor`, `activo`) VALUES
(1, 'MAIL_HOST', 'smtp.gmail.com', 1),
(2, 'MAIL_PORT', '587', 1),
(3, 'MAIL_ENCRYPTION', 'tls', 1),
(4, 'MAIL_USERNAME', 'prueba3530@gmail.com', 1),
(5, 'MAIL_PASSWORD', NULL, 1),
(6, 'MAIL_FROM_EMAIL', 'prueba3530@gmail.com', 1);

-- --------------------------------------------------------

-- Lo que se vota. Antes se llamaba `subgrupos`.
CREATE TABLE `proyectos` (
  `id` int(10) UNSIGNED NOT NULL,
  `anio_id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `proyectos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_subgrupos_grupo` (`anio_id`,`activo`),
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22,
  ADD CONSTRAINT `fk_subgrupos_grupo` FOREIGN KEY (`anio_id`) REFERENCES `anios` (`id`) ON UPDATE CASCADE;

-- --------------------------------------------------------

CREATE TABLE `usuarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token` char(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '0',
  `rol_id` int(10) UNSIGNED NOT NULL DEFAULT '2',
  `es_admin` tinyint(1) NOT NULL DEFAULT '0',
  `votos_1er_ano` tinyint(1) NOT NULL DEFAULT '0',
  `votos_2do_ano` tinyint(1) NOT NULL DEFAULT '0',
  `votos_3er_ano` tinyint(1) NOT NULL DEFAULT '0',
  `fecha_registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- El UNIQUE de email es case-insensitive solo por el COLLATE
-- utf8mb4_unicode_ci. En Postgres el default es case-SENSITIVE, y por eso
-- `usuarios.email` paso a ser citext: sin eso, `Juan@x.com` y `juan@x.com`
-- entran como dos cuentas.
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_usuarios_email` (`email`),
  ADD KEY `idx_usuarios_rol` (`rol_id`),
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9,
  ADD CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;

-- --------------------------------------------------------

CREATE TABLE `votos` (
  `id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `proyecto_id` int(10) UNSIGNED NOT NULL,
  `anio_id` int(10) UNSIGNED NOT NULL,
  `fecha_voto` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- uq_votos_usuario_grupo es lo que hacia imposible el voto doble en el mismo
-- anio. Es la misma restriccion que en Postgres quedo como uq_votos_usuario_anio.
ALTER TABLE `votos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_votos_usuario_grupo` (`usuario_id`,`anio_id`),
  ADD KEY `idx_votos_proyecto` (`proyecto_id`),
  ADD KEY `idx_votos_anio` (`anio_id`),
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  ADD CONSTRAINT `fk_votos_grupo` FOREIGN KEY (`anio_id`) REFERENCES `anios` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_votos_subgrupo` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_votos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

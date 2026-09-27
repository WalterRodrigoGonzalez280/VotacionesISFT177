-- ============================================================================
-- VotacionesISFT177 - esquema MySQL 5.7
--
-- Reconstruido a partir de las queries reales del codigo:
--   www/conexion/conexion.php, www/api.php, www/index.php, www/gracias.php,
--   www/login/{login,funciones_login,recuperar}.php,
--   www/registro/{registro_validar,activar,generar_token}.php,
--   www/phpMail/enviar_correo.php
--
-- Es idempotente: se puede correr mas de una vez sin romper nada.
--
-- IMPORTANTE: el `SET NAMES utf8mb4` de la primera linea no es opcional.
-- Cuando este archivo lo ejecuta el entrypoint de la imagen mysql:5.7
-- (docker-entrypoint-initdb.d), el cliente `mysql` conecta con el charset
-- por defecto del server, que en 5.7 es latin1. Sin esta linea, los bytes
-- UTF-8 de "1er Año" y de las columnas `votos_1er_ano` se interpretan como
-- latin1 y se guardan doble-codificados: "1er AÃ±o" y `votos_1er_aÃ±o`.
-- Con esa columna corrupta, api.php:113 / index.php:52 / gracias.php:20
-- fallan con "Unknown column" y la votacion entera no funciona.
-- ============================================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `votacion_db`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- El CREATE de arriba es no-op si la base ya existe (la crea la imagen a partir
-- de MYSQL_DATABASE, y en MySQL 5.7 eso la deja en latin1). El ALTER si
-- corrige el charset de una base preexistente.
ALTER DATABASE `votacion_db`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `votacion_db`;

-- ---------------------------------------------------------------------------
-- roles  (login.php:34 usa `rol_id = 1` => admin)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(50)  NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `roles` (`id`, `nombre`) VALUES
  (1, 'admin'),
  (2, 'usuario');

-- ---------------------------------------------------------------------------
-- usuarios
--
-- OJO: `es_admin` y `rol_id` coexisten y hoy NO estan sincronizados.
--   login.php:34      decide admin por `rol_id`
--   api.php:25        lee `es_admin` de la base
-- Al dar de alta un admin hay que setear las DOS columnas.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`         VARCHAR(50)   NOT NULL,
  `email`          VARCHAR(150)  NOT NULL,
  `password`       VARCHAR(255)  DEFAULT NULL,   -- NULL hasta activar la cuenta
  `token`          CHAR(4)       DEFAULT NULL,   -- codigo de 4 digitos, NULL al activar
  `activo`         TINYINT(1)    NOT NULL DEFAULT 0,
  `rol_id`         INT UNSIGNED  NOT NULL DEFAULT 2,
  `es_admin`       TINYINT(1)    NOT NULL DEFAULT 0,
  `votos_1er_ano`  TINYINT(1)    NOT NULL DEFAULT 0,
  `votos_2do_ano`  TINYINT(1)    NOT NULL DEFAULT 0,
  `votos_3er_ano`  TINYINT(1)    NOT NULL DEFAULT 0,
  `fecha_registro` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_email` (`email`),
  KEY `idx_usuarios_rol` (`rol_id`),
  CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`rol_id`)
    REFERENCES `roles` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- anios  (los IDs son FIJOS: la app valida `anio_id IN (1,2,3)`)
--   admin.php:128-130
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `anios` (
  `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_anios_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `anios` (`id`, `nombre`) VALUES
  (1, '1er Año'),
  (2, '2do Año'),
  (3, '3er Año');

-- ---------------------------------------------------------------------------
-- proyectos  (lo que efectivamente se vota)
-- `activo` es baja logica: api.php:379
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `proyectos` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `anio_id`  INT UNSIGNED NOT NULL,
  `nombre`   VARCHAR(150) NOT NULL,
  `activo`   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_proyectos_anio` (`anio_id`, `activo`),
  CONSTRAINT `fk_proyectos_anio` FOREIGN KEY (`anio_id`)
    REFERENCES `anios` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- votos
-- `uq_votos_usuario_anio` garantiza 1 voto por anio a nivel de motor, como
-- respaldo del check que hace la app en api.php:124.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `votos` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`  INT UNSIGNED NOT NULL,
  `proyecto_id` INT UNSIGNED NOT NULL,
  `anio_id`     INT UNSIGNED NOT NULL,
  `fecha_voto`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_votos_usuario_anio` (`usuario_id`, `anio_id`),
  KEY `idx_votos_proyecto` (`proyecto_id`),
  KEY `idx_votos_anio` (`anio_id`),
  CONSTRAINT `fk_votos_usuario` FOREIGN KEY (`usuario_id`)
    REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_votos_proyecto` FOREIGN KEY (`proyecto_id`)
    REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_votos_anio` FOREIGN KEY (`anio_id`)
    REFERENCES `anios` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- configuracion_votacion
-- Solo una fila con activo=1; se lee con `ORDER BY id DESC LIMIT 1`.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `configuracion_votacion` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inicio_votacion` DATETIME NOT NULL,
  `fin_votacion`    DATETIME NOT NULL,
  `activo`          TINYINT(1)   NOT NULL DEFAULT 0,
  `creado_en`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cfg_activo_id` (`activo`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ventana abierta: el admin la ajusta desde el panel (api.php:546).
INSERT IGNORE INTO `configuracion_votacion`
  (`id`, `inicio_votacion`, `fin_votacion`, `activo`)
VALUES
  (1, '2026-01-01 00:00:00', '2099-12-31 23:59:59', 1);

-- ---------------------------------------------------------------------------
-- email_config  (SMTP - leer enviarTokenPorEmail() en www/phpMail/enviar_correo.php)
--
-- RELLENAR MAIL_USERNAME / MAIL_PASSWORD / MAIL_FROM_EMAIL con una
-- "app password" de Gmail. Sin esto, el registro y la recuperacion de
-- contraseña fallan (ver nota de bug en README.md).
--
-- SITE_URL es OPCIONAL y es lo unico que no es SMTP: es el origen del sitio
-- (http://localhost:4200 en desarrollo) y sirve para que el correo incluya el
-- boton "Activar mi cuenta". Vacio = el correo sale sin links, que es como
-- venia funcionando.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_config` (
  `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `clave`  VARCHAR(100)  NOT NULL,
  `valor`  VARCHAR(255)  DEFAULT NULL,
  `activo` TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email_config_clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `email_config` (`clave`, `valor`, `activo`) VALUES
  ('MAIL_HOST',       'smtp.gmail.com', 1),
  ('MAIL_PORT',       '587',            1),
  ('MAIL_ENCRYPTION', 'tls',            1),
  ('MAIL_USERNAME',   '',               1),
  ('MAIL_PASSWORD',   '',               1),
  ('MAIL_FROM_EMAIL', '',               1),
  ('SITE_URL',        '',               1);

-- ---------------------------------------------------------------------------
-- Crear el admin: registrate por la UI y despues corré esto.
-- Actualiza las DOS columnas de admin (ver nota arriba).
--   UPDATE usuarios SET rol_id = 1, es_admin = 1, activo = 1
--    WHERE email = 'tu@correo.com';
-- ---------------------------------------------------------------------------

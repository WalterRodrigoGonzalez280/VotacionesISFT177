-- =============================================================================
-- Hardening de tokens de activación (V1 del audit)
--
-- Qué hace: el token pasa de varchar(4) a varchar(6) y se agregan tres
-- columnas para darle vida útil y límite de intentos al código de activación
-- y de recuperación de contraseña.
--
-- Por qué: el código de 4 dígitos generado con rand() no expiraba ni tenía
-- límite de intentos, con lo cual cualquier persona podía tomar una cuenta
-- (incluida admin) probando códigos. El hardening pasa el código a CSPRNG
-- (random_int), con 15 minutos de vida y máximo 5 intentos incorrectos.
--
-- Cómo aplicarla: contra el Postgres corriendo en Docker, desde la raíz del
-- repo (el contenedor se llama "db" en docker-compose.yml):
--
--   docker exec -i db psql -U votacion_user -d votacion_db \
--     < database/migraciones/2026-10-12_hardening_tokens.sql
--
-- IMPORTANTE: esta migración es la parte de esquema del hardening de tokens
-- (V1 del audit). Debe aplicarse ANTES de deployar el código nuevo de
-- www/core/generar_token.php, www/auth/activar.php, www/auth/recuperar.php y
-- www/auth/registro_validar.php: esas pantallas hacen SELECT/INSERT de las
-- columnas nuevas y sin ellas fallarían.
--
-- Idempotente: se puede ejecutar más de una vez sin romper nada. No toca
-- datos existentes: los tokens vigentes quedan como están y sin fecha de
-- expiración seteada.
-- =============================================================================

-- Por que bloques DO y no ADD COLUMN IF NOT EXISTS: esa forma recién llegó en
-- PostgreSQL 9.6, y esta imagen es postgres:9.5.25-alpine (ver
-- Dockerfiles/postgres/Dockerfile). En 9.5 el `IF NOT EXISTS` de ADD COLUMN no
-- parsea y falla con "syntax error at or near NOT". La variante idempotente
-- compatible con 9.5 es chequear information_schema.columns antes de agregar.

ALTER TABLE usuarios ALTER COLUMN token TYPE varchar(6);

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.columns
     WHERE table_schema = 'public'
       AND table_name  = 'usuarios'
       AND column_name = 'token_expira_en'
  ) THEN
    ALTER TABLE usuarios ADD COLUMN token_expira_en timestamptz;
  END IF;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.columns
     WHERE table_schema = 'public'
       AND table_name  = 'usuarios'
       AND column_name = 'token_intentos'
  ) THEN
    ALTER TABLE usuarios ADD COLUMN token_intentos smallint NOT NULL DEFAULT 0;
  END IF;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.columns
     WHERE table_schema = 'public'
       AND table_name  = 'usuarios'
       AND column_name = 'token_ultimo_envio'
  ) THEN
    ALTER TABLE usuarios ADD COLUMN token_ultimo_envio timestamptz;
  END IF;
END $$;

# Feature: Hardening de tokens de activación y proteccón de resultados

## Objetivo
Cerrar dos vulnerabilidades reportadas y verificadas en el audit:
- **V1 (ALTA)**: takeover de cuentas por fuerza bruta del código de 4 dígitos sin expiración ni límite de intentos.
- **V2 (ALTA)**: `obtener_resultados` (www/api/index.php) responde sin sesión y filtra conteos de votos en vivo.

## Por qué
El código de activación/recuperación sale de `rand(0,999999)`-equivalente de 4 dígitos, se compara sin rate limiting ni expiración: cualquier persona puede tomar una cuenta (incluida admin) en minutos. Además cualquier anónimo consulta los votos por proyecto.

## Alcance (autorizado por el usuario: "hazlo" sobre recomendación #1 y #2)
- **T1 — Token hardening**: código de 6 dígitos con `random_int` (CSPRNG), expiración 15 min, max 5 intentos por código (al agotarse se invalida), throttle de reenvío ≥60s por usuario, comparación con `hash_equals`. Columnas nuevas en `usuarios`: `token_expira_en`, `token_intentos`, `token_ultimo_envio`; `token` se ensancha a varchar(6).
- **T2 — Migración SQL**: archivo en `database/migraciones/` (idempotente), para aplicar a mano en el Postgres corriendo (sin acceso docker desde este entorno).
- **T3 — Obtener resultados con sesión**: exige `$_SESSION['usuario_id']`; los conteos (`votos`) solo se devuelven a admin o cuando la votación está cerrada. Ajustar el frontend si la forma de la respuesta cambia.

## Fuera de alcance (quedan pendientes, no autorizados en esta vuelta)
CSRF (#3), revalidación de `es_admin` contra DB (#4), enumeración de usuarios/throttling de login (#5), guards muertos de auto-borrado (#6), config de mail SMTP (#1 de la lista de Gmail: MAIL_PASSWORD NULL sin UI).

## Restricciones
- No tocar `database/schema.sql` ni `database/datos.sql` (son directorios vacíos con dueño root en el worktree; el esquema base vive en git HEAD).
- No commitear nada que no sean los archivos de esta feature.
- Estilo de código y mensajes: español, igual que el resto del codebase.
- Sin runtime PHP disponible localmente (sin php, sin docker, sin sudo): verificación = lectura estructural + revisión; lint `php -l` queda pendiente para el usuario.

## Criterios de aceptación
- Código de 6 dígitos, CSPRNG, con expiración e intentos persistidos en DB.
- 5 códigos incorrectos invalidan el token; reenvío <60s bloqueado con mensaje claro.
- `obtener_resultados` sin sesión → error de acceso; con sesión no-admin → sin conteos; admin o cerrada → con conteos.
- Migración SQL idempotente y aplicable a la DB existente.

## Enmienda T1 (decisión del orquestador, tras análisis del writer)
- **Nunca NULL el token al rechazar** (expirado o 5 intentos agotados): eso bajaría el formulario de activar a solo-password y permitiría takeover sin código. Invalidación = `token_expira_en = now(), token_intentos = 0`; el NULL queda solo en el UPDATE de éxito.
- Superficies agregadas por blockers reales: `www/assets/js/validate.js` (regla `digits4` y `.slice(0,4)` cortan cualquier código de 6 dígitos del lado cliente), `www/auth/registro_validar.php` (genera el token inline con `rand(0,9999)`), y copy "4 dígitos" en `www/auth/registro.php`, `www/public/index.php`, `www/phpMail/enviar_correo.php` (solo la línea de copy), `www/assets/css/auth.css`.
- Borrar `validarToken()` (www/core/generar_token.php): código muerto sin chequeo de expiración.

## Progreso
- [x] Exploración y verificación de hallazgos (auditoría previa)
- [ ] T1 Token hardening (en curso, con enmienda)
- [x] T2 Migración SQL (database/migraciones/2026-10-12_hardening_tokens.sql)
- [x] T3 Sesión/conteos en obtener_resultados (api/index.php:166-237; admin.js es único consumidor, sin cambio de forma)

## Evidencia de commits
(pendiente)

## Siguiente paso
Aplicar la migración en el Postgres corriendo y probar el flujo registro → activación y recuperación.

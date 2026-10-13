# Logo reutilizable + favicon cableado

## Objetivo

Que una persona pueda reemplazar el logo del sitio tocando UN solo archivo, y que
todas las variantes que usa la web queden regeneradas y optimizadas sin editar
PHP.

## Problema

Los 8 assets de `www/assets/Logo/` están referenciados por nombre en 7 PHP con
`srcset` escrito a mano (header, footer, login, registro, activar, recuperar,
despedida) y el de 256 px lo usa `phpMail/enviar_correo.php`. Cambiar el logo hoy
existe solo como tarea manual de redimensionar y comprimir 8 archivos con criterio
propio: nada garantiza que queden cuadrados (sostienen `width/height=480x480` y el
CLS), ni dentro del presupuesto de bytes que ya se midió, ni con los nombres
correctos.

Aparte: `favicon-32.png` y `favicon-180.png` existen pero **no hay ningún
`<link rel="icon">` en el proyecto**, así que el navegador siempre pide
`/favicon.ico` y un cambio de favicon no se llega a ver.

## Por qué así

- Los nombres no se tocan: son contrato con el `srcset`. El script genera exactamente
  los archivos que ya existen, así que ningún PHP cambia por un cambio de logo.
- PNG contra `.webp`: ya está medido y documentado (README "Verificación" y
  `site-header.php:42-62`) — para este escudo, de 256 colores planos, el PNG le gana
  al `.webp` en todos los tamaños (23 KB contra 35). Por eso el pipeline es solo PNG.
- Paleta de 8 bits: los actuales de 96/160/192/256 pesan 3.7-9.7 KB porque son PNG
  de paleta; un RGBA completo del mismo 96 px pesa 14 KB. El script genera las dos
  variantes y se queda con la más chica.
- `.webp` huérfano: `logoisft177.webp` no está referenciado en ningún lado y queda
  desfasado en cuanto cambie el logo.

## Alcance autorizado

- Crear `tools/gen-logo.sh`, `tools/.gitkeep` si hace falta, `www/assets/Logo/logo-source.png`
- Crear `www/includes/favicon.php` y sumar su include en las páginas con `<head>`
- Documentación en `README.md`
- No tocar `database/`, `.gitignore`, ni los archivos sucios preexistentes
  (`www/auth/registro_validar.php`, `database/migraciones/2026-10-12_hardening_tokens.sql`)

## Cambio de alcance (2026-10-12)

El usuario pidió **borrar `tools/`** y mantuvo todo lo demás. T1 queda
cancelada: el pipeline de generación de variantes no existe. T2 se reduce a
eliminar `logoisft177.webp`; el maestro `logo-source.png` se creó y después se
borró por pedido del usuario, junto con la sección del README que documentaba el
script. Lo que queda del alcance original es el favicon cableado y los 7 PNG
regenerados con el script, que se quedan en el repo.

Los 7 PNG regenerados con el script se quedan: mismas dimensiones, mismos
nombres, 6 de 7 más livianos que los hechos a mano (el de 256 px, +772 B).

## Checklist

- [x] T1 — CANCELADA por decisión del usuario: `tools/gen-logo.sh` implementado
      y verificado, y borrado sin commitear.
- ~~T1 original: `tools/gen-logo.sh`: valida fuente (existe, cuadrada, ≥480 px), genera
      `logoisft177-{96,160,192,256,480}.png` + `favicon-32.png` + `favicon-180.png`
      con `-strip`, eligiendo entre RGBA y paleta la variante más chica, y falla si
      alguna variante se pasa del presupuesto de bytes (`--force` para forzar).
- [ ] T2 — `www/assets/Logo/logo-source.png`: maestro a partir del 480 actual, y
      `logoisft177.webp` eliminado (no referenciado, queda desfasado).
- [ ] T3 — `www/includes/favicon.php` + include en todas las páginas con `<head>`
      real (verificar con grep cuáles emiten `<head>`; `phpMail/` es HTML de correo y
      queda fuera).
- [ ] T4 — `README.md`: sección "Logo y favicon" con el flujo, el presupuesto de
      bytes y el porqué de PNG sobre `.webp`; actualizar el árbol de `Estructura`.
- [ ] T5 — commit work-unit con Conventional Commit, solo archivos de esta feature.

## Criterios de aceptación

1. `bash tools/gen-logo.sh` regenera los 7 archivos y sale con código 0, sin tocar
   ningún PHP.
2. Todos los `identify` dan cuadrados y con las dimensiones esperadas.
3. Ninguna variante supera su presupuesto de bytes sin `--force`.
4. Una fuente no cuadrada o de menos de 480 px hace fallar el script con mensaje claro.
5. Cada página con `<head>` linkea los dos favicons con ruta absoluta `/assets/Logo/`.
6. `git diff --stat` del commit contiene solo los archivos de esta feature.

## Verificación

```bash
bash -n tools/gen-logo.sh                       # sintaxis
bash tools/gen-logo.sh                          # happy path sobre logo-source.png
identify -format '%f %wx%h\n' www/assets/Logo/logoisft177-*.png www/assets/Logo/favicon-*.png
convert -size 100x50 xc:red /tmp/no-cuadrada.png && bash tools/gen-logo.sh /tmp/no-cuadrada.png   # tiene que fallar
grep -rLl 'favicon' --include='*.php' www/auth www/public www/admin   # no debe listar páginas con <head>
git diff --stat
```

## Excepción de test-first

No hay framework de tests en el repo (sin PHPUnit, sin bats, sin `tests/`) y `gen-logo.sh`
es tooling, no comportamiento de la app. La verificación proporcional es funcional:
happy path + casos negativos (fuente inexistente, no cuadrada, <480 px) + lectura
estructural de los `<link rel="icon">`.

## Ruta por tarea

- T1: delegated (writer) — 1 archivo no trivial nuevo.
- T2: inline — `cp` de un archivo.
- T3: delegated junto con T1 — 1 archivo nuevo + ~9 ediciones mecánicas de una línea.
- T4: delegated junto con T1 — README, requiere leer el estilo del documento.
- T5: inline — `git commit` acotado.

## Progreso

- [x] Exploración: inventario de referencias, presupuestos de bytes, favicon huerfano.
- [x] Implementación — favicon cableado en 10 PHP, README, 7 PNG regenerados,
      `.webp` eliminado. T1 (script) cancelada y borrada por pedido del usuario.
- [x] Verificación — include de favicon presente en toda página con `<head>` real;
      dimensiones idénticas; 6 de 7 PNG más livianos que los hechos a mano.
- [x] Commit — `feat(assets): cablea los favicons y optimiza las variantes del
      logo`, en `dev5`. Revisión nativa **no disponible**: `gentle-ai review assess`
      devuelve `high / unassessable` porque el runtime activo (opencode) no entra
      en el review con recibo inmutable (soportados: claude-code, codex). Queda
      registrado como pendiente, no como aprobado.

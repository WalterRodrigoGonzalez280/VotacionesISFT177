# Votaciones ISFT 177 — Descripción del Sitio

## Qué es

Sistema de votación para la **Exposición de Proyectos de Prácticas Profesionalizantes** del **Instituto Superior de Formación Técnica N° 177** (ISFT 177). Permite a la comunidad educativa elegir los proyectos destacados de cada año de la carrera Técnico en Análisis de Sistemas.

## Propósito

- Que alumnos, docentes, autores y participantes voten **un proyecto por año** (1.º, 2.º y 3.º año).
- Que la organización tenga control total sobre el proceso: fechas, proyectos participantes y resultados.
- Garantizar **un voto por persona por año**, sin posibilidad de cambiarlo ni repetirlo.

---

## Audiencias y flujos

### Público general / Votantes
1. **Landing pública** — Muestra el estado de la votación (abierta, pendiente, finalizada), fechas y cómo participar.
2. **Registro de cuenta** — Nombre + correo. Se envía un código de 4 dígitos por correo para activar la cuenta.
3. **Activación** — Ingreso del código y definición de contraseña.
4. **Login** — Acceso con correo y contraseña. Recuperación de contraseña por correo.
5. **Página de votación** — Visible **sin iniciar sesión**: se ven los proyectos de cada año. Para votar hay que loguearse.
6. **Confirmación de voto** — Diálogo nativo que pide confirmar antes de emitir. Advierte que el voto **no se puede cambiar ni repetir**.
7. **Gracias** — Pantalla de confirmación tras votar los tres años.
8. **Despedida** — Pantalla final con cuenta regresiva cuando la votación cierra.

### Administradores
Acceso al **Panel de administración** con cuatro secciones:

| Sección | Qué permite |
|---------|-------------|
| **Usuarios** | Ver lista, resetear votos de un usuario, borrar usuario, limpiar votos huérfanos, borrar todos los votos de golpe. |
| **Grupos (Proyectos)** | Agregar, editar, activar/desactivar y eliminar proyectos por año. |
| **Ganadores** | Cálculo y visualización de resultados por año (proyecto con más votos). |
| **Configuración** | Definir **ventana de votación** (inicio y fin). Solo una configuración activa a la vez. |

> El panel **no** permite votar. El administrador ve la votación en modo solo lectura.

---

## Reglas de votación

- **Tres votos en total**: uno por cada año (1.º, 2.º, 3.º).
- **Un voto por proyecto por año**: no se puede votar dos veces el mismo proyecto ni cambiar el voto después de confirmar.
- **Visibilidad de contadores**: Los votos **no se muestran** al público mientras la votación está abierta (evita sesgo). Solo los ve el administrador.
- **Ventana de tiempo**: La votación solo está habilitada entre la fecha/hora de inicio y la de fin configuradas por el admin.
- **Año de edición**: El admin define qué año lectivo se está votando (p. ej. 2024, 2025). Es independiente de los años de cursada (1.º/2.º/3.º).

---

## Cuentas y roles

| Rol | Qué puede hacer |
|-----|-----------------|
| **Votante** | Registrarse, activar cuenta, loguearse, votar una vez por año, ver sus votos emitidos. |
| **Administrador** | Todo lo anterior + panel de gestión completo (usuarios, proyectos, resultados, fechas). |

La promoción a administrador se hace **manualmente en la base de datos** (no hay registro de admin en la interfaz).

---

## Experiencia de usuario y accesibilidad

- **Tema claro/oscuro**: Respeta la preferencia del sistema y permite elegir manualmente. La elección persiste en el navegador.
- **Sin destello de tema**: El tema se aplica antes del primer pintado (script inline en el `<head>`).
- **Navegación accesible**: Skip link, landmarks ARIA, foco visible, orden lógico de tabulación.
- **Menú móvil**: Hamburguesa colapsable por debajo de 768 px. Sin JavaScript el menú queda desplegado (no hay control roto).
- **Botón de tema en el menú**: Siempre funcional porque el módulo se carga desde el parcial compartido.
- **Diálogos nativos** (`<dialog>`): Confirmación de voto y formularios del admin. Atrapan foco, cierran con Escape, accesibles sin JS extra.
- **Contraste medido**: Todas las combinaciones de texto/fondo cumplen **WCAG AA (≥ 4,5:1)** en ambos temas. Paleta heráldica 60-30-10 (fondo oscuro, superficies medias, dorado de acento).
- **Tipografía**: Titulares en **Cinzel** (capitular romana, tono heráldico), cuerpo en **Lato**. Fuentes servidas localmente (WOFF2), sin depender de terceros. Fallback a fuentes del sistema si falla la carga.
- **Iconos**: Sprite SVG incrustado (26 símbolos). Cero emojis en la interfaz.
- **Layout**: Mobile-first, Grid y Flexbox, unidades relativas (`rem`, `em`, `ch`, `%`). Sin estilos en línea ni `!important`.

---

## Correo electrónico

- **Activación de cuenta**: Código de 4 dígitos.
- **Recuperación de contraseña**: Código de 4 dígitos + enlace para definir nueva contraseña.
- **Plantilla**: Usa la misma paleta del tema claro (el correo no puede leer variables CSS ni preferencia de tema del usuario). Colores transcritos con su token de origen para mantenimiento.
- **Configuración SMTP**: Parámetros en base de datos (`email_config`) o variables de entorno. Requiere contraseña de aplicación (p. ej. Gmail).

---

## Datos y estructura lógica

**Entidades principales**

| Entidad | Qué representa |
|---------|----------------|
| **Años de cursada** | 1.º, 2.º y 3.º año (fijos, tres en total). |
| **Año de edición** | Año lectivo de la votación (configurable, uno solo activo). |
| **Proyectos** | Lo que se vota. Pertenecen a un año de cursada y a un año de edición. |
| **Votos** | Uno por usuario y año de cursada. Unicidad garantizada a nivel de base de datos. |
| **Configuración de votación** | Ventana temporal (inicio/fin) y estado activo. |
| **Usuarios** | Cuentas con nombre, correo, contraseña (hash), rol, flag de admin, votos emitidos por año. |

---

## Seguridad y buenas prácticas aplicadas

- Contraseñas con **bcrypt** (hash + salt).
- Tokens de activación/recuperación de **4 dígitos**, vida corta, invalidados tras uso.
- **CSRF**: Tokens en formularios y validados en backend.
- **SQL**: Consultas parametrizadas (PDO), sin concatenación.
- **XSS**: Salida escapada con `htmlspecialchars` en todo HTML. Atributos `data-*` sanitizados.
- **Admin**: Verificación de sesión y flag `es_admin` en cada acción sensible.
- **API**: Endpoint único (`api.php`) con `action=...`. Rechaza votos sin sesión (401). No expone hashes ni datos sensibles.
- **Base de datos**: Restricciones de unicidad y claves foráneas que impiden votos duplicados o huérfanos.

---

## Administración de la votación (ciclo de vida)

1. **Preparación** (admin):
   - Cargar proyectos por año de cursada.
   - Definir año de edición.
   - Configurar fecha/hora de inicio y fin.
   - Verificar correo SMTP.
2. **Apertura**: La landing y la página de votación muestran "Votación abierta".
3. **Durante la votación**:
   - Público ve proyectos (sin contadores).
   - Votantes logueados emiten sus tres votos.
   - Admin monitorea en panel (con contadores y resultados en vivo).
4. **Cierre**: Automático al llegar la fecha/fin. Página de votación muestra "Votación finalizada" y redirige a pantalla de despedida con cuenta regresiva.
5. **Resultados**: Pestaña "Ganadores" del panel muestra proyecto con más votos por año.

---

## Decisiones de diseño destacadas

| Decisión | Por qué |
|----------|---------|
| **Landing no muestra ganadores** | Evita sesgar votos tardíos; los resultados pertenecen al panel. |
| **Contadores ocultos al público** | El voto no debe influenciarse por popularidad momentánea. |
| **Votación visible sin login** | Transparencia: cualquiera ve qué se vota; el login solo para emitir. |
| **Confirmación en `<dialog>` nativo** | Accesibilidad garantizada (foco atrapado, Escape) sin librerías. |
| **Tema aplicado antes del paint** | Elimina el destello (FOUC) de cambio de tema. |
| **Fuentes locales** | Independencia de terceros; misma renderización en todos los entornos. |
| **Iconos SVG sprite** | Un solo request, sin fuentes de iconos, cero emojis. |
| **Un solo archivo CSS por página** | Reduce requests bloqueantes; medido con Lighthouse (mediana de 3 corridas). |
| **Cache-Control en estáticos** | `must-revalidate` + `max-age` razonable: los cambios se ven rápido sin pedir archivo en cada visita. |
| **OPcache habilitado** | Mayor ahorro de CPU sin tocar código. |

---

## Lo que **no** hace el sitio

- No es un sistema de encuestas genérico: está acotado a **tres años fijos** y un año de edición a la vez.
- No permite votación anónima: requiere cuenta verificada por correo.
- No tiene roles intermedios (solo votante / admin).
- No envía notificaciones push ni en tiempo real.
- No tiene API pública documentada para terceros.

---

## Próximos pasos naturales (si se decide extender)

- Compresión HTTP (`mod_deflate`) para CSS/JS/HTML.
- Nombres de assets con hash (`tokens.<hash>.css`) → `Cache-Control: immutable` + `max-age` largo.
- Verificación automática de controles interactivos sin módulo JS (evitar botones muertos).
- Manejo de errores de base de datos visible al usuario (hoy algunos errores silencian y muestran estado vacío).
- Sincronizar `es_admin` y `rol_id` (hoy pueden desincronizarse).
- Tests automatizados de regresión visual (pixel-a-pixel contra versión de referencia).

---

## Resumen en una frase

> **Plataforma de votación acotada, accesible y auditable para la Exposición de Proyectos del ISFT 177: tres años, un voto por año, control total del admin, sin dependencias externas.**
<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');

$host   = $_ENV['DB_HOST'] ?? 'db';           
$puerto = $_ENV['DB_PORT'] ?? '5432';
$usuario = $_ENV['DB_USER'] ?? 'votacion_user';
$clave   = $_ENV['DB_PASS'] ?? 'develop2025';
$base    = $_ENV['DB_NAME'] ?? 'votacion_db';


if (empty($host) || empty($usuario) || empty($base)) {
    error_log("Configuración de base de datos incompleta");
    http_response_code(500);
    echo "Error de configuración de base de datos.";
    exit;
}

// Postgres no necesita charset en el DSN ni SET NAMES: el encoding se
// negocia con el servidor y los dos quedan en UTF8 sin que nadie lo pida.
// El `options` si hace falta, para el search_path: por defecto es
// `"$user", public`, asi que si el usuario de la base se llamara `public` (o
// existiera un schema con su nombre) las tablas se buscarian en el lugar
// equivocado. Fijarlo a `public` hace que la busqueda no dependa del usuario.
$dsn = "pgsql:host={$host};port={$puerto};dbname={$base};options='--search_path=public'";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 10
];

/**
 * La pagina que se ve cuando la base no responde.
 *
 * Es una funcion y no HTML suelto en el catch por dos cosas: para poder
 * probarla sin romper la base de verdad, y porque es la unica pagina del
 * sitio que no depende de la base: si no, no tendria ni de donde sacar el CSS.
 *
 * A la persona que esta usando el sitio no se le muestra NUNCA el error de la
 * base: dice el host, el puerto y a veces el usuario, y no le sirve de nada.
 * El detalle tecnico va al log. Antes ademas se diferenciaba por APP_ENV y en
 * desarrollo se imprimia el mensaje crudo; ahora es siempre el mismo texto.
 */
function paginaBaseCaida() {
    // El CSS vive en /css, pero esta pagina se sirve desde / o desde /login/.
    $ruta_script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $en_subcarpeta = strpos($ruta_script, '/', 1) !== false;
    $base_css = $en_subcarpeta ? '../css' : 'css';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>No pudimos conectar | ISFT 177</title>
    <link rel="stylesheet" href="<?= $base_css ?>/base.css">
</head>
<body>
    <main style="display:grid;place-items:center;min-block-size:100dvh;padding:var(--s-5)">
        <div class="card" role="alert" style="max-inline-size:26rem;text-align:center">
            <h1 class="auth__title">No pudimos conectar</h1>
            <p class="auth__lead">
                El sistema está temporalmente fuera de servicio.
            </p>
            <p class="auth__lead" style="margin-block-start:var(--s-3);font-size:var(--fs-sm)">
                Intentá de nuevo en unos minutos. Si sigue igual, avisá al
                responsable del sitio.
            </p>
        </div>
    </main>
</body>
</html>
<?php
}

/**
 * Pone la sesion en el horario de Argentina.
 *
 * Antes era `SET time_zone = '-03:00'`: un offset FIJO, que es el horario
 * estandar de Argentina pero no el de verano. El pais aplica hora de verano
 * (UTC-2) desde mediados de octubre a mediados de marzo, asi que durante esos
 * meses un offset fijo deja la base una hora atras de la que ve el usuario, y
 * no hay forma de que el propio dato avise: es una hora de diferencia, no un
 * error. Con el NOMBRE de la zona, el servidor resuelve el offset solo y el
 * cambio de horario pasa sin tocar una linea.
 *
 * El nombre solo funciona si las tablas de zona horaria de Postgres estan
 * cargadas, y en la imagen `postgres:9.5.25-alpine` eso hay que pedirlo
 * explicitamente con `apk add tzdata` (ver README, seccion "Zona horaria").
 * Por eso la funcion falla en vez de asumir: si el servidor no conoce la zona,
 * quien llama decide. Preferimos la zona con nombre y, si no esta, el respaldo
 * es el offset fijo de antes.
 *
 * Importante: el fallback NO es un error de conexion, asi que va en su propio
 * try. Si el `SET time_zone` se dejara caer en el catch de abajo, un problema
 * de zona horaria tiraria el sitio entero con un 503, que es una falta
 * desproporcionada para una hora de diferencia en un dato que casi nadie mira.
 */
function fijarZonaHoraria($pdo) {
    $pdo->exec("SET TIME ZONE 'America/Argentina/Buenos_Aires'");
}

try {
    $pdo = new PDO($dsn, $usuario, $clave, $options);

    try {
        fijarZonaHoraria($pdo);
    } catch (PDOException $e) {
        error_log('Zona horaria con nombre no disponible, se usa -03:00: ' . $e->getMessage());
        $pdo->exec("SET TIME ZONE '-03:00'");
    }

} catch (PDOException $e) {

    error_log("DB connection error: " . $e->getMessage());

    // 503 y no 500: la base caida es temporal, no un error de esta pagina.
    http_response_code(503);
    paginaBaseCaida();
    exit;
}

/**
 * El año de la exposición, que vive en la tabla `anio_votacion`.
 *
 * Antes estaba escrito a mano en el HTML ("Exposicion 2025") en dos lugares, y
 * cambiarlo era editar dos archivos. Ahora sale de la base y lo cambia el admin
 * desde la pestana "Configuracion".
 *
 * NO es la tabla `anios`: esa tiene los años de cursada (1.o, 2.o, 3.o).
 *
 * Si la consulta falla (tabla todavia no creada, base sin respuesta) devuelve
 * el año en curso en vez de tirar la pagina abajo: que una decoracion falte es
 * mucho menos grave que que el sitio no abra.
 */
function anioVotacion() {
    global $pdo;

    try {
        $stmt = $pdo->query(
            'SELECT anio FROM anio_votacion WHERE activo = 1 ORDER BY id DESC LIMIT 1'
        );
        $anio = $stmt->fetchColumn();

        if ($anio !== false && (int)$anio >= 2000 && (int)$anio <= 2100) {
            return (int)$anio;
        }
    } catch (PDOException $e) {
        error_log('Anio de votacion: ' . $e->getMessage());
    }

    return (int)date('Y');
}

function verificarConexion() {
    global $pdo;
    try {
        $pdo->query('SELECT 1');
        return true;
    } catch (PDOException $e) {
        error_log("Connection check failed: " . $e->getMessage());
        return false;
    }
}

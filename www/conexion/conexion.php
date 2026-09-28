<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');

$host   = $_ENV['DB_HOST'] ?? 'db';           
$puerto = $_ENV['DB_PORT'] ?? '3306';
$usuario = $_ENV['DB_USER'] ?? 'votacion_user';
$clave   = $_ENV['DB_PASS'] ?? 'develop2025';
$base    = $_ENV['DB_NAME'] ?? 'votacion_db';


if (empty($host) || empty($usuario) || empty($base)) {
    error_log("Configuración de base de datos incompleta");
    http_response_code(500);
    echo "Error de configuración de base de datos.";
    exit;
}

$dsn = "mysql:host={$host};port={$puerto};dbname={$base};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 10,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

/**
 * La pagina que se ve cuando la base no responde.
 *
 * Es una funcion y no HTML suelto en el catch por dos cosas: para poder
 * probarla sin romper la base de verdad, y porque es la unica pagina del
 * sitio que no depende de la base: si no, no tendria ni de donde sacar el CSS.
 *
 * A la persona que esta usando el sitio no se le muestra NUNCA el error de
 * MySQL: dice el host, el puerto y a veces el usuario, y no le sirve de nada.
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

try {
    $pdo = new PDO($dsn, $usuario, $clave, $options);
    $pdo->exec("SET time_zone = '-03:00'");

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

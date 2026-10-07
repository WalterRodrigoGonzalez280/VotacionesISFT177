<?php
session_start();
require_once 'conexion/conexion.php';

// Función para verificar si el usuario es administrador
function esAdmin() {
    return isset($_SESSION['es_admin']) && $_SESSION['es_admin'] === true;
}

// Manejar acciones via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // ACCIÓN: VERIFICAR USUARIO (para ambos roles)
    if ($_POST['action'] === 'verificar_usuario') {
        if (!isset($_SESSION['usuario_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Sesión no válida']);
            exit;
        }

        $usuario_id = (int)$_SESSION['usuario_id'];

        try {
            $stmt = $pdo->prepare("SELECT nombre, email, votos_1er_ano, votos_2do_ano, votos_3er_ano, es_admin FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
            } else {
                echo json_encode([
                    'success' => true,
                    'usuario' => $usuario,
                    'es_admin' => isset($_SESSION['es_admin']) && $_SESSION['es_admin'] === true
                ]);
            }
        } catch (PDOException $e) {
            error_log("Error verificando usuario: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al verificar usuario']);
        }
        exit;
    }

    // ACCIÓN: VOTAR (para usuarios)
    if ($_POST['action'] === 'votar') {
        if (!isset($_SESSION['usuario_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Debe iniciar sesión para votar']);
            exit;
        }

        $usuario_id = (int)$_SESSION['usuario_id'];
        $subgrupo_id = (int)$_POST['subgrupo_id'];
        $grupo_id = (int)$_POST['grupo_id'];

        if (!in_array($grupo_id, [1, 2, 3])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Grupo inválido']);
            exit;
        }

        // Verificar si la votación está abierta
        try {
            $stmt = $pdo->prepare("SELECT inicio_votacion, fin_votacion FROM configuracion_votacion WHERE activo = 1 ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $configuracion = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($configuracion) {
                $ahora = new DateTime();
                $inicio = new DateTime($configuracion['inicio_votacion']);
                $fin = new DateTime($configuracion['fin_votacion']);
                
                if ($ahora < $inicio) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'La votación aún no ha comenzado']);
                    exit;
                }
                
                if ($ahora > $fin) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'La votación ya ha finalizado']);
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log("Error verificando horario de votación: " . $e->getMessage());
        }

        try {
            $pdo->beginTransaction();

            // Verificar que el subgrupo existe y pertenece al grupo
            $stmt = $pdo->prepare("SELECT id FROM proyectos WHERE id = ? AND anio_id = ? AND activo = 1");
            $stmt->execute([$subgrupo_id, $grupo_id]);
            if (!$stmt->fetch()) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Subgrupo no válido']);
                exit;
            }

            // Verificar si el usuario ya votó en este grupo
            $campos_voto = [
                1 => 'votos_1er_ano',
                2 => 'votos_2do_ano',
                3 => 'votos_3er_ano'
            ];
            $campo_voto = $campos_voto[$grupo_id];

            $stmt = $pdo->prepare("SELECT $campo_voto FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $usuario = $stmt->fetch();

            if (!$usuario) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
                exit;
            }

            if ($usuario[$campo_voto] == 1) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ya has votado en este grupo']);
                exit;
            }

            // Actualizar el estado del voto en la tabla usuarios
            $stmt = $pdo->prepare("UPDATE usuarios SET $campo_voto = 1 WHERE id = ?");
            $stmt->execute([$usuario_id]);

            // Registrar voto
            $stmt = $pdo->prepare("INSERT INTO votos (usuario_id, proyecto_id, anio_id) VALUES (?, ?, ?)");
            $stmt->execute([$usuario_id, $subgrupo_id, $grupo_id]);

            // Verificar si el usuario completó sus 3 votos
            $stmt = $pdo->prepare("SELECT votos_1er_ano, votos_2do_ano, votos_3er_ano FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $usuario_actualizado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $votos_completos = false;
            if ($usuario_actualizado) {
                $votos_completos = ($usuario_actualizado['votos_1er_ano'] == 1 && 
                                    $usuario_actualizado['votos_2do_ano'] == 1 && 
                                    $usuario_actualizado['votos_3er_ano'] == 1);
            }

            $pdo->commit();
            echo json_encode([
                'success' => true, 
                'message' => 'Voto registrado correctamente',
                'votos_completos' => $votos_completos
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error en votación: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al procesar el voto']);
        }
        exit;
    }

    // ACCIÓN: OBTENER RESULTADOS (para usuarios)
    if ($_POST['action'] === 'obtener_resultados') {
        $usuario_id = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;

        try {
            $stmt = $pdo->prepare("
                SELECT s.id, s.nombre, s.anio_id AS grupo_id, g.nombre as grupo_nombre,
                       COUNT(v.id) as votos,
                       CASE WHEN uv.id IS NOT NULL THEN 1 ELSE 0 END as usuario_voto
                FROM proyectos s 
                JOIN anios g ON s.anio_id = g.id 
                LEFT JOIN votos v ON s.id = v.proyecto_id
                LEFT JOIN votos uv ON s.id = uv.proyecto_id AND uv.usuario_id = ?
                WHERE s.activo = 1
                GROUP BY s.id, s.nombre, s.anio_id, g.nombre, uv.id
                ORDER BY s.anio_id, s.id
            ");
            $stmt->execute([$usuario_id]);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'resultados' => $resultados]);
        } catch (PDOException $e) {
            error_log("Error obteniendo resultados: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al obtener resultados']);
        }
        exit;
    }

    // ACCIONES DE ADMINISTRACIÓN

    // ACCIÓN: OBTENER USUARIOS
    if ($_POST['action'] === 'obtener_usuarios') {
        if (!esAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
            exit;
        }

        try {
            // Columnas explicitas, NUNCA 'u.*': con el asterisco la respuesta
            // JSON arrastraba 'password' (hash bcrypt) y 'token' a cualquier
            // cliente que llame a esta accion.
            $stmt = $pdo->prepare("
                SELECT u.id,
                       u.nombre,
                       u.email,
                       u.activo,
                       u.rol_id,
                       u.es_admin,
                       u.votos_1er_ano,
                       u.votos_2do_ano,
                       u.votos_3er_ano,
                       u.fecha_registro,
                       COUNT(v.id) as total_votos,
                       STRING_AGG(s.nombre || ' (' || g.nombre || ')', ', ') as votos_detalle
                FROM usuarios u
                LEFT JOIN votos v ON u.id = v.usuario_id
                LEFT JOIN proyectos s ON v.proyecto_id = s.id
                LEFT JOIN anios g ON s.anio_id = g.id
                GROUP BY u.id, u.nombre, u.email, u.activo, u.rol_id, u.es_admin,
                         u.votos_1er_ano, u.votos_2do_ano, u.votos_3er_ano, u.fecha_registro
                ORDER BY u.fecha_registro DESC
            ");
            $stmt->execute();
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'usuarios' => $usuarios]);
        } catch (PDOException $e) {
            error_log("Error obteniendo usuarios: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al obtener usuarios']);
        }
        exit;
    }

    // ACCIÓN: OBTENER SUBGRUPOS ADMIN
    if ($_POST['action'] === 'obtener_subgrupos_admin') {
        if (!esAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
            exit;
        }

        $grupo_id = isset($_POST['grupo_id']) ? (int)$_POST['grupo_id'] : null;

        try {
            // El GROUP BY lista TODAS las columnas del SELECT menos las agregadas.
            // MySQL 5.7 resolvia `GROUP BY s.id` solo, porque s.id es la primary
            // key y de ahi se deduce el resto de la fila (functional dependency).
            // Postgres NO implementa esa deduccion: `GROUP BY s.id` solo lo
            // aceptaria si s.id fuera ademas la primary key de una tabla de la
            // que depende s.nombre, que no es el caso, y la consulta moria con
            // `column "s.nombre" must appear in the GROUP BY clause`.
            if ($grupo_id) {
                $stmt = $pdo->prepare("
                    SELECT s.id, s.nombre, s.activo, s.anio_id AS grupo_id,
                             g.nombre as grupo_nombre,
                           COUNT(v.id) as total_votos
                    FROM proyectos s
                    JOIN anios g ON s.anio_id = g.id
                    LEFT JOIN votos v ON s.id = v.proyecto_id
                    WHERE s.activo = 1 AND s.anio_id = ?
                    GROUP BY s.id, s.nombre, s.activo, s.anio_id, g.nombre
                    ORDER BY s.id
                ");
                $stmt->execute([$grupo_id]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT s.id, s.nombre, s.activo, s.anio_id AS grupo_id,
                             g.nombre as grupo_nombre,
                           COUNT(v.id) as total_votos
                    FROM proyectos s
                    JOIN anios g ON s.anio_id = g.id
                    LEFT JOIN votos v ON s.id = v.proyecto_id
                    WHERE s.activo = 1
                    GROUP BY s.id, s.nombre, s.activo, s.anio_id, g.nombre
                    ORDER BY s.anio_id, s.id
                ");
                $stmt->execute();
            }

            $subgrupos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'subgrupos' => $subgrupos]);
        } catch (PDOException $e) {
            error_log("Error obteniendo subgrupos: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al obtener subgrupos']);
        }
        exit;
    }

    // ACCIÓN: AGREGAR SUBGRUPO
    if ($_POST['action'] === 'agregar_subgrupo') {
        if (!esAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
            exit;
        }

        $grupo_id = (int)$_POST['grupo_id'];
        $nombre = trim($_POST['nombre']);

        if (empty($nombre)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'El nombre es requerido']);
            exit;
        }

        if (!in_array($grupo_id, [1, 2, 3])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Grupo inválido']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO proyectos (anio_id, nombre) VALUES (?, ?)");
            $stmt->execute([$grupo_id, $nombre]);

            echo json_encode(['success' => true, 'message' => 'Subgrupo agregado correctamente']);
        } catch (PDOException $e) {
            error_log("Error agregando subgrupo: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al agregar subgrupo']);
        }
        exit;
    }

    // ACCIÓN: ACTUALIZAR SUBGRUPO
    if ($_POST['action'] === 'actualizar_subgrupo') {
        if (!esAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
            exit;
        }

        $id = (int)$_POST['id'];
        $nombre = trim($_POST['nombre']);

        if (empty($nombre)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'El nombre es requerido']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE proyectos SET nombre = ? WHERE id = ?");
            $stmt->execute([$nombre, $id]);

            echo json_encode(['success' => true, 'message' => 'Subgrupo actualizado correctamente']);
        } catch (PDOException $e) {
            error_log("Error actualizando subgrupo: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al actualizar subgrupo']);
        }
        exit;
    }

    // ACCIÓN: ELIMINAR SUBGRUPO
    if ($_POST['action'] === 'eliminar_subgrupo') {
        if (!esAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
            exit;
        }

        $id = (int)$_POST['id'];

        try {
            $pdo->beginTransaction();

            // Obtener votos a eliminar
            $stmt = $pdo->prepare("SELECT v.usuario_id, v.anio_id FROM votos v WHERE v.proyecto_id = ?");
            $stmt->execute([$id]);
            $votosEliminar = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Actualizar usuarios
            foreach ($votosEliminar as $voto) {
                $campos_voto = [
                    1 => 'votos_1er_ano',
                    2 => 'votos_2do_ano',
                    3 => 'votos_3er_ano'
                ];

                // La clave se leia como $voto['grupo_id'], pero la columna se
                // llama anio_id: el indice no existia, daba null, el nombre de
                // columna llegaba vacio a la sentencia y el UPDATE era
                // invalido. Con eso, eliminar un subgrupo con votos tiraba
                // siempre 500 y no borraba nada. Es el mismo campo que en
                // `votar`, que tambien lo pasa como anio_id.
                $campo = $campos_voto[$voto['anio_id']] ?? null;

                if ($campo === null) {
                    continue;
                }

                $stmt = $pdo->prepare("UPDATE usuarios SET $campo = 0 WHERE id = ?");
                $stmt->execute([$voto['usuario_id']]);
            }

            // Eliminar votos
            $stmt = $pdo->prepare("DELETE FROM votos WHERE proyecto_id = ?");
            $stmt->execute([$id]);

            // Marcar subgrupo como inactivo
            $stmt = $pdo->prepare("UPDATE proyectos SET activo = 0 WHERE id = ?");
            $stmt->execute([$id]);

            $pdo->commit();

            echo json_encode(['success' => true, 'message' => 'Subgrupo eliminado correctamente']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error eliminando subgrupo: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al eliminar subgrupo']);
        }
        exit;
    }

    // ACCIÓN: RESETEAR VOTOS USUARIO
    if ($_POST['action'] === 'resetear_votos_usuario') {
        if (!esAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
            exit;
        }

        $usuario_id = (int)$_POST['usuario_id'];

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("DELETE FROM votos WHERE usuario_id = ?");
            $stmt->execute([$usuario_id]);

            $stmt = $pdo->prepare("UPDATE usuarios SET votos_1er_ano = 0, votos_2do_ano = 0, votos_3er_ano = 0 WHERE id = ?");
            $stmt->execute([$usuario_id]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Votos reseteados correctamente']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error reseteando votos: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al resetear votos']);
        }
        exit;
    }

    // ACCIÓN: ELIMINAR USUARIO
    if ($_POST['action'] === 'eliminar_usuario') {
        if (!esAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
            exit;
        }

        $usuario_id = (int)$_POST['usuario_id'];

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("DELETE FROM votos WHERE usuario_id = ?");
            $stmt->execute([$usuario_id]);

            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error eliminando usuario: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al eliminar usuario']);
        }
        exit;
    }

    // ACCIÓN: RESETEAR TODOS LOS VOTOS
    if ($_POST['action'] === 'resetear_todos_los_votos') {
        if (!esAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("DELETE FROM votos");
            $stmt->execute();

            $stmt = $pdo->prepare("UPDATE usuarios SET votos_1er_ano = 0, votos_2do_ano = 0, votos_3er_ano = 0");
            $stmt->execute();

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Todos los votos han sido eliminados']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error reseteando todos los votos: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al resetear votos']);
        }
        exit;
    }

    // ACCIÓN: LIMPIAR VOTOS HUÉRFANOS
    if ($_POST['action'] === 'limpiar_votos_huerfanos') {
        if (!esAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("DELETE FROM votos WHERE usuario_id NOT IN (SELECT id FROM usuarios)");
            $stmt->execute();

            $votosEliminados = $stmt->rowCount();

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Se eliminaron {$votosEliminados} votos huérfanos"]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error limpiando votos huérfanos: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al limpiar votos huérfanos']);
        }
        exit;
    }

    // ACCIÓN: OBTENER CONFIGURACIÓN
    if ($_POST['action'] === 'obtener_configuracion') {
        if (!esAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM configuracion_votacion WHERE activo = 1 ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $configuracion = $stmt->fetch(PDO::FETCH_ASSOC);

            // Si no hay configuración, crear una por defecto
            if (!$configuracion) {
                $inicio = date('Y-m-d H:i:s');
                $fin = date('Y-m-d H:i:s', strtotime('+7 days'));
                $stmt = $pdo->prepare("INSERT INTO configuracion_votacion (inicio_votacion, fin_votacion, activo) VALUES (?, ?, 1)");
                $stmt->execute([$inicio, $fin]);
                $configuracion = [
                    'id' => $pdo->lastInsertId(),
                    'inicio_votacion' => $inicio,
                    'fin_votacion' => $fin,
                    'activo' => 1
                ];
            }

            // El año de la edición no viene en `configuracion_votacion`: está en
            // su propia tabla y se agrega al mismo objeto para que el formulario
            // lo precargue de una. Es un SELECT y no un INSERT: si la tabla
            // todavía no existe, esto no debe tirar abajo la pantalla de
            // configuración, solo mostrar el año sin cargar.
            $configuracion['anio'] = anioVotacion();

            echo json_encode(['success' => true, 'configuracion' => $configuracion]);
        } catch (PDOException $e) {
            error_log("Error obteniendo configuración: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'No pudimos cargar la configuración. Probá de nuevo.']);
        }
        exit;
    }

    // ACCIÓN: GUARDAR CONFIGURACIÓN
    if ($_POST['action'] === 'guardar_configuracion') {
        if (!esAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
            exit;
        }

        $inicio_votacion = $_POST['inicio_votacion'];
        $fin_votacion = $_POST['fin_votacion'];
        $anio = (int)($_POST['anio'] ?? 0);

        if (empty($inicio_votacion) || empty($fin_votacion)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Todos los campos son requeridos']);
            exit;
        }

        // Rango sensato: si mandan "20" o "99999" es un error de tipeo, no un
        // ano. Antes no hacia falta validarlo porque el ano no se guardaba.
        if ($anio < 2000 || $anio > 2100) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'El año no es válido']);
            exit;
        }

        try {
            // Convertir a formato DATETIME de MySQL
            $inicio_datetime = date('Y-m-d H:i:s', strtotime($inicio_votacion));
            $fin_datetime = date('Y-m-d H:i:s', strtotime($fin_votacion));

            // Desactivar configuraciones anteriores
            $stmt = $pdo->prepare("UPDATE configuracion_votacion SET activo = 0");
            $stmt->execute();

            // Crear nueva configuración
            $stmt = $pdo->prepare("INSERT INTO configuracion_votacion (inicio_votacion, fin_votacion, activo) VALUES (?, ?, 1)");
            $stmt->execute([$inicio_datetime, $fin_datetime]);

            // El año de la edición va en su propia tabla (anio_votacion). Igual
            // que la ventana: se deja activo uno solo y el anterior se apaga, que
            // es lo que hace anioVotacion() al leer.
            $stmt = $pdo->prepare("UPDATE anio_votacion SET activo = 0");
            $stmt->execute();

            $stmt = $pdo->prepare("INSERT INTO anio_votacion (anio, activo) VALUES (?, 1)");
            $stmt->execute([$anio]);

            echo json_encode(['success' => true, 'message' => 'Configuración guardada correctamente']);
        } catch (PDOException $e) {
            error_log("Error guardando configuración: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al guardar la configuración']);
        }
        exit;
    }
}
?>
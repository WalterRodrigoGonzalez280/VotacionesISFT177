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
            $stmt = $pdo->prepare("SELECT nombre, email, votos_1er_año, votos_2do_año, votos_3er_año, es_admin FROM usuarios WHERE id = ?");
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
            $stmt = $pdo->prepare("SELECT id FROM subgrupos WHERE id = ? AND grupo_id = ? AND activo = TRUE");
            $stmt->execute([$subgrupo_id, $grupo_id]);
            if (!$stmt->fetch()) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Subgrupo no válido']);
                exit;
            }

            // Verificar si el usuario ya votó en este grupo
            $campos_voto = [
                1 => 'votos_1er_año',
                2 => 'votos_2do_año',
                3 => 'votos_3er_año'
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
            $stmt = $pdo->prepare("INSERT INTO votos (usuario_id, subgrupo_id, grupo_id) VALUES (?, ?, ?)");
            $stmt->execute([$usuario_id, $subgrupo_id, $grupo_id]);

            // Verificar si el usuario completó sus 3 votos
            $stmt = $pdo->prepare("SELECT votos_1er_año, votos_2do_año, votos_3er_año FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $usuario_actualizado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $votos_completos = false;
            if ($usuario_actualizado) {
                $votos_completos = ($usuario_actualizado['votos_1er_año'] == 1 && 
                                    $usuario_actualizado['votos_2do_año'] == 1 && 
                                    $usuario_actualizado['votos_3er_año'] == 1);
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
                SELECT s.id, s.nombre, s.grupo_id, g.nombre as grupo_nombre,
                       COUNT(v.id) as votos,
                       CASE WHEN uv.id IS NOT NULL THEN 1 ELSE 0 END as usuario_voto
                FROM subgrupos s 
                JOIN grupos g ON s.grupo_id = g.id 
                LEFT JOIN votos v ON s.id = v.subgrupo_id
                LEFT JOIN votos uv ON s.id = uv.subgrupo_id AND uv.usuario_id = ?
                WHERE s.activo = TRUE 
                GROUP BY s.id, s.nombre, s.grupo_id, g.nombre, uv.id
                ORDER BY s.grupo_id, s.id
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
            $stmt = $pdo->prepare("
                SELECT u.*, 
                       COUNT(v.id) as total_votos,
                       GROUP_CONCAT(CONCAT(s.nombre, ' (', g.nombre, ')') SEPARATOR ', ') as votos_detalle
                FROM usuarios u
                LEFT JOIN votos v ON u.id = v.usuario_id
                LEFT JOIN subgrupos s ON v.subgrupo_id = s.id
                LEFT JOIN grupos g ON s.grupo_id = g.id
                GROUP BY u.id
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
            if ($grupo_id) {
                $stmt = $pdo->prepare("
                    SELECT s.*, g.nombre as grupo_nombre,
                           COUNT(v.id) as total_votos
                    FROM subgrupos s
                    JOIN grupos g ON s.grupo_id = g.id
                    LEFT JOIN votos v ON s.id = v.subgrupo_id
                    WHERE s.activo = TRUE AND s.grupo_id = ?
                    GROUP BY s.id
                    ORDER BY s.id
                ");
                $stmt->execute([$grupo_id]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT s.*, g.nombre as grupo_nombre,
                           COUNT(v.id) as total_votos
                    FROM subgrupos s
                    JOIN grupos g ON s.grupo_id = g.id
                    LEFT JOIN votos v ON s.id = v.subgrupo_id
                    WHERE s.activo = TRUE
                    GROUP BY s.id
                    ORDER BY s.grupo_id, s.id
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
            $stmt = $pdo->prepare("INSERT INTO subgrupos (grupo_id, nombre) VALUES (?, ?)");
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
            $stmt = $pdo->prepare("UPDATE subgrupos SET nombre = ? WHERE id = ?");
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
            $stmt = $pdo->prepare("SELECT v.usuario_id, v.grupo_id FROM votos v WHERE v.subgrupo_id = ?");
            $stmt->execute([$id]);
            $votosEliminar = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Actualizar usuarios
            foreach ($votosEliminar as $voto) {
                $campos_voto = [
                    1 => 'votos_1er_año',
                    2 => 'votos_2do_año',
                    3 => 'votos_3er_año'
                ];
                $campo = $campos_voto[$voto['grupo_id']];

                $stmt = $pdo->prepare("UPDATE usuarios SET $campo = 0 WHERE id = ?");
                $stmt->execute([$voto['usuario_id']]);
            }

            // Eliminar votos
            $stmt = $pdo->prepare("DELETE FROM votos WHERE subgrupo_id = ?");
            $stmt->execute([$id]);

            // Marcar subgrupo como inactivo
            $stmt = $pdo->prepare("UPDATE subgrupos SET activo = FALSE WHERE id = ?");
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

            $stmt = $pdo->prepare("UPDATE usuarios SET votos_1er_año = 0, votos_2do_año = 0, votos_3er_año = 0 WHERE id = ?");
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

            $stmt = $pdo->prepare("UPDATE usuarios SET votos_1er_año = 0, votos_2do_año = 0, votos_3er_año = 0");
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

            echo json_encode(['success' => true, 'configuracion' => $configuracion]);
        } catch (PDOException $e) {
            error_log("Error obteniendo configuración: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al obtener la configuración']);
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

        if (empty($inicio_votacion) || empty($fin_votacion)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Todos los campos son requeridos']);
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
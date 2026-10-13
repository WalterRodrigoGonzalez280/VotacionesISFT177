<?php
function obtenerUsuario($pdo, $email) {
    $stmt = $pdo->prepare("SELECT id, nombre, email, password,activo , token,rol_id
                           FROM usuarios 
                           WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}



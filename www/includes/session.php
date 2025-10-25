<?php
/**
 * Gestión de sesiones
 */
if (session_status() === PHP_SESSION_NONE) {
    // Configurar tiempo de vida de la sesión (24 horas) antes de iniciar
    ini_set('session.cookie_lifetime', 86400);
    ini_set('session.gc_maxlifetime', 86400);
    session_start();
}

// Función para verificar si el usuario está logueado
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Función para obtener el ID del usuario logueado
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Función para obtener el rol del usuario
function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

// Función para obtener el nombre del usuario
function getUserName() {
    return $_SESSION['user_name'] ?? null;
}

// Función para verificar si el usuario es admin
function isAdmin() {
    return getUserRole() === 'admin';
}

// Función para cerrar sesión
function logout() {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Función para redirigir si no está logueado
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Función para redirigir si no es admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit();
    }
}
?>

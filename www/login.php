<?php
require_once 'controllers/AuthController.php';

// Si ya está logueado, redirigir al dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$authController = new AuthController();
$error_message = $authController->login();

include 'views/login.php';
?>

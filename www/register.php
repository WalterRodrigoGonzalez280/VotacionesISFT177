<?php
require_once 'controllers/AuthController.php';

// Si ya está logueado, redirigir al dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$authController = new AuthController();
$result = $authController->register();
$error_message = $result['error'];
$success_message = $result['success'];

include 'views/register.php';
?>

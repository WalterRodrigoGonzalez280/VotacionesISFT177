<?php
session_start();

// Destruir la sesión
session_unset();
session_destroy();

// Responder con JSON
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Sesión cerrada correctamente']);
exit;
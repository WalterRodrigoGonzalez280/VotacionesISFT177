<?php
session_start();

// Destruir la sesión
session_unset();
session_destroy();

// Redirigir a la página de despedida
header('Location: despedida.php');
exit;
?>

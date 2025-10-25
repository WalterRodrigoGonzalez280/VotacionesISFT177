<?php
require_once 'models/User.php';
require_once 'includes/session.php';

class DashboardController {
    
    public function index() {
        // Verificar que el usuario esté logueado
        if (!isLoggedIn()) {
            header('Location: login.php');
            exit();
        }
        
        $user = new User();
        $user->getUserById(getUserId());
        
        return $user;
    }
    
    public function getStats() {
        // Aquí se pueden agregar estadísticas reales de la base de datos
        return [
            'usuarios' => 1250,
            'votaciones_activas' => 3,
            'candidatos' => 12,
            'participacion' => '85%'
        ];
    }
}
?>

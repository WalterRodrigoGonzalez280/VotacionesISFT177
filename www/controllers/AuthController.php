<?php
require_once 'models/User.php';
require_once 'includes/session.php';

class AuthController {
    
    public function login() {
        $error_message = '';
        
        if ($_POST) {
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            
            if (!empty($email) && !empty($password)) {
                $user = new User();
                
                if ($user->login($email, $password)) {
                    // Iniciar sesión
                    $_SESSION['user_id'] = $user->id;
                    $_SESSION['user_name'] = $user->nombre . ' ' . $user->apellido;
                    $_SESSION['user_role'] = $user->rol;
                    $_SESSION['user_email'] = $user->email;
                    
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $error_message = 'Email o contraseña incorrectos.';
                }
            } else {
                $error_message = 'Por favor, complete todos los campos.';
            }
        }
        
        return $error_message;
    }
    
    public function register() {
        $error_message = '';
        $success_message = '';
        
        if ($_POST) {
            $nombre = trim($_POST['nombre']);
            $apellido = trim($_POST['apellido']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Validaciones
            if (empty($nombre) || empty($apellido) || empty($email) || empty($password)) {
                $error_message = 'Por favor, complete todos los campos.';
            } elseif ($password !== $confirm_password) {
                $error_message = 'Las contraseñas no coinciden.';
            } elseif (strlen($password) < 6) {
                $error_message = 'La contraseña debe tener al menos 6 caracteres.';
            } else {
                $user = new User();
                $user->nombre = $nombre;
                $user->apellido = $apellido;
                $user->email = $email;
                $user->password = $password;
                $user->dni = '00000000'; // DNI por defecto
                $user->telefono = '';
                $user->fecha_nacimiento = null;
                
                // Verificar si el email ya existe
                if ($user->emailExists()) {
                    $error_message = 'Este email ya está registrado.';
                } else {
                    if ($user->register()) {
                        $success_message = 'Usuario registrado exitosamente. Puedes iniciar sesión.';
                    } else {
                        $error_message = 'Error al registrar el usuario. Inténtalo de nuevo.';
                    }
                }
            }
        }
        
        return ['error' => $error_message, 'success' => $success_message];
    }
    
    public function logout() {
        session_destroy();
        header('Location: login.php');
        exit();
    }
}
?>

<?php
require_once 'config/database.php';

class User {
    private $conn;
    private $table_name = "usuarios";

    public $id;
    public $nombre;
    public $apellido;
    public $email;
    public $password;
    public $dni;
    public $telefono;
    public $fecha_nacimiento;
    public $rol;
    public $activo;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Registrar nuevo usuario
    public function register() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (nombre, apellido, email, password, dni, telefono, fecha_nacimiento) 
                  VALUES (:nombre, :apellido, :email, :password, :dni, :telefono, :fecha_nacimiento)";

        $stmt = $this->conn->prepare($query);

        // Hash de la contraseña
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);

        // Bind de parámetros
        $stmt->bindParam(':nombre', $this->nombre);
        $stmt->bindParam(':apellido', $this->apellido);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password', $this->password);
        $stmt->bindParam(':dni', $this->dni);
        $stmt->bindParam(':telefono', $this->telefono);
        $stmt->bindParam(':fecha_nacimiento', $this->fecha_nacimiento);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Verificar login
    public function login($email, $password) {
        $query = "SELECT id, nombre, apellido, email, password, rol, activo 
                  FROM " . $this->table_name . " 
                  WHERE email = :email AND activo = 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(password_verify($password, $row['password'])) {
                $this->id = $row['id'];
                $this->nombre = $row['nombre'];
                $this->apellido = $row['apellido'];
                $this->email = $row['email'];
                $this->rol = $row['rol'];
                return true;
            }
        }
        return false;
    }

    // Verificar si email existe
    public function emailExists() {
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // Verificar si DNI existe (no se usa en el registro simplificado)
    public function dniExists() {
        return false; // No validamos DNI en el registro simplificado
    }

    // Obtener usuario por ID
    public function getUserById($id) {
        $query = "SELECT id, nombre, apellido, email, dni, telefono, fecha_nacimiento, rol 
                  FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->nombre = $row['nombre'];
            $this->apellido = $row['apellido'];
            $this->email = $row['email'];
            $this->dni = $row['dni'];
            $this->telefono = $row['telefono'];
            $this->fecha_nacimiento = $row['fecha_nacimiento'];
            $this->rol = $row['rol'];
            return true;
        }
        return false;
    }
}
?>

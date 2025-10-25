<?php
/**
 * Configuración de la base de datos
 */
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        $this->host = getenv('MYSQL_SERVER') ?: 'db';
        $this->db_name = getenv('MYSQL_DATABASE') ?: 'votacion_db';
        $this->username = getenv('MYSQL_USER') ?: 'votacion_user';
        $this->password = getenv('MYSQL_PASSWORD') ?: 'develop2025';
    }

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
}
?>

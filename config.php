<?php
class Database {
    private $host = "Localhost";
    private $db_name = "DB Name";
    private $username = "DB Kullanıcı Adı";
    private $password = "Şifre";
    public $conn;

    public function getConnection() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => true
                ]
            );
            return $this->conn;
        } catch(PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Veritabanı bağlantı hatası: " . $e->getMessage());
        }
    }

    public function closeConnection() {
        $this->conn = null;
    }

    public function sanitize($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
}

if ($_SERVER['SERVER_NAME'] == 'localhost') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', 'error.log');
}

date_default_timezone_set('Europe/Istanbul');

define('MAX_LOGIN_ATTEMPTS', 3);
define('LOGIN_TIMEOUT', 900);
?>
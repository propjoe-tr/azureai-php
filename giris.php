<?php
session_start();
require_once 'config.php';

class User {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($username, $password) {
        try {
            if ($this->checkLoginAttempts($_SERVER['REMOTE_ADDR'])) {
                throw new Exception("Çok fazla başarısız deneme. Lütfen " . (LOGIN_TIMEOUT/60) . " dakika sonra tekrar deneyin.");
            }

            $username = $this->sanitizeInput($username);
            
            $query = "SELECT id, username, password, is_active FROM " . $this->table_name . " WHERE username = :username";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":username", $username);
            $stmt->execute();

            if($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if(!$row['is_active']) {
                    throw new Exception("Bu hesap aktif değil.");
                }

                if(password_verify($password, $row['password'])) {
                    $this->resetLoginAttempts($_SERVER['REMOTE_ADDR']);
                    $this->updateLastLogin($row['id']);
                    return $row;
                }
            }

            $this->incrementLoginAttempts($_SERVER['REMOTE_ADDR']);
            throw new Exception("Geçersiz kullanıcı adı veya şifre.");

        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            throw new Exception("Giriş işlemi sırasında bir hata oluştu.");
        }
    }

    private function sanitizeInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    private function updateLastLogin($user_id) {
        $query = "UPDATE " . $this->table_name . " SET last_login = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $user_id);
        return $stmt->execute();
    }

    private function checkLoginAttempts($ip) {
        if(!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['last_attempt'] = time();
            return false;
        }

        if(time() - $_SESSION['last_attempt'] > LOGIN_TIMEOUT) {
            $this->resetLoginAttempts($ip);
            return false;
        }

        return $_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS;
    }

    private function incrementLoginAttempts($ip) {
        if(!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = 0;
        }
        $_SESSION['login_attempts']++;
        $_SESSION['last_attempt'] = time();
    }

    private function resetLoginAttempts($ip) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt'] = time();
    }
}

// POST işlemi kontrolü
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $database = new Database();
        $db = $database->getConnection();
        $user = new User($db);

        $result = $user->login($_POST['username'], $_POST['password']);

        if($result) {
            $_SESSION['user_id'] = $result['id'];
            $_SESSION['username'] = $result['username'];
            $_SESSION['login_time'] = time();
            
            header("Location: dashboard.php");
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .login-container {
            max-width: 400px;
            margin: 50px auto;
        }
        .error-message {
            color: #dc3545;
            margin-bottom: 15px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="login-container">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h3 class="text-center mb-0">Giriş Yap</h3>
                </div>
                <div class="card-body">
                    <?php
                    if(isset($_SESSION['error'])) {
                        echo '<div class="error-message text-center">' . $_SESSION['error'] . '</div>';
                        unset($_SESSION['error']);
                    }
                    ?>
                    <form action="" method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="username" class="form-label">Kullanıcı Adı</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   required autocomplete="username">
                            <div class="invalid-feedback">
                                Lütfen kullanıcı adınızı girin.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Şifre</label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   required autocomplete="current-password">
                            <div class="invalid-feedback">
                                Lütfen şifrenizi girin.
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Giriş Yap</button>
                    <div class="card-body">
    </form>
    <hr class="my-4">
    <div class="text-center">
        <p class="mb-2">Hesabın yok mu?</p>
        <a href="register.php" class="btn btn-outline-primary w-100">Kayıt Ol</a>
    </div>
</div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form doğrulama
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    </script>
</body>
</html>

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $database = new Database();
        $db = $database->getConnection();
        $user = new User($db);

        $result = $user->login($_POST['username'], $_POST['password']);

        if($result) {
            $_SESSION['user_id'] = $result['id'];
            $_SESSION['username'] = $result['username'];
            $_SESSION['login_time'] = time();
            
            header("Location: dashboard.php");
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}
?>
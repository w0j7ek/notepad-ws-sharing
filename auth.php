<?php
session_start();
require_once 'db.php';

$mode = $_POST['mode'] ?? 'login';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode === 'login') {
        $login = trim($_POST['login']);
        $pass = $_POST['password'];
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            header('Location: index.php');
            exit;
        } else {
            $error = "Nieprawidłowy login lub hasło!";
        }
    } elseif ($mode === 'register') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $pass = $_POST['password'];
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $hash]);
            $_SESSION['user_id'] = $pdo->lastInsertId();
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $error = "Taki login lub email już istnieje!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <title>Logowanie / Rejestracja</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/darkly/bootstrap.min.css">
    <link rel="icon" href="favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card-form {
            max-width: 400px;
            margin: 40px auto;
        }

        .input-group-text {
            cursor: pointer;
        }
    </style>
    <script>
        function showForm(mode) {
            document.getElementById('login-form').style.display = (mode === 'login') ? 'block' : 'none';
            document.getElementById('register-form').style.display = (mode === 'register') ? 'block' : 'none';
        }
        function togglePassword(id, iconId) {
            var input = document.getElementById(id);
            var icon = document.getElementById(iconId);
            if (input.type === "password") {
                input.type = "text";
                icon.className = "fa fa-eye-slash";
            } else {
                input.type = "password";
                icon.className = "fa fa-eye";
            }
        }
    </script>
</head>

<body class="bg-dark text-light" onload="showForm('login')">
    <?php include 'navbar.php'; ?>
    <div class="container">
        <div class="card card-form bg-dark border-secondary mt-5">
            <div class="card-header text-center"><i class="fa fa-user"></i> Notatki - Logowanie / Rejestracja</div>
            <div class="card-body">
                <?php if ($error)
                    echo "<div class='alert alert-danger'><i class='fa fa-exclamation-triangle'></i> $error</div>"; ?>
                <div class="text-center mb-3">
                    <button class="btn btn-primary me-2" onclick="showForm('login')"><i class="fa fa-sign-in-alt"></i>
                        Logowanie</button>
                    <button class="btn btn-secondary" onclick="showForm('register')"><i class="fa fa-user-plus"></i>
                        Rejestracja</button>
                </div>
                <form method="post" id="login-form" style="display:none;" novalidate>
                    <input type="hidden" name="mode" value="login">
                    <div class="mb-3">
                        <label>Login lub Email</label>
                        <input type="text" name="login" class="form-control" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label>Hasło</label>
                        <div class="input-group">
                            <input type="password" name="password" class="form-control" id="login-password" required>
                            <span class="input-group-text" onclick="togglePassword('login-password','login-eye')"><i
                                    id="login-eye" class="fa fa-eye"></i></span>
                        </div>
                    </div>
                    <button class="btn btn-success w-100"><i class="fa fa-sign-in-alt"></i> Zaloguj się</button>
                </form>
                <form method="post" id="register-form" style="display:none;" novalidate>
                    <input type="hidden" name="mode" value="register">
                    <div class="mb-3">
                        <label>Nazwa użytkownika</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Hasło</label>
                        <div class="input-group">
                            <input type="password" name="password" class="form-control" id="reg-password" required>
                            <span class="input-group-text" onclick="togglePassword('reg-password','reg-eye')"><i
                                    id="reg-eye" class="fa fa-eye"></i></span>
                        </div>
                    </div>
                    <button class="btn btn-secondary w-100"><i class="fa fa-user-plus"></i> Zarejestruj się</button>
                </form>
                <div class="mt-3 text-center">
                    <a href="index.php" class="btn btn-link text-light"><i class="fa fa-arrow-left"></i> Powrót</a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
<?php
// login.php
include 'db.php';

// Cookies de sesión seguras
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
session_start();

include 'csrf.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_validate();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $message = '<div class="messages error">¡Completa todos los campos!</div>';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Regenerar ID de sesión para prevenir session fixation
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] == 'admin' || $user['role'] == 'operator') {
                header("Location: admin.php");
            } else {
                header("Location: index.php");
            }
            exit();
        } else {
            $message = '<div class="messages error">¡Usuario o contraseña incorrectos!</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - FashionHub</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">FashionHub</div>
            <div class="nav-links">
                <a href="index.php">Inicio</a>
                <a href="login.php" style="color: var(--primary-color);">Iniciar Sesión</a>
                <a href="register.php">Registrarse</a>
            </div>
        </nav>
    </header>

    <div class="form-container">
        <form action="login.php" method="POST">
            <h2>Iniciar Sesión</h2>
            <?php echo $message; ?>
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn" style="width: 100%;">Entrar</button>
            <p style="text-align: center; margin-top: 1rem;">¿No tienes cuenta? <a href="register.php" style="color: var(--primary-color);">Regístrate aquí</a></p>
        </form>
    </div>
</body>
</html>

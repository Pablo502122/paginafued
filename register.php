<?php
// register.php
include 'db.php';

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
session_start();

include 'csrf.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_validate();

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $address = trim($_POST['address'] ?? '');

    if ($username === '' || $email === '' || $password === '' || $address === '') {
        $message = '<div class="messages error">¡Completa todos los campos!</div>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '<div class="messages error">¡Email no válido!</div>';
    } elseif (strlen($password) < 6) {
        $message = '<div class="messages error">¡La contraseña debe tener al menos 6 caracteres!</div>';
    } elseif ($password !== $confirm_password) {
        $message = '<div class="messages error">¡Las contraseñas no coinciden!</div>';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, address) VALUES (?, ?, ?, ?)");
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt->execute([$username, $email, $hashed_password, $address]);
            header("Location: login.php"); // Redirect to login after successful registration
            exit();
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Integrity constraint violation (duplicate entry)
                $message = '<div class="messages error">¡El usuario o correo ya existe!</div>';
            } else {
                $message = '<div class="messages error">Error al registrar. Intenta de nuevo.</div>';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - FashionHub</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">FashionHub</div>
            <div class="nav-links">
                <a href="index.php">Inicio</a>
                <a href="login.php">Iniciar Sesión</a>
                <a href="register.php" style="color: var(--primary-color);">Registrarse</a>
            </div>
        </nav>
    </header>

    <div class="form-container">
        <form action="register.php" method="POST">
            <h2>Crear Cuenta</h2>
            <?php echo $message; ?>
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="address">Dirección de Envío</label>
                <input type="text" id="address" name="address" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirmar Contraseña</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn" style="width: 100%;">Registrarse</button>
            <p style="text-align: center; margin-top: 1rem;">¿Ya tienes cuenta? <a href="login.php" style="color: var(--primary-color);">Inicia sesión aquí</a></p>
        </form>
    </div>
</body>
</html>

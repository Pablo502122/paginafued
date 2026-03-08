<?php
// csrf.php - Protección CSRF para formularios críticos

/**
 * Genera o devuelve el token CSRF de la sesión actual.
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Devuelve un campo hidden HTML con el token CSRF.
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Valida el token CSRF del POST actual.
 * Termina la ejecución con 403 si el token no es válido.
 */
function csrf_validate() {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('Error: Token CSRF inválido. Recarga la página e intenta de nuevo.');
    }
}
?>

<?php
// db.php - Database Connection

$db_file = __DIR__ . '/tiendaropa.db';

try {
    // Create (connect to) SQLite database in file
    $pdo = new PDO('sqlite:' . $db_file);
    // Set errormode to exceptions
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Enable WAL mode for better concurrency
    $pdo->exec("PRAGMA journal_mode=WAL");
    $pdo->exec("PRAGMA foreign_keys=ON");

    // Create tables if they don't exist (base schema)
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($sql);

    // Run incremental migrations (safe to call multiple times)
    if (file_exists(__DIR__ . '/migrate_auto.php')) {
        include_once __DIR__ . '/migrate_auto.php';
    }

    // Check if admin exists and add if not
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();

} catch(PDOException $e) {
    // Don't expose error details in production
    error_log("DB Connection failed: " . $e->getMessage());
    echo "Error de conexión a la base de datos.";
    exit();
}
?>


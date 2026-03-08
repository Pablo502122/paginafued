<?php
// migrate.php - Migración incremental de la base de datos
// Ejecutar una sola vez: php migrate.php o abrir en navegador

include 'db.php';

echo "<pre>\n";
echo "=== Migración de Base de Datos FashionHub ===\n\n";

$migrations = [];

// Helper: check if column exists in a table
function columnExists($pdo, $table, $column) {
    $stmt = $pdo->query("PRAGMA table_info($table)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        if ($col['name'] === $column) return true;
    }
    return false;
}

// Helper: check if table exists
function tableExists($pdo, $table) {
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
    return $stmt->fetch() !== false;
}

try {
    // ========== NEW TABLES ==========

    // Categories table
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        slug TEXT NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✅ Tabla 'categories' verificada/creada\n";

    // Brands table
    $pdo->exec("CREATE TABLE IF NOT EXISTS brands (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✅ Tabla 'brands' verificada/creada\n";

    // Product images table
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_images (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        image_url TEXT NOT NULL,
        sort_order INTEGER DEFAULT 0,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");
    echo "✅ Tabla 'product_images' verificada/creada\n";

    // Product variants table (talla/color)
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_variants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        size TEXT,
        color TEXT,
        sku TEXT UNIQUE,
        stock INTEGER NOT NULL DEFAULT 0,
        price_override REAL,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");
    echo "✅ Tabla 'product_variants' verificada/creada\n";

    // Order status log table
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_status_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ticket_id INTEGER NOT NULL,
        old_status TEXT,
        new_status TEXT NOT NULL,
        changed_by TEXT,
        note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ticket_id) REFERENCES tickets(id)
    )");
    echo "✅ Tabla 'order_status_log' verificada/creada\n";

    // ========== ALTER PRODUCTS TABLE ==========
    if (!columnExists($pdo, 'products', 'category_id')) {
        $pdo->exec("ALTER TABLE products ADD COLUMN category_id INTEGER REFERENCES categories(id)");
        echo "✅ Columna 'products.category_id' agregada\n";
    } else {
        echo "⏭️  Columna 'products.category_id' ya existe\n";
    }

    if (!columnExists($pdo, 'products', 'brand_id')) {
        $pdo->exec("ALTER TABLE products ADD COLUMN brand_id INTEGER REFERENCES brands(id)");
        echo "✅ Columna 'products.brand_id' agregada\n";
    } else {
        echo "⏭️  Columna 'products.brand_id' ya existe\n";
    }

    if (!columnExists($pdo, 'products', 'sku')) {
        $pdo->exec("ALTER TABLE products ADD COLUMN sku TEXT");
        echo "✅ Columna 'products.sku' agregada\n";
    } else {
        echo "⏭️  Columna 'products.sku' ya existe\n";
    }

    // ========== ALTER TICKETS TABLE ==========
    if (!columnExists($pdo, 'tickets', 'payment_method')) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN payment_method TEXT DEFAULT 'card'");
        echo "✅ Columna 'tickets.payment_method' agregada\n";
    } else {
        echo "⏭️  Columna 'tickets.payment_method' ya existe\n";
    }

    if (!columnExists($pdo, 'tickets', 'shipping_address')) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN shipping_address TEXT");
        echo "✅ Columna 'tickets.shipping_address' agregada\n";
    } else {
        echo "⏭️  Columna 'tickets.shipping_address' ya existe\n";
    }

    if (!columnExists($pdo, 'tickets', 'shipping_cost')) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN shipping_cost REAL DEFAULT 0");
        echo "✅ Columna 'tickets.shipping_cost' agregada\n";
    } else {
        echo "⏭️  Columna 'tickets.shipping_cost' ya existe\n";
    }

    if (!columnExists($pdo, 'tickets', 'stock_reserved_until')) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN stock_reserved_until DATETIME");
        echo "✅ Columna 'tickets.stock_reserved_until' agregada\n";
    } else {
        echo "⏭️  Columna 'tickets.stock_reserved_until' ya existe\n";
    }

    if (!columnExists($pdo, 'tickets', 'updated_at')) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP");
        echo "✅ Columna 'tickets.updated_at' agregada\n";
    } else {
        echo "⏭️  Columna 'tickets.updated_at' ya existe\n";
    }

    // ========== ALTER USERS TABLE ==========
    if (!columnExists($pdo, 'users', 'phone')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN phone TEXT");
        echo "✅ Columna 'users.phone' agregada\n";
    } else {
        echo "⏭️  Columna 'users.phone' ya existe\n";
    }

    // ========== INDEXES ==========
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_products_category ON products(category_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_products_brand ON products(brand_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tickets_user ON tickets(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tickets_conekta ON tickets(conekta_order_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_order_status_log_ticket ON order_status_log(ticket_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_product_images_product ON product_images(product_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_product_variants_product ON product_variants(product_id)");
    echo "✅ Índices creados/verificados\n";

    // ========== SEED DEFAULT CATEGORIES ==========
    $defaultCategories = [
        ['Camisetas', 'camisetas'],
        ['Pantalones', 'pantalones'],
        ['Vestidos', 'vestidos'],
        ['Zapatos', 'zapatos'],
        ['Accesorios', 'accesorios'],
        ['Chamarras', 'chamarras'],
        ['Faldas', 'faldas'],
    ];
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO categories (name, slug) VALUES (?, ?)");
    foreach ($defaultCategories as $cat) {
        $stmt->execute($cat);
    }
    echo "✅ Categorías por defecto insertadas\n";

    // ========== PRAGMA for performance ==========
    $pdo->exec("PRAGMA journal_mode=WAL");
    echo "✅ PRAGMA journal_mode=WAL activado\n";

    echo "\n=== Migración completada exitosamente ===\n";

} catch (Exception $e) {
    echo "❌ Error en migración: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>

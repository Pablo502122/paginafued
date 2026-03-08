<?php
// migrate_auto.php - Silent auto-migration (runs on every page load via db.php)
// Only runs ALTER TABLE / CREATE TABLE IF NOT EXISTS — idempotent and safe

// Helper: check if column exists in a table
function _migColExists($pdo, $table, $column) {
    $stmt = $pdo->query("PRAGMA table_info($table)");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        if ($c['name'] === $column) return true;
    }
    return false;
}

try {
    // New tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        slug TEXT NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS brands (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS product_images (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        image_url TEXT NOT NULL,
        sort_order INTEGER DEFAULT 0,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");

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

    // Alter products
    if (!_migColExists($pdo, 'products', 'category_id'))
        $pdo->exec("ALTER TABLE products ADD COLUMN category_id INTEGER REFERENCES categories(id)");
    if (!_migColExists($pdo, 'products', 'brand_id'))
        $pdo->exec("ALTER TABLE products ADD COLUMN brand_id INTEGER REFERENCES brands(id)");
    if (!_migColExists($pdo, 'products', 'sku'))
        $pdo->exec("ALTER TABLE products ADD COLUMN sku TEXT");

    // Alter tickets
    if (!_migColExists($pdo, 'tickets', 'payment_method'))
        $pdo->exec("ALTER TABLE tickets ADD COLUMN payment_method TEXT DEFAULT 'card'");
    if (!_migColExists($pdo, 'tickets', 'shipping_address'))
        $pdo->exec("ALTER TABLE tickets ADD COLUMN shipping_address TEXT");
    if (!_migColExists($pdo, 'tickets', 'shipping_cost'))
        $pdo->exec("ALTER TABLE tickets ADD COLUMN shipping_cost REAL DEFAULT 0");
    if (!_migColExists($pdo, 'tickets', 'stock_reserved_until'))
        $pdo->exec("ALTER TABLE tickets ADD COLUMN stock_reserved_until DATETIME");
    if (!_migColExists($pdo, 'tickets', 'updated_at'))
        $pdo->exec("ALTER TABLE tickets ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP");

    // Alter users
    if (!_migColExists($pdo, 'users', 'phone'))
        $pdo->exec("ALTER TABLE users ADD COLUMN phone TEXT");

    // Indexes
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_products_category ON products(category_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_products_brand ON products(brand_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tickets_user ON tickets(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tickets_conekta ON tickets(conekta_order_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_order_log_ticket ON order_status_log(ticket_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_prod_images ON product_images(product_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_prod_variants ON product_variants(product_id)");

} catch (Exception $e) {
    error_log("Auto-migration error: " . $e->getMessage());
}
?>

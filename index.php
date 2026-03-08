<?php
// index.php - Catálogo principal con búsqueda, filtros y paginación
include 'db.php';

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();

include 'csrf.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Pagination
$perPage = 12;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Search & Filters
$search = trim($_GET['q'] ?? '');
$categoryFilter = intval($_GET['cat'] ?? 0);
$brandFilter = intval($_GET['brand'] ?? 0);
$priceMin = floatval($_GET['price_min'] ?? 0);
$priceMax = floatval($_GET['price_max'] ?? 0);
$sort = $_GET['sort'] ?? 'newest';

// Build query
$where = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($categoryFilter > 0) {
    $where .= " AND p.category_id = ?";
    $params[] = $categoryFilter;
}
if ($brandFilter > 0) {
    $where .= " AND p.brand_id = ?";
    $params[] = $brandFilter;
}
if ($priceMin > 0) {
    $where .= " AND p.price >= ?";
    $params[] = $priceMin;
}
if ($priceMax > 0) {
    $where .= " AND p.price <= ?";
    $params[] = $priceMax;
}

// Sort
$orderBy = "ORDER BY p.created_at DESC"; // default: newest
if ($sort === 'price_asc') $orderBy = "ORDER BY p.price ASC";
elseif ($sort === 'price_desc') $orderBy = "ORDER BY p.price DESC";
elseif ($sort === 'name') $orderBy = "ORDER BY p.name ASC";

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p $where");
$countStmt->execute($params);
$totalProducts = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalProducts / $perPage));

// Fetch products (only needed columns)
$sql = "SELECT p.id, p.name, p.price, p.stock, p.image_url, p.description, c.name as category_name, b.name as brand_name
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN brands b ON p.brand_id = b.id 
        $where $orderBy LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch categories and brands for filters
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$brands = $pdo->query("SELECT * FROM brands ORDER BY name")->fetchAll();

// Build current filter URL params
function filterUrl($overrides = []) {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === '' || $v === null || $v === 0) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    unset($params['page']); // reset page when filters change
    return 'index.php?' . http_build_query($params);
}

$cartCount = array_sum($_SESSION['cart'] ?? []);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FashionHub - Colección</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .catalog-layout { display: grid; grid-template-columns: 240px 1fr; gap: 25px; max-width: 1300px; margin: 30px auto; padding: 0 20px; }
        @media (max-width: 768px) { .catalog-layout { grid-template-columns: 1fr; } }
        
        .sidebar-filters { background: white; padding: 20px; border-radius: 10px; box-shadow: var(--card-shadow); height: fit-content; position: sticky; top: 80px; }
        .sidebar-filters h3 { margin: 0 0 15px 0; color: #333; font-size: 1rem; }
        .filter-group { margin-bottom: 18px; }
        .filter-group label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.85rem; color: #555; }
        .filter-group select, .filter-group input[type="number"] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px; font-size: 0.9rem; }
        .filter-link { display: block; padding: 5px 0; color: #555; font-size: 0.9rem; transition: color 0.2s; }
        .filter-link:hover, .filter-link.active { color: var(--primary-color); font-weight: 600; }

        .catalog-main h1 { margin: 0 0 5px 0; font-size: 1.5rem; }
        .catalog-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
        .search-bar { display: flex; gap: 8px; }
        .search-bar input { padding: 8px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 0.95rem; width: 250px; }
        .search-bar button { padding: 8px 18px; }
        .sort-select { padding: 8px; border: 1px solid #ddd; border-radius: 5px; font-size: 0.9rem; }

        .product-card { position: relative; }
        .product-badge { position: absolute; top: 10px; left: 10px; background: var(--primary-color); color: white; padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
        .product-stock-badge { position: absolute; top: 10px; right: 10px; background: #ff7675; color: white; padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }

        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 30px; }
        .pagination a, .pagination span { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 5px; font-size: 0.9rem; text-decoration: none; transition: all 0.2s; }
        .pagination a { background: white; color: #333; border: 1px solid #ddd; }
        .pagination a:hover { background: var(--primary-color); color: white; border-color: var(--primary-color); }
        .pagination span.current { background: var(--primary-color); color: white; border: 1px solid var(--primary-color); }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="logo">FashionHub</div>
            <div class="nav-links">
                <a href="index.php" style="color: var(--primary-color);">Inicio</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="cart.php">🛒 Carrito (<?php echo $cartCount; ?>)</a>
                    <a href="perfil.php">Mi Perfil</a>
                    <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'operator'])): ?>
                        <a href="admin.php">Panel Admin</a>
                    <?php endif; ?>
                    <a href="logout.php">Cerrar Sesión (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
                <?php else: ?>
                    <a href="login.php">Iniciar Sesión</a>
                    <a href="register.php">Registrarse</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <div class="catalog-layout">
        <!-- Sidebar Filters -->
        <aside class="sidebar-filters">
            <h3>🔍 Filtros</h3>

            <div class="filter-group">
                <label>Categoría</label>
                <a href="<?php echo filterUrl(['cat' => '']); ?>" class="filter-link <?php echo !$categoryFilter ? 'active' : ''; ?>">Todas</a>
                <?php foreach ($categories as $c): ?>
                    <a href="<?php echo filterUrl(['cat' => $c['id']]); ?>" class="filter-link <?php echo $categoryFilter == $c['id'] ? 'active' : ''; ?>"><?php echo htmlspecialchars($c['name']); ?></a>
                <?php endforeach; ?>
            </div>

            <div class="filter-group">
                <label>Marca</label>
                <a href="<?php echo filterUrl(['brand' => '']); ?>" class="filter-link <?php echo !$brandFilter ? 'active' : ''; ?>">Todas</a>
                <?php foreach ($brands as $b): ?>
                    <a href="<?php echo filterUrl(['brand' => $b['id']]); ?>" class="filter-link <?php echo $brandFilter == $b['id'] ? 'active' : ''; ?>"><?php echo htmlspecialchars($b['name']); ?></a>
                <?php endforeach; ?>
            </div>

            <div class="filter-group">
                <label>Rango de Precio</label>
                <form method="GET" action="index.php">
                    <?php foreach ($_GET as $k => $v): ?>
                        <?php if ($k !== 'price_min' && $k !== 'price_max' && $k !== 'page'): ?>
                            <input type="hidden" name="<?php echo htmlspecialchars($k); ?>" value="<?php echo htmlspecialchars($v); ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <div style="display:flex; gap:5px; margin-bottom:5px;">
                        <input type="number" name="price_min" placeholder="Min" value="<?php echo $priceMin > 0 ? $priceMin : ''; ?>" min="0" step="1" style="width:50%;">
                        <input type="number" name="price_max" placeholder="Max" value="<?php echo $priceMax > 0 ? $priceMax : ''; ?>" min="0" step="1" style="width:50%;">
                    </div>
                    <button class="btn btn-secondary" style="width:100%; padding:6px; font-size:0.85rem;">Aplicar</button>
                </form>
            </div>

            <?php if ($search || $categoryFilter || $brandFilter || $priceMin || $priceMax): ?>
                <a href="index.php" class="btn btn-secondary" style="width:100%; text-align:center; padding:8px; font-size:0.85rem;">Limpiar Filtros</a>
            <?php endif; ?>
        </aside>

        <!-- Main Catalog -->
        <div class="catalog-main">
            <div class="catalog-meta">
                <div>
                    <h1>Colección</h1>
                    <span style="color:#666; font-size:0.9rem;"><?php echo $totalProducts; ?> productos encontrados</span>
                </div>
                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <!-- Search Bar -->
                    <form method="GET" action="index.php" class="search-bar">
                        <?php foreach ($_GET as $k => $v): ?>
                            <?php if ($k !== 'q' && $k !== 'page'): ?>
                                <input type="hidden" name="<?php echo htmlspecialchars($k); ?>" value="<?php echo htmlspecialchars($v); ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <input type="text" name="q" placeholder="Buscar productos..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn" type="submit">Buscar</button>
                    </form>

                    <!-- Sort -->
                    <select class="sort-select" onchange="window.location.href='<?php echo filterUrl(['sort' => '']); ?>&sort='+this.value">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Más nuevos</option>
                        <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Precio: menor a mayor</option>
                        <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Precio: mayor a menor</option>
                        <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Nombre A-Z</option>
                    </select>
                </div>
            </div>

            <?php if (empty($products)): ?>
                <div style="text-align:center; padding:60px 20px; color:#888;">
                    <div style="font-size:3rem; margin-bottom:10px;">🔎</div>
                    <h3>No se encontraron productos</h3>
                    <p>Intenta con otros filtros o <a href="index.php" style="color:var(--primary-color);">ver todos los productos</a></p>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <?php if ($product['category_name']): ?>
                                <span class="product-badge"><?php echo htmlspecialchars($product['category_name']); ?></span>
                            <?php endif; ?>
                            <?php if ($product['stock'] <= 0): ?>
                                <span class="product-stock-badge">Agotado</span>
                            <?php elseif ($product['stock'] <= 3): ?>
                                <span class="product-stock-badge" style="background:#ffc107; color:#333;">¡Últimos <?php echo $product['stock']; ?>!</span>
                            <?php endif; ?>

                            <a href="product.php?id=<?php echo $product['id']; ?>">
                                <?php if ($product['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                                <?php else: ?>
                                    <div class="product-image" style="background:#f0f0f0; display:flex; align-items:center; justify-content:center; font-size:3rem;">👕</div>
                                <?php endif; ?>
                            </a>
                            <div class="product-info">
                                <a href="product.php?id=<?php echo $product['id']; ?>" style="text-decoration:none; color:inherit;">
                                    <div class="product-title"><?php echo htmlspecialchars($product['name']); ?></div>
                                </a>
                                <?php if ($product['brand_name']): ?>
                                    <div style="font-size:0.8rem; color:#999; margin-bottom:4px;"><?php echo htmlspecialchars($product['brand_name']); ?></div>
                                <?php endif; ?>
                                <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                                <p style="font-size:0.85rem; color:#666; margin-bottom:10px;"><?php echo htmlspecialchars(mb_substr($product['description'] ?? '', 0, 60)); ?><?php echo strlen($product['description'] ?? '') > 60 ? '...' : ''; ?></p>
                                <div class="product-actions">
                                    <?php if ($product['stock'] > 0): ?>
                                        <form action="cart.php" method="POST">
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <button type="submit" class="btn">Agregar al Carrito</button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn" disabled style="background:#ccc; cursor:not-allowed;">Agotado</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo filterUrl([]); ?>&page=<?php echo $page - 1; ?>">&laquo;</a>
                        <?php endif; ?>
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="<?php echo filterUrl([]); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo filterUrl([]); ?>&page=<?php echo $page + 1; ?>">&raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer style="background:#2d3436; color:white; padding:40px 20px; margin-top:40px;">
        <div style="max-width:1200px; margin:0 auto; display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:30px;">
            <div>
                <h4 style="color:var(--secondary-color); margin-bottom:10px;">FashionHub</h4>
                <p style="color:#b2bec3; font-size:0.9rem;">Tu tienda de moda online favorita. Las mejores tendencias al mejor precio.</p>
            </div>
            <div>
                <h4 style="color:var(--secondary-color); margin-bottom:10px;">Enlaces</h4>
                <a href="index.php" style="display:block; color:#b2bec3; margin-bottom:5px; font-size:0.9rem;">Inicio</a>
                <a href="politicas.php" style="display:block; color:#b2bec3; margin-bottom:5px; font-size:0.9rem;">Políticas</a>
                <a href="contacto.php" style="display:block; color:#b2bec3; margin-bottom:5px; font-size:0.9rem;">Contacto</a>
            </div>
            <div>
                <h4 style="color:var(--secondary-color); margin-bottom:10px;">Contacto</h4>
                <p style="color:#b2bec3; font-size:0.9rem;">📧 info@fashionhub.com</p>
                <p style="color:#b2bec3; font-size:0.9rem;">📞 +52 618 123 4567</p>
            </div>
        </div>
        <div style="text-align:center; margin-top:20px; padding-top:15px; border-top:1px solid #636e72; color:#636e72; font-size:0.85rem;">
            © <?php echo date('Y'); ?> FashionHub. Todos los derechos reservados.
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('.product-actions form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    formData.append('ajax', 'true');
                    const button = this.querySelector('button');
                    const originalText = button.innerText;
                    button.innerText = 'Agregando...';
                    button.disabled = true;

                    fetch('cart.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            button.innerText = '¡Agregado! ✓';
                            button.style.backgroundColor = '#00b894';
                            // Update cart count in nav
                            const cartLinks = document.querySelectorAll('a[href="cart.php"]');
                            cartLinks.forEach(l => {
                                if (data.cart_count !== undefined) {
                                    l.textContent = '🛒 Carrito (' + data.cart_count + ')';
                                }
                            });
                            setTimeout(() => {
                                button.innerText = originalText;
                                button.style.backgroundColor = '';
                                button.disabled = false;
                            }, 2000);
                        } else {
                            alert(data.message || 'Error al agregar al carrito');
                            button.innerText = originalText;
                            button.disabled = false;
                        }
                    })
                    .catch(() => {
                        button.innerText = originalText;
                        button.disabled = false;
                        alert('Error de conexión');
                    });
                });
            });
        });
    </script>
</body>
</html>

<?php
// product.php - Página de detalle del producto
include 'db.php';

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();

include 'csrf.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$productId = intval($_GET['id'] ?? 0);
if ($productId <= 0) {
    header("Location: index.php");
    exit();
}

// Fetch product
$stmt = $pdo->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug, b.name as brand_name 
                        FROM products p 
                        LEFT JOIN categories c ON p.category_id = c.id 
                        LEFT JOIN brands b ON p.brand_id = b.id 
                        WHERE p.id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: index.php");
    exit();
}

// Fetch extra images
$stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order");
$stmt->execute([$productId]);
$extraImages = $stmt->fetchAll();

// Build image gallery (main image + extra images)
$gallery = [];
if ($product['image_url']) {
    $gallery[] = $product['image_url'];
}
foreach ($extraImages as $img) {
    $gallery[] = $img['image_url'];
}
if (empty($gallery)) {
    $gallery[] = null; // placeholder
}

// Fetch variants
$stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY size, color");
$stmt->execute([$productId]);
$variants = $stmt->fetchAll();

// Get unique sizes and colors
$sizes = array_unique(array_filter(array_column($variants, 'size')));
$colors = array_unique(array_filter(array_column($variants, 'color')));

// Fetch related products (same category)
$relatedProducts = [];
if ($product['category_id']) {
    $stmt = $pdo->prepare("SELECT id, name, price, image_url FROM products WHERE category_id = ? AND id != ? LIMIT 4");
    $stmt->execute([$product['category_id'], $productId]);
    $relatedProducts = $stmt->fetchAll();
}

$cartCount = array_sum($_SESSION['cart'] ?? []);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - FashionHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .product-detail { max-width: 1100px; margin: 30px auto; padding: 0 20px; }
        .product-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; background: white; padding: 30px; border-radius: 15px; box-shadow: var(--card-shadow); }
        @media (max-width: 768px) { .product-layout { grid-template-columns: 1fr; } }

        .gallery { position: relative; }
        .gallery-main { width: 100%; height: 450px; object-fit: cover; border-radius: 10px; cursor: pointer; }
        .gallery-main-placeholder { width: 100%; height: 450px; background: #f0f0f0; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 5rem; }
        .gallery-thumbs { display: flex; gap: 8px; margin-top: 10px; overflow-x: auto; }
        .gallery-thumb { width: 70px; height: 70px; object-fit: cover; border-radius: 6px; cursor: pointer; border: 2px solid transparent; transition: border-color 0.2s; }
        .gallery-thumb:hover, .gallery-thumb.active { border-color: var(--primary-color); }

        .product-meta { display: flex; flex-direction: column; }
        .product-meta h1 { font-size: 1.8rem; margin: 0 0 5px 0; }
        .brand-label { color: #888; font-size: 0.9rem; margin-bottom: 10px; }
        .category-link { display: inline-block; background: var(--primary-color); color: white; padding: 3px 12px; border-radius: 12px; font-size: 0.8rem; margin-bottom: 12px; text-decoration: none; }
        .price-display { font-size: 2rem; font-weight: 700; color: var(--primary-color); margin: 15px 0; }
        .stock-info { font-size: 0.9rem; margin-bottom: 15px; }
        .stock-info.in-stock { color: #00b894; }
        .stock-info.low-stock { color: #e17055; }
        .stock-info.out-of-stock { color: #d63031; }

        .desc-text { color: #555; line-height: 1.7; margin: 15px 0; }
        .sku-text { color: #999; font-size: 0.85rem; margin-bottom: 15px; }

        .variant-selector { margin: 15px 0; }
        .variant-selector label { display: block; font-weight: 600; margin-bottom: 8px; }
        .variant-options { display: flex; gap: 8px; flex-wrap: wrap; }
        .variant-btn { padding: 8px 16px; border: 2px solid #ddd; border-radius: 6px; cursor: pointer; background: white; font-size: 0.9rem; transition: all 0.2s; }
        .variant-btn:hover { border-color: var(--primary-color); }
        .variant-btn.selected { border-color: var(--primary-color); background: var(--primary-color); color: white; }
        .variant-btn.out-of-stock { opacity: 0.5; text-decoration: line-through; cursor: not-allowed; }

        .add-to-cart-form { margin-top: 20px; display: flex; gap: 10px; align-items: center; }
        .qty-input { width: 80px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; text-align: center; }
        .add-to-cart-btn { flex: 1; padding: 14px; font-size: 1.1rem; }

        .related-section { margin-top: 40px; }
        .related-section h2 { margin-bottom: 20px; }
        .related-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px; }
        .related-card { background: white; border-radius: 10px; overflow: hidden; box-shadow: var(--card-shadow); transition: transform 0.2s; }
        .related-card:hover { transform: translateY(-3px); }
        .related-card img { width: 100%; height: 180px; object-fit: cover; }
        .related-card .info { padding: 12px; }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="logo">FashionHub</div>
            <div class="nav-links">
                <a href="index.php">Inicio</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="cart.php">🛒 Carrito (<?php echo $cartCount; ?>)</a>
                    <a href="perfil.php">Mi Perfil</a>
                    <a href="logout.php">Cerrar Sesión</a>
                <?php else: ?>
                    <a href="login.php">Iniciar Sesión</a>
                    <a href="register.php">Registrarse</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <div class="product-detail">
        <!-- Breadcrumb -->
        <div style="margin-bottom:15px; font-size:0.9rem; color:#888;">
            <a href="index.php" style="color:var(--primary-color);">Inicio</a>
            <?php if ($product['category_name']): ?>
                → <a href="index.php?cat=<?php echo $product['category_id']; ?>" style="color:var(--primary-color);"><?php echo htmlspecialchars($product['category_name']); ?></a>
            <?php endif; ?>
            → <?php echo htmlspecialchars($product['name']); ?>
        </div>

        <div class="product-layout">
            <!-- Gallery -->
            <div class="gallery">
                <?php if ($gallery[0]): ?>
                    <img id="mainImage" src="<?php echo htmlspecialchars($gallery[0]); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="gallery-main">
                <?php else: ?>
                    <div class="gallery-main-placeholder">👕</div>
                <?php endif; ?>

                <?php if (count($gallery) > 1): ?>
                    <div class="gallery-thumbs">
                        <?php foreach ($gallery as $i => $img): ?>
                            <?php if ($img): ?>
                                <img src="<?php echo htmlspecialchars($img); ?>" class="gallery-thumb <?php echo $i === 0 ? 'active' : ''; ?>" onclick="changeImage(this, '<?php echo htmlspecialchars($img); ?>')">
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Product Info -->
            <div class="product-meta">
                <?php if ($product['category_name']): ?>
                    <a href="index.php?cat=<?php echo $product['category_id']; ?>" class="category-link"><?php echo htmlspecialchars($product['category_name']); ?></a>
                <?php endif; ?>

                <h1><?php echo htmlspecialchars($product['name']); ?></h1>

                <?php if ($product['brand_name']): ?>
                    <div class="brand-label">por <?php echo htmlspecialchars($product['brand_name']); ?></div>
                <?php endif; ?>

                <div class="price-display">$<?php echo number_format($product['price'], 2); ?></div>

                <?php if ($product['stock'] > 10): ?>
                    <div class="stock-info in-stock">✓ En stock</div>
                <?php elseif ($product['stock'] > 0): ?>
                    <div class="stock-info low-stock">⚠ Solo quedan <?php echo $product['stock']; ?> unidades</div>
                <?php else: ?>
                    <div class="stock-info out-of-stock">✕ Agotado</div>
                <?php endif; ?>

                <?php if ($product['sku']): ?>
                    <div class="sku-text">SKU: <?php echo htmlspecialchars($product['sku']); ?></div>
                <?php endif; ?>

                <div class="desc-text"><?php echo nl2br(htmlspecialchars($product['description'] ?? 'Sin descripción disponible.')); ?></div>

                <!-- Size Selector -->
                <?php if (!empty($sizes)): ?>
                    <div class="variant-selector">
                        <label>Talla</label>
                        <div class="variant-options">
                            <?php foreach ($sizes as $s): ?>
                                <button type="button" class="variant-btn" onclick="selectVariant(this, 'size', '<?php echo htmlspecialchars($s); ?>')"><?php echo htmlspecialchars($s); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Color Selector -->
                <?php if (!empty($colors)): ?>
                    <div class="variant-selector">
                        <label>Color</label>
                        <div class="variant-options">
                            <?php foreach ($colors as $c): ?>
                                <button type="button" class="variant-btn" onclick="selectVariant(this, 'color', '<?php echo htmlspecialchars($c); ?>')"><?php echo htmlspecialchars($c); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Add to Cart -->
                <?php if ($product['stock'] > 0): ?>
                    <form action="cart.php" method="POST" class="add-to-cart-form" id="addToCartForm">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <button type="submit" class="btn add-to-cart-btn">🛒 Agregar al Carrito</button>
                    </form>
                <?php else: ?>
                    <button class="btn add-to-cart-btn" disabled style="background:#ccc; cursor:not-allowed; margin-top:20px;">Producto Agotado</button>
                <?php endif; ?>

                <div style="margin-top:15px; font-size:0.85rem; color:#888;">
                    <p>📦 Envío estimado: 3-7 días hábiles</p>
                    <p>🔄 <a href="politicas.php" style="color:var(--primary-color);">Política de devoluciones</a></p>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php if (!empty($relatedProducts)): ?>
            <div class="related-section">
                <h2>Productos Relacionados</h2>
                <div class="related-grid">
                    <?php foreach ($relatedProducts as $rp): ?>
                        <a href="product.php?id=<?php echo $rp['id']; ?>" class="related-card" style="text-decoration:none; color:inherit;">
                            <?php if ($rp['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($rp['image_url']); ?>" alt="<?php echo htmlspecialchars($rp['name']); ?>">
                            <?php else: ?>
                                <div style="height:180px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; font-size:2rem;">👕</div>
                            <?php endif; ?>
                            <div class="info">
                                <div style="font-weight:600;"><?php echo htmlspecialchars($rp['name']); ?></div>
                                <div style="color:var(--primary-color); font-weight:700;">$<?php echo number_format($rp['price'], 2); ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer style="background:#2d3436; color:white; padding:30px 20px; margin-top:40px;">
        <div style="text-align:center; color:#636e72; font-size:0.85rem;">
            <a href="politicas.php" style="color:#b2bec3; margin:0 10px;">Políticas</a> |
            <a href="contacto.php" style="color:#b2bec3; margin:0 10px;">Contacto</a>
            <br><br>© <?php echo date('Y'); ?> FashionHub. Todos los derechos reservados.
        </div>
    </footer>

    <script>
        function changeImage(thumb, src) {
            document.getElementById('mainImage').src = src;
            document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
            thumb.classList.add('active');
        }

        let selectedVariants = {};
        function selectVariant(btn, type, value) {
            // Toggle selection
            const parent = btn.parentElement;
            parent.querySelectorAll('.variant-btn').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            selectedVariants[type] = value;
        }

        // AJAX add to cart
        const addForm = document.getElementById('addToCartForm');
        if (addForm) {
            addForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const fd = new FormData(this);
                fd.append('ajax', 'true');
                const btn = this.querySelector('button');
                const orig = btn.innerHTML;
                btn.innerHTML = 'Agregando...';
                btn.disabled = true;

                fetch('cart.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        btn.innerHTML = '¡Agregado! ✓';
                        btn.style.backgroundColor = '#00b894';
                        setTimeout(() => { btn.innerHTML = orig; btn.style.backgroundColor = ''; btn.disabled = false; }, 2000);
                    } else {
                        alert(data.message || 'Error');
                        btn.innerHTML = orig;
                        btn.disabled = false;
                    }
                }).catch(() => { btn.innerHTML = orig; btn.disabled = false; });
            });
        }
    </script>
</body>
</html>

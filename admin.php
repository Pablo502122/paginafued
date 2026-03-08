<?php
// admin.php
include 'db.php';

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();

include 'csrf.php';

// Admin/Operator Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'operator'])) {
    header("Location: login.php");
    exit();
}

$isAdmin = ($_SESSION['role'] === 'admin');
$message = '';

// Handle Product Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    csrf_validate();
    $action = $_POST['action'];

    // === PRODUCT ACTIONS ===
    if ($action == 'add_product' && $isAdmin) {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $stock = intval($_POST['stock'] ?? 0);
        $category_id = intval($_POST['category_id'] ?? 0) ?: null;
        $brand_id = intval($_POST['brand_id'] ?? 0) ?: null;
        $sku = trim($_POST['sku'] ?? '');

        if ($name === '' || $price <= 0) {
            $message = '<div class="messages error">Nombre y precio son obligatorios.</div>';
        } else {
            try {
                // Handle main image upload
                $image_url = '';
                if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, $allowed)) {
                        $new_filename = uniqid('prod_') . '.' . $ext;
                        $upload_path = 'uploads/' . $new_filename;
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                            $image_url = $upload_path;
                        }
                    }
                }

                $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock, image_url, category_id, brand_id, sku) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description, $price, $stock, $image_url, $category_id, $brand_id, $sku ?: null]);
                $newProductId = $pdo->lastInsertId();

                // Handle additional images
                if (isset($_FILES['extra_images'])) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    foreach ($_FILES['extra_images']['tmp_name'] as $i => $tmp) {
                        if ($_FILES['extra_images']['error'][$i] == 0) {
                            $ext = strtolower(pathinfo($_FILES['extra_images']['name'][$i], PATHINFO_EXTENSION));
                            if (in_array($ext, $allowed)) {
                                $fn = uniqid('img_') . '.' . $ext;
                                $path = 'uploads/' . $fn;
                                if (move_uploaded_file($tmp, $path)) {
                                    $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_url, sort_order) VALUES (?, ?, ?)");
                                    $stmt->execute([$newProductId, $path, $i]);
                                }
                            }
                        }
                    }
                }

                $message = '<div class="messages success">¡Producto agregado!</div>';
            } catch (PDOException $e) {
                $message = '<div class="messages error">Error al agregar producto.</div>';
                error_log("Add product error: " . $e->getMessage());
            }
        }

    } elseif ($action == 'edit_product' && $isAdmin) {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $stock = intval($_POST['stock'] ?? 0);
        $category_id = intval($_POST['category_id'] ?? 0) ?: null;
        $brand_id = intval($_POST['brand_id'] ?? 0) ?: null;
        $sku = trim($_POST['sku'] ?? '');
        $image_url = $_POST['existing_image_url'] ?? '';

        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $new_filename = uniqid('prod_') . '.' . $ext;
                $upload_path = 'uploads/' . $new_filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $image_url = $upload_path;
                }
            }
        }

        if ($name !== '' && $price > 0 && $id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE products SET name=?, description=?, price=?, stock=?, image_url=?, category_id=?, brand_id=?, sku=? WHERE id=?");
                $stmt->execute([$name, $description, $price, $stock, $image_url, $category_id, $brand_id, $sku ?: null, $id]);

                // Handle extra images
                if (isset($_FILES['extra_images'])) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    foreach ($_FILES['extra_images']['tmp_name'] as $i => $tmp) {
                        if ($_FILES['extra_images']['error'][$i] == 0) {
                            $ext = strtolower(pathinfo($_FILES['extra_images']['name'][$i], PATHINFO_EXTENSION));
                            if (in_array($ext, $allowed)) {
                                $fn = uniqid('img_') . '.' . $ext;
                                $path = 'uploads/' . $fn;
                                if (move_uploaded_file($tmp, $path)) {
                                    $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_url, sort_order) VALUES (?, ?, ?)");
                                    $stmt->execute([$id, $path, $i]);
                                }
                            }
                        }
                    }
                }

                $message = '<div class="messages success">¡Producto actualizado!</div>';
            } catch (PDOException $e) {
                $message = '<div class="messages error">Error al actualizar.</div>';
            }
        }

    } elseif ($action == 'delete_product' && $isAdmin) {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM product_variants WHERE product_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
                $message = '<div class="messages success">¡Producto eliminado!</div>';
            } catch (PDOException $e) {
                $message = '<div class="messages error">Error al eliminar.</div>';
            }
        }

    } elseif ($action == 'delete_image') {
        $imgId = intval($_POST['image_id'] ?? 0);
        if ($imgId > 0) {
            $pdo->prepare("DELETE FROM product_images WHERE id = ?")->execute([$imgId]);
            $message = '<div class="messages success">Imagen eliminada.</div>';
        }

    // === VARIANT ACTIONS ===
    } elseif ($action == 'add_variant' && $isAdmin) {
        $product_id = intval($_POST['product_id'] ?? 0);
        $size = trim($_POST['size'] ?? '');
        $color = trim($_POST['color'] ?? '');
        $vstock = intval($_POST['variant_stock'] ?? 0);
        $price_override = trim($_POST['price_override'] ?? '');
        $vsku = trim($_POST['variant_sku'] ?? '');

        if ($product_id > 0 && ($size !== '' || $color !== '')) {
            $stmt = $pdo->prepare("INSERT INTO product_variants (product_id, size, color, sku, stock, price_override) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$product_id, $size ?: null, $color ?: null, $vsku ?: null, $vstock, $price_override !== '' ? floatval($price_override) : null]);
            $message = '<div class="messages success">¡Variante agregada!</div>';
        }

    } elseif ($action == 'delete_variant' && $isAdmin) {
        $vid = intval($_POST['variant_id'] ?? 0);
        if ($vid > 0) {
            $pdo->prepare("DELETE FROM product_variants WHERE id = ?")->execute([$vid]);
            $message = '<div class="messages success">Variante eliminada.</div>';
        }

    // === CATEGORY ACTIONS ===
    } elseif ($action == 'add_category' && $isAdmin) {
        $name = trim($_POST['cat_name'] ?? '');
        $slug = trim($_POST['cat_slug'] ?? '');
        if ($name !== '' && $slug !== '') {
            try {
                $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)")->execute([$name, $slug]);
                $message = '<div class="messages success">¡Categoría agregada!</div>';
            } catch (PDOException $e) {
                $message = '<div class="messages error">La categoría ya existe.</div>';
            }
        }

    } elseif ($action == 'delete_category' && $isAdmin) {
        $id = intval($_POST['cat_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE products SET category_id = NULL WHERE category_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
            $message = '<div class="messages success">Categoría eliminada.</div>';
        }

    // === BRAND ACTIONS ===
    } elseif ($action == 'add_brand' && $isAdmin) {
        $name = trim($_POST['brand_name'] ?? '');
        if ($name !== '') {
            try {
                $pdo->prepare("INSERT INTO brands (name) VALUES (?)")->execute([$name]);
                $message = '<div class="messages success">¡Marca agregada!</div>';
            } catch (PDOException $e) {
                $message = '<div class="messages error">La marca ya existe.</div>';
            }
        }

    } elseif ($action == 'delete_brand' && $isAdmin) {
        $id = intval($_POST['brand_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE products SET brand_id = NULL WHERE brand_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM brands WHERE id = ?")->execute([$id]);
            $message = '<div class="messages success">Marca eliminada.</div>';
        }

    // === ORDER STATUS CHANGE ===
    } elseif ($action == 'change_order_status') {
        $ticket_id = intval($_POST['ticket_id'] ?? 0);
        $new_status = trim($_POST['new_status'] ?? '');
        $validStatuses = ['pending_payment', 'paid', 'processing', 'shipped', 'delivered', 'canceled', 'refunded'];

        if ($ticket_id > 0 && in_array($new_status, $validStatuses)) {
            $stmt = $pdo->prepare("SELECT status FROM tickets WHERE id = ?");
            $stmt->execute([$ticket_id]);
            $old = $stmt->fetchColumn();

            if ($old !== false && $old !== $new_status) {
                $pdo->prepare("UPDATE tickets SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$new_status, $ticket_id]);
                $pdo->prepare("INSERT INTO order_status_log (ticket_id, old_status, new_status, changed_by, note) VALUES (?, ?, ?, ?, 'Cambio manual desde admin')")
                    ->execute([$ticket_id, $old, $new_status, $_SESSION['username']]);

                // If canceled from pending_payment, restore stock
                if ($new_status === 'canceled' && in_array($old, ['pending_payment', 'pending'])) {
                    $items = $pdo->prepare("SELECT product_id, quantity FROM ticket_items WHERE ticket_id = ?");
                    $items->execute([$ticket_id]);
                    foreach ($items->fetchAll() as $it) {
                        $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$it['quantity'], $it['product_id']]);
                    }
                }

                $message = '<div class="messages success">Estado actualizado a ' . htmlspecialchars($new_status) . '</div>';
            }
        }
    }
}

// Fetch Data
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$brands = $pdo->query("SELECT * FROM brands ORDER BY name")->fetchAll();
$products = $pdo->query("SELECT p.*, c.name as category_name, b.name as brand_name FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN brands b ON p.brand_id = b.id ORDER BY p.id DESC")->fetchAll();

// Fetch variants for each product
$allVariants = [];
foreach ($products as $p) {
    $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ?");
    $stmt->execute([$p['id']]);
    $allVariants[$p['id']] = $stmt->fetchAll();
}

// Fetch extra images for each product
$allImages = [];
foreach ($products as $p) {
    $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order");
    $stmt->execute([$p['id']]);
    $allImages[$p['id']] = $stmt->fetchAll();
}

// Fetch orders with optional filters
$filterUserId = isset($_GET['filter_user']) && $_GET['filter_user'] !== '' ? intval($_GET['filter_user']) : null;
$filterStatus = isset($_GET['filter_status']) && $_GET['filter_status'] !== '' ? $_GET['filter_status'] : null;

$orderQuery = "SELECT t.*, u.username FROM tickets t JOIN users u ON t.user_id = u.id WHERE 1=1";
$orderParams = [];

if ($filterUserId) { $orderQuery .= " AND t.user_id = ?"; $orderParams[] = $filterUserId; }
if ($filterStatus) { $orderQuery .= " AND t.status = ?"; $orderParams[] = $filterStatus; }
$orderQuery .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($orderQuery);
$stmt->execute($orderParams);
$tickets = $stmt->fetchAll();

// Fetch ticket items
$allTicketItems = [];
foreach ($tickets as $t) {
    $stmt = $pdo->prepare("SELECT ti.*, p.name, p.image_url FROM ticket_items ti JOIN products p ON ti.product_id = p.id WHERE ti.ticket_id = ?");
    $stmt->execute([$t['id']]);
    $allTicketItems[$t['id']] = $stmt->fetchAll();
}

// Fetch order status logs
$allStatusLogs = [];
foreach ($tickets as $t) {
    $stmt = $pdo->prepare("SELECT * FROM order_status_log WHERE ticket_id = ? ORDER BY created_at DESC");
    $stmt->execute([$t['id']]);
    $allStatusLogs[$t['id']] = $stmt->fetchAll();
}

$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

$statusLabels = [
    'pending_payment' => ['label' => 'Pago Pendiente', 'class' => 'badge-pending'],
    'pending' => ['label' => 'Pendiente', 'class' => 'badge-pending'],
    'paid' => ['label' => 'Pagado', 'class' => 'badge-paid'],
    'processing' => ['label' => 'Procesando', 'class' => 'badge-processing'],
    'shipped' => ['label' => 'Enviado', 'class' => 'badge-shipped'],
    'delivered' => ['label' => 'Entregado', 'class' => 'badge-paid'],
    'canceled' => ['label' => 'Cancelado', 'class' => 'badge-rejected'],
    'rejected' => ['label' => 'Rechazado', 'class' => 'badge-rejected'],
    'refunded' => ['label' => 'Reembolsado', 'class' => 'badge-refunded'],
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin - FashionHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-section { padding: 2rem; background: white; margin-bottom: 2rem; border-radius: 10px; box-shadow: var(--card-shadow); }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .data-table th, .data-table td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; font-size: 0.9rem; }
        .data-table th { background: #f8f9fa; font-weight: 600; }
        .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 3% auto; padding: 25px; border-radius: 10px; width: 65%; max-width: 700px; max-height: 85vh; overflow-y: auto; }
        .close { float: right; font-size: 28px; cursor: pointer; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .badge-paid { background: #d4edda; color: #155724; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-refunded { background: #cce5ff; color: #004085; }
        .badge-processing { background: #e8daef; color: #6c3483; }
        .badge-shipped { background: #d6eaf8; color: #1b4f72; }
        .filter-bar { display: flex; gap: 15px; align-items: center; margin-bottom: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; flex-wrap: wrap; }
        .filter-bar select, .filter-bar input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 0.9rem; }
        .filter-bar .btn { padding: 8px 16px; font-size: 0.85rem; }
        .order-row { cursor: pointer; transition: background 0.2s; }
        .order-row:hover { background: #f0f4ff !important; }
        .detail-items { padding: 10px 0; }
        .detail-item { display: flex; align-items: center; gap: 12px; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .detail-item:last-child { border-bottom: none; }
        .detail-img { width: 45px; height: 45px; object-fit: cover; border-radius: 6px; }
        .mini-form { display: inline-flex; gap: 5px; align-items: center; }
        .mini-form select { padding: 4px 8px; font-size: 0.8rem; border: 1px solid #ddd; border-radius: 4px; }
        .mini-form button { padding: 4px 10px; font-size: 0.75rem; }
        .status-log { font-size: 0.8rem; color: #666; margin-top: 10px; }
        .status-log-item { padding: 4px 0; border-bottom: 1px dashed #eee; }
        .variant-table { width: 100%; margin-top: 10px; font-size: 0.85rem; }
        .variant-table th, .variant-table td { padding: 5px 8px; border: 1px solid #eee; }
        .cat-brand-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .cat-brand-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="sidebar">
            <h3 style="color:white;">Admin FashionHub</h3>
            <ul>
                <li><a href="#" onclick="showSection('products')" class="active">📦 Productos</a></li>
                <li><a href="#" onclick="showSection('orders')">📋 Órdenes</a></li>
                <li><a href="#" onclick="showSection('catalog')">🏷️ Categorías / Marcas</a></li>
                <li><a href="#" onclick="showSection('users')">👥 Usuarios</a></li>
                <li><a href="export_csv.php" target="_blank">📊 Exportar CSV</a></li>
                <li><a href="index.php">🏠 Ver Tienda</a></li>
                <li><a href="logout.php">🚪 Cerrar Sesión</a></li>
            </ul>
            <div style="margin-top:auto; padding-top:20px; font-size:0.8rem; color:#999;">
                Rol: <?php echo htmlspecialchars($_SESSION['role']); ?>
            </div>
        </div>

        <div class="main-content">
            <div class="admin-header">
                <h2>Panel de Control</h2>
                <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>

            <?php echo $message; ?>

            <!-- ==================== PRODUCTS SECTION ==================== -->
            <div id="products-section" class="admin-section">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <h3>📦 Gestión de Productos</h3>
                    <?php if ($isAdmin): ?>
                        <button class="btn" onclick="openProductModal('add')">+ Agregar Producto</button>
                    <?php endif; ?>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th><th>Imagen</th><th>Nombre</th><th>Categoría</th><th>Marca</th><th>Precio</th><th>Stock</th><th>Variantes</th>
                            <?php if ($isAdmin): ?><th>Acciones</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                        <tr>
                            <td><?php echo $p['id']; ?></td>
                            <td><?php if($p['image_url']): ?><img src="<?php echo htmlspecialchars($p['image_url']); ?>" width="50" style="border-radius:4px;"><?php endif; ?></td>
                            <td><strong><?php echo htmlspecialchars($p['name']); ?></strong><br><small style="color:#999;"><?php echo htmlspecialchars($p['sku'] ?? ''); ?></small></td>
                            <td><?php echo htmlspecialchars($p['category_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($p['brand_name'] ?? '-'); ?></td>
                            <td>$<?php echo number_format($p['price'], 2); ?></td>
                            <td><strong><?php echo $p['stock']; ?></strong></td>
                            <td><?php echo count($allVariants[$p['id']] ?? []); ?></td>
                            <?php if ($isAdmin): ?>
                            <td>
                                <button class="btn btn-secondary" style="font-size:0.8rem; padding:4px 10px;" onclick='openProductModal("edit", <?php echo json_encode($p); ?>)'>Editar</button>
                                <button class="btn btn-secondary" style="font-size:0.8rem; padding:4px 10px;" onclick="showVariants(<?php echo $p['id']; ?>)">Variantes</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar producto?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_product">
                                    <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                    <button class="btn btn-danger" style="font-size:0.8rem; padding:4px 10px;">✕</button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- ==================== ORDERS SECTION ==================== -->
            <div id="orders-section" class="admin-section" style="display:none;">
                <h3>📋 Gestión de Órdenes</h3>

                <div class="filter-bar">
                    <label><strong>Filtrar:</strong></label>
                    <select id="userFilter" onchange="applyOrderFilters()">
                        <option value="">Todos los usuarios</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo ($filterUserId == $u['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="statusFilter" onchange="applyOrderFilters()">
                        <option value="">Todos los estados</option>
                        <?php foreach ($statusLabels as $key => $sl): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($filterStatus === $key) ? 'selected' : ''; ?>><?php echo $sl['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($filterUserId || $filterStatus): ?>
                        <a href="admin.php" class="btn btn-secondary" onclick="setTimeout(()=>showSection('orders'),100)">Limpiar</a>
                    <?php endif; ?>
                    <span style="margin-left:auto; color:#666;"><?php echo count($tickets); ?> orden(es)</span>
                </div>

                <table class="data-table">
                    <thead>
                        <tr><th>ID</th><th>Usuario</th><th>Total</th><th>Método</th><th>Fecha</th><th>Estado</th><th>Conekta</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $t): ?>
                            <?php $st = $statusLabels[$t['status']] ?? $statusLabels['pending_payment']; ?>
                            <tr class="order-row">
                                <td>#<?php echo $t['id']; ?></td>
                                <td><?php echo htmlspecialchars($t['username']); ?></td>
                                <td><strong>$<?php echo number_format($t['total'], 2); ?></strong></td>
                                <td><?php echo htmlspecialchars($t['payment_method'] ?? 'card'); ?></td>
                                <td style="font-size:0.85rem;"><?php echo $t['created_at']; ?></td>
                                <td><span class="badge <?php echo $st['class']; ?>"><?php echo $st['label']; ?></span></td>
                                <td><span class="badge <?php echo ($t['conekta_status'] === 'paid' ? 'badge-paid' : ($t['conekta_status'] === 'pending_payment' || $t['conekta_status'] === 'pending' ? 'badge-pending' : 'badge-rejected')); ?>"><?php echo htmlspecialchars($t['conekta_status'] ?? 'N/A'); ?></span></td>
                                <td>
                                    <button class="btn btn-secondary" style="font-size:0.8rem; padding:4px 10px;" onclick="showOrderDetail(<?php echo $t['id']; ?>)">Detalle</button>
                                    <form method="POST" class="mini-form" style="margin-top:4px;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="change_order_status">
                                        <input type="hidden" name="ticket_id" value="<?php echo $t['id']; ?>">
                                        <select name="new_status">
                                            <?php foreach (['pending_payment','paid','processing','shipped','delivered','canceled','refunded'] as $s): ?>
                                                <option value="<?php echo $s; ?>" <?php echo ($t['status'] === $s) ? 'selected' : ''; ?>><?php echo $statusLabels[$s]['label'] ?? $s; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn" style="padding:4px 10px; font-size:0.75rem;">Cambiar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- ==================== CATEGORIES & BRANDS SECTION ==================== -->
            <div id="catalog-section" class="admin-section" style="display:none;">
                <h3>🏷️ Categorías y Marcas</h3>
                <?php if ($isAdmin): ?>
                <div class="cat-brand-grid">
                    <div>
                        <h4>Categorías</h4>
                        <form method="POST" style="display:flex; gap:8px; margin-bottom:15px;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="add_category">
                            <input type="text" name="cat_name" placeholder="Nombre" required style="flex:1; padding:8px; border:1px solid #ddd; border-radius:5px;">
                            <input type="text" name="cat_slug" placeholder="slug" required style="flex:1; padding:8px; border:1px solid #ddd; border-radius:5px;">
                            <button class="btn" style="padding:8px 16px;">+</button>
                        </form>
                        <table class="data-table">
                            <thead><tr><th>ID</th><th>Nombre</th><th>Slug</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($categories as $c): ?>
                                <tr>
                                    <td><?php echo $c['id']; ?></td>
                                    <td><?php echo htmlspecialchars($c['name']); ?></td>
                                    <td><code><?php echo htmlspecialchars($c['slug']); ?></code></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar categoría?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="cat_id" value="<?php echo $c['id']; ?>">
                                            <button class="btn btn-danger" style="font-size:0.8rem; padding:3px 8px;">✕</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div>
                        <h4>Marcas</h4>
                        <form method="POST" style="display:flex; gap:8px; margin-bottom:15px;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="add_brand">
                            <input type="text" name="brand_name" placeholder="Nombre de marca" required style="flex:1; padding:8px; border:1px solid #ddd; border-radius:5px;">
                            <button class="btn" style="padding:8px 16px;">+</button>
                        </form>
                        <table class="data-table">
                            <thead><tr><th>ID</th><th>Nombre</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($brands as $b): ?>
                                <tr>
                                    <td><?php echo $b['id']; ?></td>
                                    <td><?php echo htmlspecialchars($b['name']); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar marca?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete_brand">
                                            <input type="hidden" name="brand_id" value="<?php echo $b['id']; ?>">
                                            <button class="btn btn-danger" style="font-size:0.8rem; padding:3px 8px;">✕</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                    <p>Solo administradores pueden gestionar categorías y marcas.</p>
                <?php endif; ?>
            </div>

            <!-- ==================== USERS SECTION ==================== -->
            <div id="users-section" class="admin-section" style="display:none;">
                <h3>👥 Usuarios Registrados</h3>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Usuario</th><th>Email</th><th>Rol</th><th>Teléfono</th><th>Dirección</th><th>Registro</th></tr></thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo $u['id']; ?></td>
                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><span class="badge <?php echo $u['role'] === 'admin' ? 'badge-paid' : ($u['role'] === 'operator' ? 'badge-processing' : 'badge-pending'); ?>"><?php echo $u['role']; ?></span></td>
                            <td><?php echo htmlspecialchars($u['phone'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($u['address'] ?? '-'); ?></td>
                            <td><?php echo $u['created_at']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ==================== ORDER DETAIL MODAL ==================== -->
    <div id="orderDetailModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeOrderDetail()">&times;</span>
            <div id="orderDetailContent"></div>
        </div>
    </div>

    <!-- ==================== PRODUCT MODAL ==================== -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeProductModal()">&times;</span>
            <h2 id="modalTitle">Agregar Producto</h2>
            <form method="POST" id="productForm" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" id="formAction" value="add_product">
                <input type="hidden" name="id" id="productId">
                <input type="hidden" name="existing_image_url" id="existingImageUrl">

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Nombre *</label>
                        <input type="text" name="name" id="productName" required>
                    </div>
                    <div class="form-group">
                        <label>SKU</label>
                        <input type="text" name="sku" id="productSku">
                    </div>
                    <div class="form-group">
                        <label>Precio *</label>
                        <input type="number" step="0.01" min="0.01" name="price" id="productPrice" required>
                    </div>
                    <div class="form-group">
                        <label>Stock</label>
                        <input type="number" min="0" name="stock" id="productStock" value="0" required>
                    </div>
                    <div class="form-group">
                        <label>Categoría</label>
                        <select name="category_id" id="productCategory">
                            <option value="">Sin categoría</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Marca</label>
                        <select name="brand_id" id="productBrand">
                            <option value="">Sin marca</option>
                            <?php foreach ($brands as $b): ?>
                                <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="description" id="productDesc" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Imagen Principal</label>
                    <input type="file" name="image" accept="image/*">
                    <div id="imagePreview" style="margin-top:10px;"></div>
                </div>
                <div class="form-group">
                    <label>Imágenes Adicionales (hasta 5)</label>
                    <input type="file" name="extra_images[]" multiple accept="image/*">
                </div>
                <button type="submit" class="btn" style="width:100%;">Guardar Producto</button>
            </form>
        </div>
    </div>

    <!-- ==================== VARIANT MODAL ==================== -->
    <div id="variantModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeVariantModal()">&times;</span>
            <h2>Variantes del Producto</h2>
            <div id="variantContent"></div>
            <hr style="margin:15px 0;">
            <h4>Agregar Variante</h4>
            <form method="POST" id="variantForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add_variant">
                <input type="hidden" name="product_id" id="variantProductId">
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
                    <div class="form-group"><label>Talla</label><input type="text" name="size" placeholder="S, M, L, XL..."></div>
                    <div class="form-group"><label>Color</label><input type="text" name="color" placeholder="Rojo, Azul..."></div>
                    <div class="form-group"><label>SKU Variante</label><input type="text" name="variant_sku"></div>
                    <div class="form-group"><label>Stock</label><input type="number" name="variant_stock" value="0" min="0"></div>
                    <div class="form-group"><label>Precio Override</label><input type="number" step="0.01" name="price_override" placeholder="Dejar vacío = precio base"></div>
                </div>
                <button type="submit" class="btn" style="margin-top:10px;">Agregar Variante</button>
            </form>
        </div>
    </div>

    <script>
        // === Section visibility ===
        function showSection(section) {
            ['products','orders','catalog','users'].forEach(s => {
                document.getElementById(s + '-section').style.display = 'none';
            });
            document.getElementById(section + '-section').style.display = 'block';
            // Update sidebar active
            document.querySelectorAll('.sidebar a').forEach(a => a.classList.remove('active'));
        }

        <?php if ($filterUserId || $filterStatus): ?>
        document.addEventListener('DOMContentLoaded', () => showSection('orders'));
        <?php endif; ?>

        // === Order Filters ===
        function applyOrderFilters() {
            const userId = document.getElementById('userFilter').value;
            const status = document.getElementById('statusFilter').value;
            let url = 'admin.php?';
            if (userId) url += 'filter_user=' + userId + '&';
            if (status) url += 'filter_status=' + status + '&';
            window.location.href = url;
        }

        // === Order Detail ===
        var orderDetails = <?php echo json_encode($allTicketItems); ?>;
        var ticketsData = {};
        var statusLogs = <?php echo json_encode($allStatusLogs); ?>;
        <?php foreach ($tickets as $t): ?>
        ticketsData[<?php echo $t['id']; ?>] = <?php echo json_encode($t); ?>;
        <?php endforeach; ?>

        function showOrderDetail(ticketId) {
            var ticket = ticketsData[ticketId];
            var items = orderDetails[ticketId] || [];
            var logs = statusLogs[ticketId] || [];

            var html = '<h2>Orden #' + ticketId + '</h2>';
            html += '<div style="display:flex;gap:15px;flex-wrap:wrap;margin-bottom:15px;">';
            html += '<div><strong>Usuario:</strong> ' + (ticket.username || '') + '</div>';
            html += '<div><strong>Total:</strong> $' + parseFloat(ticket.total).toFixed(2) + '</div>';
            html += '<div><strong>Método:</strong> ' + (ticket.payment_method || 'card') + '</div>';
            html += '<div><strong>Fecha:</strong> ' + (ticket.created_at || '') + '</div>';
            html += '</div>';

            if (ticket.shipping_address) {
                html += '<div style="background:#f8f9fa;padding:10px;border-radius:8px;margin-bottom:15px;"><strong>📍 Envío:</strong> ' + ticket.shipping_address + '</div>';
            }

            if (items.length > 0) {
                html += '<h3>Productos</h3><div class="detail-items">';
                items.forEach(function(item) {
                    html += '<div class="detail-item">';
                    if (item.image_url) html += '<img src="' + item.image_url + '" class="detail-img">';
                    else html += '<div class="detail-img" style="background:#eee;display:flex;align-items:center;justify-content:center;">📦</div>';
                    html += '<span style="flex:1;font-weight:500;">' + item.name + '</span>';
                    html += '<span style="color:#666;">x' + item.quantity + '</span>';
                    html += '<span style="font-weight:600;">$' + (item.price * item.quantity).toFixed(2) + '</span>';
                    html += '</div>';
                });
                html += '</div>';
            }

            if (logs.length > 0) {
                html += '<h4 style="margin-top:15px;">📜 Historial de Estados</h4><div class="status-log">';
                logs.forEach(function(log) {
                    html += '<div class="status-log-item">';
                    html += '<strong>' + (log.old_status || '—') + '</strong> → <strong>' + log.new_status + '</strong>';
                    html += ' <span style="color:#999;">(' + log.changed_by + ', ' + log.created_at + ')</span>';
                    if (log.note) html += '<br><small>' + log.note + '</small>';
                    html += '</div>';
                });
                html += '</div>';
            }

            document.getElementById('orderDetailContent').innerHTML = html;
            document.getElementById('orderDetailModal').style.display = 'block';
        }

        function closeOrderDetail() { document.getElementById('orderDetailModal').style.display = 'none'; }

        // === Product Modal ===
        function openProductModal(mode, product) {
            document.getElementById('productModal').style.display = 'block';
            if (mode === 'edit' && product) {
                document.getElementById('modalTitle').innerText = 'Editar Producto';
                document.getElementById('formAction').value = 'edit_product';
                document.getElementById('productId').value = product.id;
                document.getElementById('productName').value = product.name;
                document.getElementById('productDesc').value = product.description || '';
                document.getElementById('productPrice').value = product.price;
                document.getElementById('productStock').value = product.stock;
                document.getElementById('productSku').value = product.sku || '';
                document.getElementById('productCategory').value = product.category_id || '';
                document.getElementById('productBrand').value = product.brand_id || '';
                document.getElementById('existingImageUrl').value = product.image_url || '';
                document.getElementById('imagePreview').innerHTML = product.image_url ? '<img src="' + product.image_url + '" width="100" style="border-radius:6px;">' : '';
            } else {
                document.getElementById('modalTitle').innerText = 'Agregar Producto';
                document.getElementById('formAction').value = 'add_product';
                document.getElementById('productForm').reset();
                document.getElementById('imagePreview').innerHTML = '';
                document.getElementById('existingImageUrl').value = '';
            }
        }
        function closeProductModal() { document.getElementById('productModal').style.display = 'none'; }

        // === Variant Modal ===
        var allVariantsData = <?php echo json_encode($allVariants); ?>;
        function showVariants(productId) {
            document.getElementById('variantProductId').value = productId;
            var variants = allVariantsData[productId] || [];
            var html = '';
            if (variants.length === 0) {
                html = '<p style="color:#666;">No hay variantes para este producto.</p>';
            } else {
                html = '<table class="variant-table"><thead><tr><th>Talla</th><th>Color</th><th>SKU</th><th>Stock</th><th>Precio</th><th></th></tr></thead><tbody>';
                variants.forEach(function(v) {
                    html += '<tr>';
                    html += '<td>' + (v.size || '-') + '</td>';
                    html += '<td>' + (v.color || '-') + '</td>';
                    html += '<td>' + (v.sku || '-') + '</td>';
                    html += '<td>' + v.stock + '</td>';
                    html += '<td>' + (v.price_override ? '$' + parseFloat(v.price_override).toFixed(2) : 'Base') + '</td>';
                    html += '<td><form method="POST" style="display:inline;" onsubmit="return confirm(\'¿Eliminar?\');"><input type="hidden" name="action" value="delete_variant"><input type="hidden" name="variant_id" value="' + v.id + '"><input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>"><button class="btn btn-danger" style="font-size:0.75rem;padding:2px 8px;">✕</button></form></td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
            }
            document.getElementById('variantContent').innerHTML = html;
            document.getElementById('variantModal').style.display = 'block';
        }
        function closeVariantModal() { document.getElementById('variantModal').style.display = 'none'; }

        // Close modals on outside click
        window.onclick = function(event) {
            ['productModal','orderDetailModal','variantModal'].forEach(id => {
                if (event.target == document.getElementById(id)) {
                    document.getElementById(id).style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>

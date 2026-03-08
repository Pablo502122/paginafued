<?php
// cart.php
include 'db.php';

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();

include 'csrf.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$message = '';

// Helper: Fetch products by IDs using prepared statements (fix SQL injection)
function fetchProductsByIds($pdo, $ids) {
    if (empty($ids)) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->execute(array_values($ids));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    // CSRF validation for all non-AJAX cart actions
    $isAjax = isset($_POST['ajax']) && $_POST['ajax'] === 'true';
    if (!$isAjax && in_array($action, ['update', 'remove', 'checkout'])) {
        csrf_validate();
    }

    if ($action == 'add') {
        $product_id = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
        if (!$product_id || $product_id <= 0) {
            if ($isAjax) {
                echo json_encode(['status' => 'error', 'message' => 'Producto no válido.']);
                exit();
            }
            header("Location: cart.php");
            exit();
        }

        $requested_qty = 1;

        // Check current stock
        $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            $current_qty_in_cart = $_SESSION['cart'][$product_id] ?? 0;

            if (($current_qty_in_cart + $requested_qty) <= $product['stock']) {
                $_SESSION['cart'][$product_id] = $current_qty_in_cart + 1;

                if ($isAjax) {
                    echo json_encode(['status' => 'success', 'message' => 'Producto agregado al carrito', 'cart_count' => array_sum($_SESSION['cart'])]);
                    exit();
                }
            } else {
                if ($isAjax) {
                    echo json_encode(['status' => 'error', 'message' => 'No hay suficiente stock disponible.']);
                    exit();
                }
                $message = '<div class="messages error">No hay suficiente stock para este producto.</div>';
            }
        }

        if (!$isAjax) {
            header("Location: cart.php");
            exit();
        }

    } elseif ($action == 'update') {
        $product_id = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
        $qty = filter_var($_POST['qty'] ?? 0, FILTER_VALIDATE_INT);

        if ($product_id && $product_id > 0 && $qty !== false) {
            if ($qty <= 0) {
                // Remove from cart if qty is 0 or negative
                unset($_SESSION['cart'][$product_id]);
                $message = '<div class="messages success">Producto eliminado del carrito.</div>';
            } else {
                // Check stock
                $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($product) {
                    if ($qty <= $product['stock']) {
                        $_SESSION['cart'][$product_id] = $qty;
                        $message = '<div class="messages success">Carrito actualizado.</div>';
                    } else {
                        $_SESSION['cart'][$product_id] = $product['stock'];
                        $message = '<div class="messages warning">Cantidad ajustada al stock máximo disponible (' . $product['stock'] . ').</div>';
                    }
                }
            }
        }

    } elseif ($action == 'remove') {
        $product_id = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($product_id) {
            unset($_SESSION['cart'][$product_id]);
            $message = '<div class="messages success">Producto eliminado.</div>';
        }
    }
}

// Fetch Cart Details (using prepared statements)
$cart_products = [];
$total_price = 0;
if (!empty($_SESSION['cart'])) {
    $cart_ids = array_keys($_SESSION['cart']);
    $products = fetchProductsByIds($pdo, $cart_ids);
    foreach ($products as $row) {
        $row['qty'] = $_SESSION['cart'][$row['id']];
        $row['line_total'] = $row['price'] * $row['qty'];
        $total_price += $row['line_total'];
        $cart_products[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito de Compras - FashionHub</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">FashionHub</div>
            <div class="nav-links">
                <a href="index.php">Inicio</a>
                <a href="cart.php" style="color: var(--primary-color);">Carrito (<?php echo array_sum($_SESSION['cart']); ?>)</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="perfil.php">Mi Perfil</a>
                    <a href="logout.php">Cerrar Sesión</a>
                <?php else: ?>
                    <a href="login.php">Iniciar Sesión</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <div class="container">
        <h1>Tu Carrito de Compras</h1>
        <?php echo $message; ?>
        <br>

        <?php if (empty($cart_products)): ?>
            <p>Tu carrito está vacío. <a href="index.php" style="color: var(--primary-color);">Empezar a comprar</a></p>
        <?php else: ?>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Precio</th>
                        <th>Cantidad</th>
                        <th>Total</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart_products as $item): ?>
                        <tr>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <?php if ($item['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="" style="width:50px; height:50px; object-fit:cover; border-radius:6px;">
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </div>
                            </td>
                            <td>$<?php echo number_format($item['price'], 2); ?></td>
                            <td>
                                <form action="cart.php" method="POST" style="display: flex; gap: 5px; align-items: center;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                    <input type="number" name="qty" value="<?php echo $item['qty']; ?>" min="1" max="<?php echo $item['stock']; ?>" style="width: 60px; padding: 5px;">
                                    <button type="submit" class="btn" style="padding: 5px 10px; font-size: 0.8rem;">Actualizar</button>
                                </form>
                                <small style="color: #666;">Disponibles: <?php echo $item['stock']; ?></small>
                            </td>
                            <td>$<?php echo number_format($item['line_total'], 2); ?></td>
                            <td>
                                <form action="cart.php" method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 0.9rem;">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="3" style="text-align: right; font-weight: bold;">Gran Total:</td>
                        <td colspan="2" style="font-weight: bold; color: var(--primary-color);">$<?php echo number_format($total_price, 2); ?></td>
                    </tr>
                </tbody>
            </table>

            <div style="text-align: right;">
                <a href="checkout.php" class="btn">Proceder al Pago</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
// perfil.php - Perfil de usuario mejorado con historial de pedidos
include 'db.php';

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();

include 'csrf.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle profile update
$profileMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    csrf_validate();
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $profileMsg = '<div class="messages error">Email no válido.</div>';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET address = ?, phone = ?, email = ? WHERE id = ?");
            $stmt->execute([$address, $phone, $email, $user_id]);
            $profileMsg = '<div class="messages success">Perfil actualizado.</div>';
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            $profileMsg = '<div class="messages error">Error al actualizar el perfil.</div>';
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    csrf_validate();
    $currentPw = $_POST['current_password'] ?? '';
    $newPw = $_POST['new_password'] ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';

    if ($newPw !== $confirmPw) {
        $profileMsg = '<div class="messages error">Las contraseñas nuevas no coinciden.</div>';
    } elseif (strlen($newPw) < 6) {
        $profileMsg = '<div class="messages error">La contraseña debe tener al menos 6 caracteres.</div>';
    } elseif (!password_verify($currentPw, $user['password'])) {
        $profileMsg = '<div class="messages error">La contraseña actual es incorrecta.</div>';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([password_hash($newPw, PASSWORD_DEFAULT), $user_id]);
        $profileMsg = '<div class="messages success">Contraseña actualizada correctamente.</div>';
    }
}

// Fetch user orders (paginated)
$ordersPage = max(1, intval($_GET['op'] ?? 1));
$ordersPerPage = 10;
$ordersOffset = ($ordersPage - 1) * $ordersPerPage;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = ?");
$countStmt->execute([$user_id]);
$totalOrders = $countStmt->fetchColumn();
$totalOrderPages = max(1, ceil($totalOrders / $ordersPerPage));

$stmt = $pdo->prepare("SELECT t.* FROM tickets t WHERE t.user_id = ? ORDER BY t.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([$user_id, $ordersPerPage, $ordersOffset]);
$tickets = $stmt->fetchAll();

// Fetch items for each ticket
$ticketItems = [];
foreach ($tickets as $t) {
    $stmt = $pdo->prepare("SELECT ti.*, p.name, p.image_url FROM ticket_items ti JOIN products p ON ti.product_id = p.id WHERE ti.ticket_id = ?");
    $stmt->execute([$t['id']]);
    $ticketItems[$t['id']] = $stmt->fetchAll();
}

$statusLabels = [
    'pending_payment' => ['label' => 'Pago Pendiente', 'icon' => '⏳', 'class' => 'badge-pending'],
    'pending' => ['label' => 'Pendiente', 'icon' => '⏳', 'class' => 'badge-pending'],
    'paid' => ['label' => 'Pagado', 'icon' => '✅', 'class' => 'badge-paid'],
    'processing' => ['label' => 'Procesando', 'icon' => '📦', 'class' => 'badge-processing'],
    'shipped' => ['label' => 'Enviado', 'icon' => '🚚', 'class' => 'badge-shipped'],
    'delivered' => ['label' => 'Entregado', 'icon' => '🎉', 'class' => 'badge-paid'],
    'canceled' => ['label' => 'Cancelado', 'icon' => '❌', 'class' => 'badge-rejected'],
    'rejected' => ['label' => 'Rechazado', 'icon' => '❌', 'class' => 'badge-rejected'],
    'refunded' => ['label' => 'Reembolsado', 'icon' => '💸', 'class' => 'badge-refunded'],
];

$cartCount = array_sum($_SESSION['cart'] ?? []);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - FashionHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .profile-container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        .profile-grid { display: grid; grid-template-columns: 320px 1fr; gap: 25px; }
        @media (max-width: 768px) { .profile-grid { grid-template-columns: 1fr; } }

        .profile-sidebar { background: white; padding: 25px; border-radius: 15px; box-shadow: var(--card-shadow); height: fit-content; }
        .profile-avatar { width: 80px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: white; margin: 0 auto 15px; }

        .profile-main { }
        .section-card { background: white; padding: 25px; border-radius: 15px; box-shadow: var(--card-shadow); margin-bottom: 20px; }

        .order-card { border: 1px solid #eee; border-radius: 10px; padding: 15px; margin-bottom: 12px; transition: border-color 0.2s; }
        .order-card:hover { border-color: var(--primary-color); }
        .order-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px; }
        .order-products { display: flex; gap: 8px; flex-wrap: wrap; }
        .order-product-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 5px; }
        .order-product-placeholder { width: 40px; height: 40px; background: #f0f0f0; border-radius: 5px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; }

        .badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
        .badge-paid { background: #d4edda; color: #155724; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-processing { background: #e8daef; color: #6c3483; }
        .badge-shipped { background: #d6eaf8; color: #1b4f72; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        .badge-refunded { background: #cce5ff; color: #004085; }

        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 15px; }
        .pagination a, .pagination span { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 5px; font-size: 0.85rem; text-decoration: none; }
        .pagination a { background: #f0f0f0; color: #333; }
        .pagination a:hover { background: var(--primary-color); color: white; }
        .pagination span.current { background: var(--primary-color); color: white; }

        .tab-buttons { display: flex; gap: 0; border-bottom: 2px solid #eee; margin-bottom: 20px; }
        .tab-btn { padding: 10px 20px; font-weight: 600; color: #888; cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.2s; }
        .tab-btn:hover { color: var(--primary-color); }
        .tab-btn.active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="logo">FashionHub</div>
            <div class="nav-links">
                <a href="index.php">Inicio</a>
                <a href="cart.php">🛒 Carrito (<?php echo $cartCount; ?>)</a>
                <a href="perfil.php" style="color:var(--primary-color);">Mi Perfil</a>
                <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'operator'])): ?>
                    <a href="admin.php">Panel Admin</a>
                <?php endif; ?>
                <a href="logout.php">Cerrar Sesión</a>
            </div>
        </nav>
    </header>

    <div class="profile-container">
        <?php echo $profileMsg; ?>

        <div class="profile-grid">
            <!-- Sidebar -->
            <div class="profile-sidebar">
                <div class="profile-avatar"><?php echo mb_strtoupper(mb_substr($user['username'], 0, 1)); ?></div>
                <h2 style="text-align:center; margin:0;"><?php echo htmlspecialchars($user['username']); ?></h2>
                <p style="text-align:center; color:#888; font-size:0.9rem; margin-bottom:20px;"><?php echo htmlspecialchars($user['email']); ?></p>

                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+52 618 ...">
                    </div>
                    <div class="form-group">
                        <label>Dirección</label>
                        <textarea name="address" rows="3" style="resize:vertical;"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn" style="width:100%;">Guardar Perfil</button>
                </form>

                <hr style="margin:20px 0; border:0; border-top:1px solid #eee;">

                <!-- Change Password -->
                <h4 style="margin-bottom:10px;">🔒 Cambiar Contraseña</h4>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label>Contraseña Actual</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>Nueva Contraseña</label>
                        <input type="password" name="new_password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Confirmar Contraseña</label>
                        <input type="password" name="confirm_password" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-secondary" style="width:100%;">Cambiar Contraseña</button>
                </form>

                <div style="margin-top:20px; text-align:center; font-size:0.85rem; color:#999;">
                    Miembro desde: <?php echo $user['created_at']; ?>
                </div>
            </div>

            <!-- Main Content -->
            <div class="profile-main">
                <div class="section-card">
                    <h2 style="margin:0 0 5px 0;">📋 Mis Pedidos</h2>
                    <p style="color:#888; margin-bottom:15px;"><?php echo $totalOrders; ?> pedido(s) en total</p>

                    <?php if (empty($tickets)): ?>
                        <div style="text-align:center; padding:40px; color:#888;">
                            <div style="font-size:3rem; margin-bottom:10px;">🛍️</div>
                            <h3>Aún no tienes pedidos</h3>
                            <p>¡Explora nuestra <a href="index.php" style="color:var(--primary-color);">colección</a> y realiza tu primera compra!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($tickets as $t): ?>
                            <?php $st = $statusLabels[$t['status']] ?? $statusLabels['pending_payment']; ?>
                            <div class="order-card">
                                <div class="order-header">
                                    <div>
                                        <a href="ticket.php?id=<?php echo $t['id']; ?>" style="font-weight:600; color:var(--primary-color); text-decoration:none;">Pedido #<?php echo $t['id']; ?></a>
                                        <span style="color:#999; font-size:0.85rem; margin-left:10px;"><?php echo $t['created_at']; ?></span>
                                    </div>
                                    <div style="display:flex; gap:8px; align-items:center;">
                                        <span class="badge <?php echo $st['class']; ?>"><?php echo $st['icon']; ?> <?php echo $st['label']; ?></span>
                                        <strong style="color:var(--primary-color);">$<?php echo number_format($t['total'], 2); ?></strong>
                                    </div>
                                </div>
                                <div class="order-products">
                                    <?php foreach ($ticketItems[$t['id']] ?? [] as $item): ?>
                                        <?php if ($item['image_url']): ?>
                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="order-product-thumb" title="<?php echo htmlspecialchars($item['name']); ?> x<?php echo $item['quantity']; ?>">
                                        <?php else: ?>
                                            <div class="order-product-placeholder" title="<?php echo htmlspecialchars($item['name']); ?>">📦</div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <span style="color:#888; font-size:0.85rem; display:flex; align-items:center; margin-left:5px;">
                                        <?php
                                        $totalItemsCount = array_sum(array_column($ticketItems[$t['id']] ?? [], 'quantity'));
                                        echo $totalItemsCount . ' artículo(s)';
                                        ?>
                                        · <?php echo ($t['payment_method'] ?? 'card') === 'oxxo' ? '🏪 OXXO' : '💳 Tarjeta'; ?>
                                    </span>
                                </div>
                                <div style="margin-top:8px;">
                                    <a href="ticket.php?id=<?php echo $t['id']; ?>" style="color:var(--primary-color); font-size:0.85rem;">Ver detalle →</a>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Orders Pagination -->
                        <?php if ($totalOrderPages > 1): ?>
                            <div class="pagination">
                                <?php for ($i = 1; $i <= $totalOrderPages; $i++): ?>
                                    <?php if ($i == $ordersPage): ?>
                                        <span class="current"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="perfil.php?op=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer style="background:#2d3436; color:white; padding:30px 20px; margin-top:40px;">
        <div style="text-align:center; color:#636e72; font-size:0.85rem;">
            <a href="politicas.php" style="color:#b2bec3; margin:0 10px;">Políticas</a> |
            <a href="contacto.php" style="color:#b2bec3; margin:0 10px;">Contacto</a>
            <br><br>© <?php echo date('Y'); ?> FashionHub. Todos los derechos reservados.
        </div>
    </footer>
</body>
</html>

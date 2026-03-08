<?php
// ticket.php - Detalle de pedido con tracking
include 'db.php';

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$ticket_id = intval($_GET['id'] ?? 0);
if ($ticket_id <= 0) {
    header("Location: perfil.php");
    exit();
}

// Fetch ticket (only owner or admin can see)
$stmt = $pdo->prepare("SELECT t.*, u.username, u.email FROM tickets t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch();

if (!$ticket || ($ticket['user_id'] != $_SESSION['user_id'] && !in_array($_SESSION['role'] ?? '', ['admin', 'operator']))) {
    header("Location: perfil.php");
    exit();
}

// Fetch ticket items
$stmt = $pdo->prepare("SELECT ti.*, p.name, p.image_url FROM ticket_items ti JOIN products p ON ti.product_id = p.id WHERE ti.ticket_id = ?");
$stmt->execute([$ticket_id]);
$items = $stmt->fetchAll();

// Fetch status history
$stmt = $pdo->prepare("SELECT * FROM order_status_log WHERE ticket_id = ? ORDER BY created_at ASC");
$stmt->execute([$ticket_id]);
$statusLog = $stmt->fetchAll();

$statusLabels = [
    'pending_payment' => ['label' => 'Pago Pendiente', 'icon' => '⏳', 'color' => '#f39c12'],
    'pending' => ['label' => 'Pendiente', 'icon' => '⏳', 'color' => '#f39c12'],
    'paid' => ['label' => 'Pagado', 'icon' => '✅', 'color' => '#27ae60'],
    'processing' => ['label' => 'Procesando', 'icon' => '📦', 'color' => '#8e44ad'],
    'shipped' => ['label' => 'Enviado', 'icon' => '🚚', 'color' => '#2980b9'],
    'delivered' => ['label' => 'Entregado', 'icon' => '🎉', 'color' => '#27ae60'],
    'canceled' => ['label' => 'Cancelado', 'icon' => '❌', 'color' => '#e74c3c'],
    'rejected' => ['label' => 'Rechazado', 'icon' => '❌', 'color' => '#e74c3c'],
    'refunded' => ['label' => 'Reembolsado', 'icon' => '💸', 'color' => '#3498db'],
];

$currentStatus = $statusLabels[$ticket['status']] ?? $statusLabels['pending_payment'];

$cartCount = array_sum($_SESSION['cart'] ?? []);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido #<?php echo $ticket_id; ?> - FashionHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .ticket-container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
        .ticket-card { background: white; padding: 30px; border-radius: 15px; box-shadow: var(--card-shadow); margin-bottom: 20px; }
        .ticket-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .status-badge { display: inline-flex; gap: 5px; align-items: center; padding: 8px 18px; border-radius: 20px; font-weight: 600; font-size: 0.95rem; }
        .ticket-items { border: 1px solid #f0f0f0; border-radius: 10px; overflow: hidden; }
        .ticket-item { display: flex; align-items: center; gap: 15px; padding: 15px; border-bottom: 1px solid #f0f0f0; }
        .ticket-item:last-child { border-bottom: none; }
        .ticket-item img { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; }
        .ticket-item .placeholder-img { width: 60px; height: 60px; background: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }

        .timeline { position: relative; padding-left: 30px; }
        .timeline::before { content: ''; position: absolute; left: 12px; top: 0; bottom: 0; width: 2px; background: #eee; }
        .timeline-item { position: relative; padding-bottom: 20px; }
        .timeline-item:last-child { padding-bottom: 0; }
        .timeline-dot { position: absolute; left: -24px; top: 3px; width: 14px; height: 14px; border-radius: 50%; background: #ddd; border: 2px solid white; }
        .timeline-item.current .timeline-dot { background: var(--primary-color); box-shadow: 0 0 0 4px rgba(108, 92, 231, 0.2); }
        .timeline-content { font-size: 0.9rem; }
        .timeline-date { color: #999; font-size: 0.8rem; }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 15px 0; }
        @media (max-width: 600px) { .info-grid { grid-template-columns: 1fr; } }
        .info-item { background: #f8f9fa; padding: 12px; border-radius: 8px; }
        .info-item label { font-size: 0.8rem; color: #888; display: block; margin-bottom: 4px; }
        .info-item span { font-weight: 600; }

        .oxxo-ref { background: #fff7e6; border: 2px solid #ffc107; padding: 20px; border-radius: 10px; text-align: center; margin: 15px 0; }
        .oxxo-ref h3 { margin: 0 0 10px 0; }
        .ref-code { font-size: 1.5rem; font-weight: 700; letter-spacing: 3px; color: #333; font-family: monospace; }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="logo">FashionHub</div>
            <div class="nav-links">
                <a href="index.php">Inicio</a>
                <a href="cart.php">🛒 Carrito (<?php echo $cartCount; ?>)</a>
                <a href="perfil.php">Mi Perfil</a>
                <a href="logout.php">Cerrar Sesión</a>
            </div>
        </nav>
    </header>

    <div class="ticket-container">
        <div class="ticket-card">
            <div class="ticket-header">
                <div>
                    <h1 style="margin:0;">Pedido #<?php echo $ticket_id; ?></h1>
                    <span style="color:#888; font-size:0.9rem;"><?php echo $ticket['created_at']; ?></span>
                </div>
                <span class="status-badge" style="background: <?php echo $currentStatus['color']; ?>20; color: <?php echo $currentStatus['color']; ?>;">
                    <?php echo $currentStatus['icon']; ?> <?php echo $currentStatus['label']; ?>
                </span>
            </div>

            <!-- OXXO Reference -->
            <?php if (($ticket['payment_method'] ?? '') === 'oxxo' && in_array($ticket['status'], ['pending_payment', 'pending'])): ?>
                <div class="oxxo-ref">
                    <h3>🏪 Referencia de Pago OXXO</h3>
                    <?php
                    $conektaResp = $ticket['conekta_order_id'] ?? '';
                    ?>
                    <p class="ref-code"><?php echo htmlspecialchars($conektaResp); ?></p>
                    <p style="color:#856404; margin-top:10px;">Presenta esta referencia en tu OXXO más cercano.<br>
                    <strong>Expira:</strong> <?php echo $ticket['stock_reserved_until'] ?? '72 horas desde la compra'; ?></p>
                    <small style="color:#999;">El pago se confirma en 1-24 horas después de pagar en tienda.</small>
                </div>
            <?php endif; ?>

            <!-- Order Info -->
            <div class="info-grid">
                <div class="info-item">
                    <label>Método de Pago</label>
                    <span><?php echo ($ticket['payment_method'] ?? 'card') === 'oxxo' ? '🏪 OXXO' : '💳 Tarjeta'; ?></span>
                </div>
                <div class="info-item">
                    <label>Total</label>
                    <span style="color:var(--primary-color); font-size:1.1rem;">$<?php echo number_format($ticket['total'], 2); ?></span>
                </div>
                <div class="info-item">
                    <label>Conekta ID</label>
                    <span style="font-family:monospace; font-size:0.85rem;"><?php echo htmlspecialchars($ticket['conekta_order_id'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <label>Conekta Status</label>
                    <span><?php echo htmlspecialchars($ticket['conekta_status'] ?? 'N/A'); ?></span>
                </div>
                <?php if ($ticket['shipping_address'] ?? ''): ?>
                    <div class="info-item" style="grid-column:1/-1;">
                        <label>📍 Dirección de Envío</label>
                        <span><?php echo htmlspecialchars($ticket['shipping_address']); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Items -->
            <h3 style="margin-top:20px;">Productos</h3>
            <div class="ticket-items">
                <?php foreach ($items as $item): ?>
                    <div class="ticket-item">
                        <?php if ($item['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="">
                        <?php else: ?>
                            <div class="placeholder-img">📦</div>
                        <?php endif; ?>
                        <div style="flex:1;">
                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                            <div style="color:#888; font-size:0.9rem;">Cantidad: <?php echo $item['quantity']; ?> × $<?php echo number_format($item['price'], 2); ?></div>
                        </div>
                        <div style="font-weight:600; font-size:1.05rem;">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="display:flex; justify-content:flex-end; padding:15px 0;">
                <div style="text-align:right;">
                    <div style="color:#888;">Subtotal: $<?php echo number_format($ticket['total'], 2); ?></div>
                    <div style="color:#888;">Envío: Gratis</div>
                    <div style="font-size:1.2rem; font-weight:700; color:var(--primary-color); margin-top:5px;">Total: $<?php echo number_format($ticket['total'], 2); ?></div>
                </div>
            </div>
        </div>

        <!-- Status Timeline -->
        <?php if (!empty($statusLog)): ?>
            <div class="ticket-card">
                <h3>📜 Historial del Pedido</h3>
                <div class="timeline">
                    <?php foreach (array_reverse($statusLog) as $i => $log): ?>
                        <div class="timeline-item <?php echo $i === 0 ? 'current' : ''; ?>">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <strong><?php echo htmlspecialchars($statusLabels[$log['new_status']]['label'] ?? $log['new_status']); ?></strong>
                                <?php if ($log['old_status']): ?>
                                    <span style="color:#999;"> ← <?php echo htmlspecialchars($statusLabels[$log['old_status']]['label'] ?? $log['old_status']); ?></span>
                                <?php endif; ?>
                                <?php if ($log['note']): ?>
                                    <div style="color:#666; font-size:0.85rem; margin-top:2px;"><?php echo htmlspecialchars($log['note']); ?></div>
                                <?php endif; ?>
                                <div class="timeline-date"><?php echo $log['created_at']; ?> · por <?php echo htmlspecialchars($log['changed_by']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div style="text-align:center; margin-top:20px;">
            <a href="perfil.php" class="btn btn-secondary">← Volver a Mi Perfil</a>
            <a href="index.php" class="btn">Seguir Comprando</a>
        </div>
    </div>

    <!-- Auto-refresh for pending orders -->
    <?php if (in_array($ticket['status'], ['pending_payment', 'pending'])): ?>
        <script>
            // Refresh page every 30 seconds for pending OXXO payments
            setTimeout(() => location.reload(), 30000);
        </script>
    <?php endif; ?>
</body>
</html>

<?php
// export_csv.php - Exportar ventas en CSV
include 'db.php';
session_start();

// Admin/Operator check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'operator'])) {
    header("Location: login.php");
    exit();
}

// Filters
$dateFrom = $_GET['from'] ?? '';
$dateTo = $_GET['to'] ?? '';
$status = $_GET['status'] ?? '';

$query = "SELECT t.id, u.username, u.email, t.total, t.status, t.conekta_status, t.payment_method, t.shipping_address, t.created_at 
          FROM tickets t 
          JOIN users u ON t.user_id = u.id 
          WHERE 1=1";
$params = [];

if ($dateFrom) { $query .= " AND t.created_at >= ?"; $params[] = $dateFrom . ' 00:00:00'; }
if ($dateTo) { $query .= " AND t.created_at <= ?"; $params[] = $dateTo . ' 23:59:59'; }
if ($status) { $query .= " AND t.status = ?"; $params[] = $status; }

$query .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If download requested
if (isset($_GET['download'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ventas_fashionhub_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // BOM for Excel UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, ['ID Orden', 'Usuario', 'Email', 'Total', 'Estado', 'Conekta Status', 'Método Pago', 'Dirección Envío', 'Fecha', 'Productos']);

    foreach ($tickets as $t) {
        // Get items for this ticket
        $itemStmt = $pdo->prepare("SELECT p.name, ti.quantity, ti.price FROM ticket_items ti JOIN products p ON ti.product_id = p.id WHERE ti.ticket_id = ?");
        $itemStmt->execute([$t['id']]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        $productsList = implode(' | ', array_map(function($i) {
            return $i['name'] . ' x' . $i['quantity'] . ' ($' . number_format($i['price'], 2) . ')';
        }, $items));

        fputcsv($output, [
            $t['id'],
            $t['username'],
            $t['email'],
            '$' . number_format($t['total'], 2),
            $t['status'],
            $t['conekta_status'],
            $t['payment_method'] ?? 'card',
            $t['shipping_address'] ?? '',
            $t['created_at'],
            $productsList
        ]);
    }

    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportar Ventas - FashionHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .export-container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
        .export-card { background: white; padding: 30px; border-radius: 10px; box-shadow: var(--card-shadow); }
        .filter-row { display: flex; gap: 15px; align-items: end; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-row .form-group { margin-bottom: 0; }
        .summary { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="logo">FashionHub</div>
            <div class="nav-links">
                <a href="admin.php">← Panel Admin</a>
            </div>
        </nav>
    </header>

    <div class="export-container">
        <div class="export-card">
            <h2>📊 Exportar Ventas a CSV</h2>

            <form method="GET" class="filter-row">
                <div class="form-group">
                    <label>Desde</label>
                    <input type="date" name="from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                </div>
                <div class="form-group">
                    <label>Hasta</label>
                    <input type="date" name="to" value="<?php echo htmlspecialchars($dateTo); ?>">
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="status">
                        <option value="">Todos</option>
                        <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Pagado</option>
                        <option value="pending_payment" <?php echo $status === 'pending_payment' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>Procesando</option>
                        <option value="shipped" <?php echo $status === 'shipped' ? 'selected' : ''; ?>>Enviado</option>
                        <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>Entregado</option>
                        <option value="canceled" <?php echo $status === 'canceled' ? 'selected' : ''; ?>>Cancelado</option>
                        <option value="refunded" <?php echo $status === 'refunded' ? 'selected' : ''; ?>>Reembolsado</option>
                    </select>
                </div>
                <button class="btn btn-secondary">Filtrar</button>
                <button class="btn" name="download" value="1">⬇ Descargar CSV</button>
            </form>

            <div class="summary">
                <strong>Resultados:</strong> <?php echo count($tickets); ?> órdenes encontradas
                <?php
                $totalSum = array_sum(array_column($tickets, 'total'));
                ?>
                &nbsp;|&nbsp; <strong>Total:</strong> $<?php echo number_format($totalSum, 2); ?>
            </div>

            <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
                <thead>
                    <tr style="background:#f8f9fa;">
                        <th style="padding:8px; text-align:left;">ID</th>
                        <th style="padding:8px; text-align:left;">Usuario</th>
                        <th style="padding:8px; text-align:left;">Total</th>
                        <th style="padding:8px; text-align:left;">Estado</th>
                        <th style="padding:8px; text-align:left;">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:8px;">#<?php echo $t['id']; ?></td>
                        <td style="padding:8px;"><?php echo htmlspecialchars($t['username']); ?></td>
                        <td style="padding:8px;">$<?php echo number_format($t['total'], 2); ?></td>
                        <td style="padding:8px;"><?php echo htmlspecialchars($t['status']); ?></td>
                        <td style="padding:8px;"><?php echo $t['created_at']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

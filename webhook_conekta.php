<?php
// webhook_conekta.php - Endpoint para recibir notificaciones de Conekta
// Conekta enviará POSTs a esta URL cuando el estatus de un pago cambie

// No session needed - this is called by Conekta's servers
include 'db.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

// Read the raw JSON payload from Conekta
$payload = file_get_contents('php://input');
$event = json_decode($payload, true);

// Log webhook for debugging
$logFile = __DIR__ . '/webhook_log.txt';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $payload . "\n", FILE_APPEND);

// Validate payload
if (!$event || !isset($event['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit();
}

// Process payment-related events from Conekta
$eventType = $event['type'];
$validEvents = [
    'order.paid',
    'order.pending_payment',
    'order.expired',
    'order.canceled',
    'order.refunded',
    'charge.paid',
    'charge.refunded',
    'charge.created',
    'charge.pending_confirmation'
];

if (!in_array($eventType, $validEvents)) {
    http_response_code(200);
    echo json_encode(['message' => 'Event type not handled', 'type' => $eventType]);
    exit();
}

// Extract order data from the event
$data = $event['data'] ?? null;
$object = $data['object'] ?? null;

if (!$object) {
    http_response_code(400);
    echo json_encode(['error' => 'No data object in event']);
    exit();
}

// Get the Conekta order ID and payment status
$conektaOrderId = $object['id'] ?? null;
$paymentStatus = $object['payment_status'] ?? null;

// For charge events, the order_id is nested differently
if (strpos($eventType, 'charge.') === 0) {
    $conektaOrderId = $object['order_id'] ?? $conektaOrderId;
    $chargeStatus = $object['status'] ?? null;
    if ($chargeStatus === 'paid') $paymentStatus = 'paid';
    elseif ($chargeStatus === 'pending_payment') $paymentStatus = 'pending_payment';
    elseif ($chargeStatus === 'refunded') $paymentStatus = 'refunded';
}

if (!$conektaOrderId) {
    http_response_code(400);
    echo json_encode(['error' => 'No order ID found']);
    exit();
}

// Map Conekta payment_status to our internal status
$statusMapping = [
    'paid' => 'paid',
    'pending_payment' => 'pending_payment',
    'declined' => 'canceled',
    'expired' => 'canceled',
    'refunded' => 'refunded',
    'canceled' => 'canceled',
    'voided' => 'canceled',
    'partially_refunded' => 'paid'
];

$internalStatus = $statusMapping[$paymentStatus] ?? 'pending_payment';

// Update the ticket in the database
try {
    $pdo->beginTransaction();

    // Get the current ticket
    $stmt = $pdo->prepare("SELECT id, status, conekta_status FROM tickets WHERE conekta_order_id = ?");
    $stmt->execute([$conektaOrderId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        $pdo->rollBack();
        http_response_code(200); // Return 200 so Conekta doesn't retry
        echo json_encode(['message' => 'Order not found in database', 'conekta_order_id' => $conektaOrderId]);
        exit();
    }

    $oldStatus = $ticket['status'];
    $oldConektaStatus = $ticket['conekta_status'];

    // Only update if status actually changed
    if ($paymentStatus !== $oldConektaStatus || $internalStatus !== $oldStatus) {

        // Update ticket
        $stmt = $pdo->prepare("UPDATE tickets SET conekta_status = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE conekta_order_id = ?");
        $stmt->execute([$paymentStatus, $internalStatus, $conektaOrderId]);

        // Log status change
        $stmt = $pdo->prepare("INSERT INTO order_status_log (ticket_id, old_status, new_status, changed_by, note) VALUES (?, ?, ?, 'webhook', ?)");
        $stmt->execute([
            $ticket['id'],
            $oldStatus,
            $internalStatus,
            "Conekta event: $eventType | payment_status: $paymentStatus"
        ]);

        // Handle stock based on status transition
        if ($internalStatus === 'canceled' && in_array($oldStatus, ['pending_payment', 'pending'])) {
            // OXXO expired/canceled/declined: Restore reserved stock
            $itemsStmt = $pdo->prepare("SELECT product_id, quantity FROM ticket_items WHERE ticket_id = ?");
            $itemsStmt->execute([$ticket['id']]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                $restoreStmt = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                $restoreStmt->execute([$item['quantity'], $item['product_id']]);
            }

            // Clear reservation
            $stmt = $pdo->prepare("UPDATE tickets SET stock_reserved_until = NULL WHERE id = ?");
            $stmt->execute([$ticket['id']]);

            // Log stock restoration
            $stmt = $pdo->prepare("INSERT INTO order_status_log (ticket_id, old_status, new_status, changed_by, note) VALUES (?, ?, ?, 'webhook', 'Stock restaurado por pago cancelado/expirado')");
            $stmt->execute([$ticket['id'], $internalStatus, $internalStatus]);

        } elseif ($internalStatus === 'paid' && in_array($oldStatus, ['pending_payment', 'pending'])) {
            // OXXO paid: Stock was already reserved (deducted), just clear reservation timer
            $stmt = $pdo->prepare("UPDATE tickets SET stock_reserved_until = NULL WHERE id = ?");
            $stmt->execute([$ticket['id']]);
        } elseif ($internalStatus === 'refunded' && $oldStatus === 'paid') {
            // Refund: Restore stock
            $itemsStmt = $pdo->prepare("SELECT product_id, quantity FROM ticket_items WHERE ticket_id = ?");
            $itemsStmt->execute([$ticket['id']]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                $restoreStmt = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                $restoreStmt->execute([$item['quantity'], $item['product_id']]);
            }
        }
    }

    $pdo->commit();

    http_response_code(200);
    echo json_encode([
        'message' => 'Status updated',
        'conekta_order_id' => $conektaOrderId,
        'conekta_status' => $paymentStatus,
        'internal_status' => $internalStatus,
        'old_status' => $oldStatus
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>

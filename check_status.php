<?php
// check_status.php - API para consultar el estado de un pedido (polling)
include 'db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'No autenticado']);
    exit();
}

$ticket_id = intval($_GET['id'] ?? 0);
if ($ticket_id <= 0) {
    echo json_encode(['error' => 'ID inválido']);
    exit();
}

// Only owner or admin can check
$stmt = $pdo->prepare("SELECT t.status, t.conekta_status, t.payment_method FROM tickets t WHERE t.id = ? AND (t.user_id = ? OR ? IN ('admin', 'operator'))");
$stmt->execute([$ticket_id, $_SESSION['user_id'], $_SESSION['role'] ?? 'user']);
$ticket = $stmt->fetch();

if (!$ticket) {
    echo json_encode(['error' => 'Pedido no encontrado']);
    exit();
}

echo json_encode([
    'status' => $ticket['status'],
    'conekta_status' => $ticket['conekta_status'],
    'payment_method' => $ticket['payment_method'] ?? 'card'
]);
?>

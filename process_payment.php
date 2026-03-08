<?php
// process_payment.php
include 'db.php';
include 'config_conekta.php';

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();

include 'csrf.php';

// Conekta API call
function processConektaPayment($apiKey, $paymentMethod, $amount, $customerName, $customerEmail, $token = null, $currency = "MXN", $description = "FashionHub Order") {
    $url = 'https://api.conekta.io/orders';

    $data = [
        "currency" => $currency,
        "customer_info" => [
            "name" => $customerName,
            "email" => $customerEmail,
            "phone" => "+5215555555555"
        ],
        "line_items" => [
            [
                "name" => $description,
                "unit_price" => intval(round($amount * 100)),
                "quantity" => 1
            ]
        ],
        "charges" => [
            [
                "payment_method" => (
                    $paymentMethod === 'oxxo_cash'
                        ? ["type" => "oxxo_cash"]
                        : ["type" => "card", "token_id" => $token]
                )
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/vnd.conekta-v2.0.0+json',
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($apiKey . ':')
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'response' => json_decode($response, true)];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    csrf_validate();

    if (!isset($_SESSION['user_id']) || empty($_SESSION['cart'])) {
        header("Location: cart.php");
        exit();
    }

    $payment_method = $_POST['payment_method'] ?? 'card';
    $payment_method = ($payment_method === 'oxxo') ? 'oxxo_cash' : 'card';
    $shipping_address = trim($_POST['shipping_address'] ?? '');

    $token = $_POST['conektaTokenId'] ?? null;
    $apiKey = $CONEKTA_PRIVATE_KEY;

    if ($payment_method === 'card' && empty($token)) {
        header("Location: checkout.php?err=token");
        exit();
    }

    // Recalculate Total from DB (never trust client-side price)
    $total_price = 0;
    $cart_items = [];
    $cart_ids = array_keys($_SESSION['cart']);

    if (!empty($cart_ids)) {
        $placeholders = implode(',', array_fill(0, count($cart_ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
        $stmt->execute(array_values($cart_ids));
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $qty = intval($_SESSION['cart'][$row['id']]);
            if ($qty <= 0) continue;
            $total_price += $row['price'] * $qty;
            $cart_items[] = ['product' => $row, 'qty' => $qty];
        }
    }

    if ($total_price <= 0 || empty($cart_items)) {
        header("Location: cart.php");
        exit();
    }

    // Verify stock BEFORE calling Conekta
    foreach ($cart_items as $item) {
        if ($item['product']['stock'] < $item['qty']) {
            header("Location: cart.php?err=stock&product=" . urlencode($item['product']['name']));
            exit();
        }
    }

    // Fetch user info for Conekta
    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $customerName = $user['username'] ?? 'Cliente';
    $customerEmail = $user['email'] ?? 'user@example.com';

    // Process Payment with Conekta
    $result = processConektaPayment($apiKey, $payment_method, $total_price, $customerName, $customerEmail, $token);

    // Extract Conekta order ID and status from response
    $conektaOrderId = $result['response']['id'] ?? null;
    $conektaStatus = $result['response']['payment_status'] ?? 'unknown';

    if ($result['code'] == 200 && in_array($conektaStatus, ['paid', 'pending_payment', 'pending'])) {
        // Payment Successful -> Create Order in DB
        try {
            $pdo->beginTransaction();

            // Determine internal status and whether to deduct stock NOW
            $isOxxo = ($payment_method === 'oxxo_cash');
            $internalStatus = ($conektaStatus === 'paid') ? 'paid' : 'pending_payment';

            // Calculate stock reservation expiration for OXXO (72 hours)
            $stockReservedUntil = null;
            if ($isOxxo && $internalStatus === 'pending_payment') {
                $stockReservedUntil = date('Y-m-d H:i:s', strtotime('+72 hours'));
            }

            $stmt = $pdo->prepare("INSERT INTO tickets (user_id, total, status, conekta_order_id, conekta_status, payment_method, shipping_address, stock_reserved_until) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'],
                $total_price,
                $internalStatus,
                $conektaOrderId,
                $conektaStatus,
                $isOxxo ? 'oxxo' : 'card',
                $shipping_address ?: ($user['address'] ?? ''),
                $stockReservedUntil
            ]);
            $ticket_id = $pdo->lastInsertId();

            foreach ($cart_items as $item) {
                $stmt = $pdo->prepare("INSERT INTO ticket_items (ticket_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$ticket_id, $item['product']['id'], $item['qty'], $item['product']['price']]);

                if ($internalStatus === 'paid') {
                    // Card payment: deduct stock immediately
                    $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
                    $stmt->execute([$item['qty'], $item['product']['id'], $item['qty']]);
                } else {
                    // OXXO: reserve stock (deduct temporarily, will be restored if expired/canceled)
                    $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
                    $stmt->execute([$item['qty'], $item['product']['id'], $item['qty']]);
                }
            }

            // Log initial order status
            $stmt = $pdo->prepare("INSERT INTO order_status_log (ticket_id, old_status, new_status, changed_by, note) VALUES (?, NULL, ?, 'system', ?)");
            $stmt->execute([$ticket_id, $internalStatus, 'Orden creada - ' . ($isOxxo ? 'OXXO pendiente' : 'Tarjeta pagada')]);

            $pdo->commit();
            $_SESSION['cart'] = [];
            header("Location: ticket.php?id=" . $ticket_id);
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            // Log error but don't expose internals
            error_log("Order save failed: " . $e->getMessage());
            header("Location: checkout.php?err=save");
            exit();
        }
    } else {
        // Payment Failed -> Create Rejected Order Record
        try {
            $stmt = $pdo->prepare("INSERT INTO tickets (user_id, total, status, conekta_order_id, conekta_status, payment_method, shipping_address) VALUES (?, ?, 'canceled', ?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'],
                $total_price,
                $conektaOrderId,
                $conektaStatus,
                ($payment_method === 'oxxo_cash') ? 'oxxo' : 'card',
                $shipping_address
            ]);
            $ticket_id = $pdo->lastInsertId();

            foreach ($cart_items as $item) {
                $stmt = $pdo->prepare("INSERT INTO ticket_items (ticket_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$ticket_id, $item['product']['id'], $item['qty'], $item['product']['price']]);
            }

            // Log rejected status
            $stmt = $pdo->prepare("INSERT INTO order_status_log (ticket_id, old_status, new_status, changed_by, note) VALUES (?, NULL, 'canceled', 'system', 'Pago rechazado por Conekta')");
            $stmt->execute([$ticket_id]);

            header("Location: ticket.php?id=" . $ticket_id);
            exit();

        } catch (Exception $e) {
            error_log("Failed order record save: " . $e->getMessage());
            header("Location: checkout.php?err=payment");
            exit();
        }
    }
} else {
    header("Location: cart.php");
}
?>

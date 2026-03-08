<?php
// checkout.php
include 'db.php';
include 'config_conekta.php';

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();

include 'csrf.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit();
}

// Calculate Total using prepared statements (fix SQL injection)
$total_price = 0;
$cart_items = [];
$cart_ids = array_keys($_SESSION['cart']);

if (!empty($cart_ids)) {
    $placeholders = implode(',', array_fill(0, count($cart_ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->execute(array_values($cart_ids));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $qty = $_SESSION['cart'][$row['id']];
        $total_price += $row['price'] * $qty;
        $cart_items[] = ['product' => $row, 'qty' => $qty];
    }
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagar - FashionHub</title>
    <link rel="stylesheet" href="style.css">
    <script type="text/javascript" src="https://cdn.conekta.io/js/latest/conekta.js"></script>
    <style>
        .checkout-container { display: flex; gap: 2rem; max-width: 1100px; margin: 2rem auto; padding: 0 1rem; flex-wrap: wrap; }
        .checkout-form { flex: 2; min-width: 320px; background: white; padding: 2rem; border-radius: 10px; box-shadow: var(--card-shadow); }
        .order-summary { flex: 1; min-width: 280px; background: white; padding: 2rem; border-radius: 10px; box-shadow: var(--card-shadow); height: fit-content; }
        .error-message { color: red; margin-bottom: 1rem; display: none; }
        .summary-item { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .summary-item:last-child { border-bottom: none; }
        .summary-img { width: 45px; height: 45px; object-fit: cover; border-radius: 6px; }
        .summary-name { flex: 1; font-size: 0.9rem; }
        .summary-qty { color: #666; font-size: 0.85rem; }
        .summary-price { font-weight: 600; font-size: 0.9rem; }
        .shipping-section { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 1rem; }
        .shipping-section h4 { margin: 0 0 10px 0; color: #333; }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="logo">FashionHub</div>
            <div class="nav-links">
                <a href="cart.php">Volver al Carrito</a>
                <a href="index.php">Inicio</a>
            </div>
        </nav>
    </header>

    <div class="checkout-container">
        <div class="checkout-form">
            <h2>Detalles del Pago</h2>

            <!-- Dirección de envío -->
            <div class="shipping-section">
                <h4>📦 Dirección de Envío</h4>
                <div class="form-group" style="margin-bottom:0;">
                    <input type="text" id="shipping_address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" placeholder="Tu dirección de envío completa" required style="width:100%;">
                </div>
                <small style="color:#666;">Tiempo estimado de envío: 3-7 días hábiles</small>
            </div>

            <div class="form-group" style="margin: 1rem 0;">
                <label style="font-weight:600;">Método de pago</label>
                <div style="display:flex; gap:1rem; margin-top:.5rem; flex-wrap:wrap;">
                    <label style="display:flex; gap:.5rem; align-items:center; cursor:pointer;">
                        <input type="radio" name="pm_radio" value="card" checked>
                        💳 Tarjeta (Conekta)
                    </label>
                    <label style="display:flex; gap:.5rem; align-items:center; cursor:pointer;">
                        <input type="radio" name="pm_radio" value="oxxo">
                        🏪 OXXO (Pago en efectivo)
                    </label>
                </div>
                <small style="display:block; margin-top:.5rem; color:#666;">
                    Si eliges OXXO, se generará una referencia y el pedido quedará en <b>pendiente</b> hasta que pagues.
                </small>
            </div>

            <div id="oxxo-info" style="display:none; background:#fff7e6; padding:1rem; border-radius:10px; margin-bottom:1rem; border:1px solid #ffe0a3;">
                <b>🏪 Pago en OXXO:</b> al confirmar, te mostraremos tu referencia para pagar en tienda. Tu pedido se mantendrá reservado por 72 horas.
            </div>

            <div id="card-errors" class="error-message"></div>
            <form action="process_payment.php" method="POST" id="payment-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="payment_method" id="payment_method" value="card">
                <input type="hidden" name="shipping_address" id="shipping_address_hidden" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                <input type="hidden" name="conektaTokenId" id="conektaTokenId">

                <div class="form-group">
                    <label>Nombre del Titular</label>
                    <input type="text" data-conekta="card[name]" placeholder="Juan Pérez" required>
                </div>
                <div id="card-fields">
                    <div class="form-group">
                        <label>Número de Tarjeta</label>
                        <input type="text" data-conekta="card[number]" placeholder="0000 0000 0000 0000" required>
                    </div>
                    <div style="display: flex; gap: 1rem;">
                        <div class="form-group" style="flex: 1;">
                            <label>Mes (MM)</label>
                            <input type="text" data-conekta="card[exp_month]" placeholder="MM" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Año (AAAA)</label>
                            <input type="text" data-conekta="card[exp_year]" placeholder="AAAA" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>CVC</label>
                        <input type="text" data-conekta="card[cvc]" placeholder="123" required>
                    </div>
                </div>

                <button type="submit" class="btn" style="width: 100%; font-size: 1.1rem; padding: 12px;">
                    Pagar $<?php echo number_format($total_price, 2); ?>
                </button>
            </form>
        </div>

        <div class="order-summary">
            <h3>🛒 Resumen del Pedido</h3>
            <div style="margin: 15px 0;">
                <?php foreach ($cart_items as $ci): ?>
                    <div class="summary-item">
                        <?php if ($ci['product']['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($ci['product']['image_url']); ?>" class="summary-img" alt="">
                        <?php else: ?>
                            <div class="summary-img" style="background:#eee;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">📦</div>
                        <?php endif; ?>
                        <span class="summary-name"><?php echo htmlspecialchars($ci['product']['name']); ?></span>
                        <span class="summary-qty">x<?php echo $ci['qty']; ?></span>
                        <span class="summary-price">$<?php echo number_format($ci['product']['price'] * $ci['qty'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <hr style="margin: 1rem 0; border: 0; border-top: 1px solid #eee;">
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span>Subtotal:</span>
                <span>$<?php echo number_format($total_price, 2); ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:8px; color:#666;">
                <span>Envío:</span>
                <span>Gratis</span>
            </div>
            <hr style="margin: 0.5rem 0; border: 0; border-top: 1px solid #eee;">
            <div style="display:flex; justify-content:space-between; font-weight:bold; font-size:1.1rem;">
                <span>Total:</span>
                <span style="color: var(--primary-color);">$<?php echo number_format($total_price, 2); ?></span>
            </div>
            <hr style="margin: 1rem 0; border: 0; border-top: 1px solid #eee;">
            <p><strong>📍 Enviar a:</strong></p>
            <p style="color:#666;"><?php echo htmlspecialchars($user['address'] ?? 'Sin dirección'); ?></p>
            <small style="color:#888;">Envío estimado: 3-7 días hábiles</small>
        </div>
    </div>

    <script type="text/javascript">
    Conekta.setPublicKey('<?php echo $CONEKTA_PUBLIC_KEY; ?>');

    const form = document.getElementById('payment-form');
    const tokenInput = document.getElementById('conektaTokenId');
    const pmHidden = document.getElementById('payment_method');
    const cardFields = document.getElementById('card-fields');
    const oxxoInfo = document.getElementById('oxxo-info');
    const errorDiv = document.getElementById('card-errors');
    const shippingInput = document.getElementById('shipping_address');
    const shippingHidden = document.getElementById('shipping_address_hidden');

    // Sync shipping address to hidden field
    if (shippingInput) {
        shippingInput.addEventListener('input', () => {
            shippingHidden.value = shippingInput.value;
        });
    }

    function setPaymentMethod(method) {
        pmHidden.value = method;
        if (method === 'card') {
            cardFields.style.display = '';
            oxxoInfo.style.display = 'none';
            // Re-enable required on card fields
            cardFields.querySelectorAll('input').forEach(i => i.required = true);
        } else {
            cardFields.style.display = 'none';
            oxxoInfo.style.display = '';
            if (tokenInput) tokenInput.value = '';
            if (errorDiv) { errorDiv.innerText = ''; errorDiv.style.display = 'none'; }
            // Disable required on card fields for OXXO
            cardFields.querySelectorAll('input').forEach(i => i.required = false);
        }
    }

    document.querySelectorAll('input[name="pm_radio"]').forEach(r => {
        r.addEventListener('change', () => setPaymentMethod(r.value));
    });

    setPaymentMethod('card');

    const conektaSuccessResponseHandler = function(token) {
        if (tokenInput) tokenInput.value = token.id;
        form.submit();
    };

    const conektaErrorResponseHandler = function(response) {
        if (!errorDiv) return;
        errorDiv.innerText = response.message_to_purchaser || 'Error al validar la tarjeta.';
        errorDiv.style.display = 'block';
    };

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        // Validate shipping address
        if (!shippingInput.value.trim()) {
            alert('Por favor ingresa tu dirección de envío.');
            shippingInput.focus();
            return;
        }
        shippingHidden.value = shippingInput.value.trim();

        const method = pmHidden.value || 'card';
        if (method === 'oxxo') {
            form.submit();
            return;
        }
        Conekta.Token.create(form, conektaSuccessResponseHandler, conektaErrorResponseHandler);
    });
    </script>
</body>
</html>

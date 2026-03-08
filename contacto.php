<?php
// contacto.php - Página de contacto y soporte
include 'db.php';
session_start();
include 'csrf.php';

$cartCount = array_sum($_SESSION['cart'] ?? []);
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    // In production, this would send an email or save to a support tickets table
    $sent = true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto - FashionHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .contact-container { max-width: 900px; margin: 30px auto; padding: 0 20px; }
        .contact-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        @media (max-width: 768px) { .contact-grid { grid-template-columns: 1fr; } }
        .contact-card { background: white; padding: 35px; border-radius: 15px; box-shadow: var(--card-shadow); }
        .info-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 35px; border-radius: 15px; box-shadow: var(--card-shadow); }
        .info-item { display: flex; gap: 15px; align-items: flex-start; margin-bottom: 25px; }
        .info-icon { font-size: 1.5rem; width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .info-text h4 { margin: 0 0 4px 0; }
        .info-text p { margin: 0; opacity: 0.85; font-size: 0.9rem; }
        .success-msg { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; text-align: center; margin-bottom: 15px; }
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
                    <a href="logout.php">Cerrar Sesión</a>
                <?php else: ?>
                    <a href="login.php">Iniciar Sesión</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <div class="contact-container">
        <h1 style="text-align:center; margin-bottom:5px;">📬 Contacto y Soporte</h1>
        <p style="text-align:center; color:#888; margin-bottom:30px;">¿Tienes dudas? Estamos aquí para ayudarte</p>

        <div class="contact-grid">
            <!-- Contact Form -->
            <div class="contact-card">
                <h2 style="margin-bottom:20px;">Envíanos un Mensaje</h2>

                <?php if ($sent): ?>
                    <div class="success-msg">✅ ¡Mensaje enviado! Te responderemos lo antes posible.</div>
                <?php endif; ?>

                <form method="POST" action="contacto.php">
                    <?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="name" placeholder="Tu nombre completo" required value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="tu@email.com" required>
                    </div>
                    <div class="form-group">
                        <label>Asunto</label>
                        <select name="subject" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px; font-size:1rem;">
                            <option value="general">Consulta General</option>
                            <option value="pedido">Problema con mi Pedido</option>
                            <option value="devolucion">Devolución o Cambio</option>
                            <option value="pago">Problema con el Pago</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Mensaje</label>
                        <textarea name="message" rows="5" placeholder="Describe tu consulta..." required style="resize:vertical;"></textarea>
                    </div>
                    <button type="submit" class="btn" style="width:100%;">Enviar Mensaje</button>
                </form>
            </div>

            <!-- Contact Info -->
            <div class="info-card">
                <h2 style="margin-bottom:25px;">Información de Contacto</h2>

                <div class="info-item">
                    <div class="info-icon">📧</div>
                    <div class="info-text">
                        <h4>Email</h4>
                        <p>soporte@fashionhub.com</p>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon">📞</div>
                    <div class="info-text">
                        <h4>Teléfono</h4>
                        <p>+52 618 123 4567</p>
                        <p>Lunes a Viernes, 9:00 - 18:00</p>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon">💬</div>
                    <div class="info-text">
                        <h4>WhatsApp</h4>
                        <p>+52 618 123 4567</p>
                        <p>Respuesta en menos de 2 horas</p>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon">📍</div>
                    <div class="info-text">
                        <h4>Dirección</h4>
                        <p>Av. Principal #123, Col. Centro</p>
                        <p>Durango, Dgo. 34000</p>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon">🕐</div>
                    <div class="info-text">
                        <h4>Horario de Atención</h4>
                        <p>Lunes a Viernes: 9:00 - 18:00</p>
                        <p>Sábados: 10:00 - 14:00</p>
                    </div>
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

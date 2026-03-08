<?php
// politicas.php - Páginas de políticas: devolución, privacidad, términos
include 'db.php';
session_start();
include 'csrf.php';

$section = $_GET['s'] ?? 'devolucion';
$cartCount = array_sum($_SESSION['cart'] ?? []);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Políticas - FashionHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .policies-container { max-width: 900px; margin: 30px auto; padding: 0 20px; }
        .policies-card { background: white; padding: 40px; border-radius: 15px; box-shadow: var(--card-shadow); }
        .policy-tabs { display: flex; gap: 0; margin-bottom: 30px; border-bottom: 2px solid #eee; }
        .policy-tab { padding: 12px 20px; font-weight: 600; color: #666; text-decoration: none; border-bottom: 3px solid transparent; transition: all 0.2s; }
        .policy-tab:hover { color: var(--primary-color); }
        .policy-tab.active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
        .policy-content h2 { color: #333; margin-bottom: 15px; }
        .policy-content h3 { color: #555; margin: 20px 0 10px 0; }
        .policy-content p { color: #666; line-height: 1.8; margin-bottom: 12px; }
        .policy-content ul { color: #666; line-height: 1.8; margin: 10px 0 15px 25px; }
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

    <div class="policies-container">
        <div class="policies-card">
            <h1 style="text-align:center; margin-bottom:5px;">📋 Políticas de FashionHub</h1>
            <p style="text-align:center; color:#888; margin-bottom:20px;">Última actualización: <?php echo date('d/m/Y'); ?></p>

            <div class="policy-tabs">
                <a href="?s=devolucion" class="policy-tab <?php echo $section === 'devolucion' ? 'active' : ''; ?>">🔄 Devoluciones</a>
                <a href="?s=privacidad" class="policy-tab <?php echo $section === 'privacidad' ? 'active' : ''; ?>">🔒 Privacidad</a>
                <a href="?s=terminos" class="policy-tab <?php echo $section === 'terminos' ? 'active' : ''; ?>">📄 Términos</a>
                <a href="?s=envio" class="policy-tab <?php echo $section === 'envio' ? 'active' : ''; ?>">📦 Envío</a>
            </div>

            <div class="policy-content">
                <?php if ($section === 'devolucion'): ?>
                    <h2>Política de Devoluciones y Cambios</h2>
                    <p>En FashionHub queremos que estés completamente satisfecho con tu compra. Si no estás contento con tu pedido, puedes solicitar un cambio o devolución bajo las siguientes condiciones:</p>
                    <h3>Plazo para Devoluciones</h3>
                    <ul>
                        <li>Tienes <strong>30 días naturales</strong> a partir de la fecha de entrega para solicitar una devolución.</li>
                        <li>Los cambios de talla o color están disponibles dentro de los <strong>15 días</strong> posteriores a la entrega.</li>
                    </ul>
                    <h3>Condiciones</h3>
                    <ul>
                        <li>El producto debe estar sin usar, con etiquetas originales y en su empaque original.</li>
                        <li>No se aceptan devoluciones de ropa interior, trajes de baño o accesorios personales por razones de higiene.</li>
                        <li>Los productos en oferta o con descuento especial solo aplican para cambio, no devolución.</li>
                    </ul>
                    <h3>Proceso</h3>
                    <p>Para iniciar una devolución, contáctanos a través de nuestra <a href="contacto.php" style="color:var(--primary-color);">página de contacto</a> con tu número de pedido. Te enviaremos una guía de devolución sin costo.</p>
                    <h3>Reembolsos</h3>
                    <p>Los reembolsos se procesan dentro de 5-10 días hábiles una vez recibido el producto. El reembolso se realizará al mismo método de pago utilizado.</p>

                <?php elseif ($section === 'privacidad'): ?>
                    <h2>Política de Privacidad</h2>
                    <p>FashionHub se compromete a proteger tu información personal. Esta política explica qué información recopilamos y cómo la usamos.</p>
                    <h3>Información que Recopilamos</h3>
                    <ul>
                        <li><strong>Datos de cuenta:</strong> nombre de usuario, correo electrónico, dirección de envío.</li>
                        <li><strong>Datos de pago:</strong> procesados de forma segura a través de Conekta. No almacenamos datos de tarjeta.</li>
                        <li><strong>Historial de compras:</strong> pedidos realizados, productos adquiridos.</li>
                    </ul>
                    <h3>Uso de la Información</h3>
                    <ul>
                        <li>Procesar y entregar tus pedidos.</li>
                        <li>Comunicarnos contigo sobre el estado de tu pedido.</li>
                        <li>Mejorar nuestros productos y servicios.</li>
                        <li>Prevenir fraude y garantizar la seguridad de las transacciones.</li>
                    </ul>
                    <h3>Seguridad</h3>
                    <p>Implementamos medidas de seguridad técnicas y organizativas para proteger tus datos, incluyendo encriptación de contraseñas y conexiones seguras.</p>
                    <h3>Tus Derechos</h3>
                    <p>Puedes solicitar acceso, corrección o eliminación de tus datos personales contactándonos en cualquier momento.</p>

                <?php elseif ($section === 'terminos'): ?>
                    <h2>Términos y Condiciones</h2>
                    <p>Al usar FashionHub aceptas estos términos y condiciones. Lee cuidadosamente antes de realizar una compra.</p>
                    <h3>Uso del Sitio</h3>
                    <ul>
                        <li>Debes ser mayor de 18 años para crear una cuenta y realizar compras.</li>
                        <li>Es tu responsabilidad mantener la confidencialidad de tus credenciales de acceso.</li>
                        <li>Nos reservamos el derecho de cancelar cuentas que violen estos términos.</li>
                    </ul>
                    <h3>Precios y Pagos</h3>
                    <ul>
                        <li>Todos los precios están en Pesos Mexicanos (MXN) e incluyen IVA.</li>
                        <li>Aceptamos pagos con tarjeta de crédito/débito y en efectivo vía OXXO a través de Conekta.</li>
                        <li>Para pagos en OXXO, el pedido se reservará por 72 horas. Si no se completa el pago, el pedido se cancelará automáticamente.</li>
                    </ul>
                    <h3>Disponibilidad</h3>
                    <p>Los productos están sujetos a disponibilidad. En caso de que un producto no esté disponible, te notificaremos y procesaremos el reembolso correspondiente.</p>
                    <h3>Propiedad Intelectual</h3>
                    <p>Todo el contenido del sitio (imágenes, textos, logotipos) es propiedad de FashionHub y está protegido por las leyes de propiedad intelectual.</p>

                <?php elseif ($section === 'envio'): ?>
                    <h2>Política de Envío</h2>
                    <h3>Cobertura</h3>
                    <p>Realizamos envíos a toda la República Mexicana.</p>
                    <h3>Tiempos de Entrega</h3>
                    <ul>
                        <li><strong>Zona metropolitana:</strong> 2-4 días hábiles</li>
                        <li><strong>Resto del país:</strong> 3-7 días hábiles</li>
                        <li><strong>Zonas remotas:</strong> 5-10 días hábiles</li>
                    </ul>
                    <h3>Costo de Envío</h3>
                    <ul>
                        <li><strong>Envío gratis</strong> en compras mayores a $500 MXN.</li>
                        <li>Para compras menores, el costo se calcula al momento del checkout según tu ubicación.</li>
                    </ul>
                    <h3>Seguimiento</h3>
                    <p>Una vez enviado tu pedido, recibirás un número de seguimiento para rastrear tu paquete. Puedes consultar el estado en tu perfil, sección "Mis Pedidos".</p>
                <?php endif; ?>
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

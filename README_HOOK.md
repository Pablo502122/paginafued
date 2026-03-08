# Integración Hook (Conekta)

Este proyecto incluye dos formas de actualizar el estatus de pagos:

1) **Webhook (recomendado)**: `webhook_conekta.php` (Conekta llama a tu servidor).
2) **Hook por polling**: carpeta `hook_conekta/` (Node.js) que consulta Conekta cada 5s y actualiza la BD SQLite.

## Para OXXO
En `checkout.php` ahora puedes elegir **Tarjeta** o **OXXO**.
- Tarjeta: se tokeniza con Conekta.js y el ticket queda `paid` si el pago fue exitoso.
- OXXO: el ticket queda `pending` / `pending_payment` y luego cambia a `paid` cuando se registra el pago.


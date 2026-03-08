# Hook Conekta (Polling) - tiendaropa

Este servicio revisa cada N segundos los pedidos (tabla `tickets`) que estén en `pending/pending_payment`
y actualiza `tickets.conekta_status` y `tickets.status` consultando la API de Conekta.

## Requisitos
- Node.js 18+
- Tu tienda debe guardar `tickets.conekta_order_id` y `tickets.conekta_status` (este proyecto ya lo hace).

## Instalación
```bash
cd hook_conekta
npm install
cp .env.example .env
# edita .env (ruta al .db y tu llave privada)
npm start
```

## Notas
- Este hook es útil si no puedes exponer `webhook_conekta.php` a internet.
- Si usas webhooks oficiales, el polling puede ser innecesario.

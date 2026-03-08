require('dotenv').config();
const axios = require('axios');
const sqlite3 = require('sqlite3').verbose();
const path = require('path');

const SQLITE_PATH = process.env.SQLITE_PATH || path.join(__dirname, '..', 'tiendaropa.db');
const CONEKTA_PRIVATE_KEY = process.env.CONEKTA_PRIVATE_KEY;
const POLL_SECONDS = parseInt(process.env.POLL_SECONDS || '5', 10);

if (!CONEKTA_PRIVATE_KEY) {
  console.error('Falta CONEKTA_PRIVATE_KEY en .env');
  process.exit(1);
}

function conektaHeaders() {
  const auth = Buffer.from(CONEKTA_PRIVATE_KEY + ':').toString('base64');
  return {
    'Accept': 'application/vnd.conekta-v2.0.0+json',
    'Content-Type': 'application/json',
    'Authorization': 'Basic ' + auth,
  };
}

function openDb() {
  return new sqlite3.Database(SQLITE_PATH);
}

function all(db, sql, params=[]) {
  return new Promise((resolve, reject) => {
    db.all(sql, params, (err, rows) => err ? reject(err) : resolve(rows));
  });
}
function get(db, sql, params=[]) {
  return new Promise((resolve, reject) => {
    db.get(sql, params, (err, row) => err ? reject(err) : resolve(row));
  });
}
function run(db, sql, params=[]) {
  return new Promise((resolve, reject) => {
    db.run(sql, params, function(err) { err ? reject(err) : resolve(this); });
  });
}

function mapInternalStatus(paymentStatus) {
  const map = {
    paid: 'paid',
    pending_payment: 'pending',
    pending: 'pending',
    declined: 'rejected',
    expired: 'rejected',
    canceled: 'rejected',
    voided: 'rejected',
    refunded: 'refunded',
    partially_refunded: 'paid'
  };
  return map[paymentStatus] || 'pending';
}

async function restoreStockIfRejected(db, ticketId) {
  const items = await all(db, 'SELECT product_id, quantity FROM ticket_items WHERE ticket_id = ?', [ticketId]);
  for (const it of items) {
    await run(db, 'UPDATE products SET stock = stock + ? WHERE id = ?', [it.quantity, it.product_id]);
  }
}

async function pollOnce() {
  const db = openDb();
  try {
    const tickets = await all(db, `
      SELECT id, conekta_order_id, conekta_status, status
      FROM tickets
      WHERE conekta_order_id IS NOT NULL
        AND conekta_order_id != ''
        AND (conekta_status IS NULL OR conekta_status IN ('pending','pending_payment') OR status = 'pending')
      ORDER BY created_at DESC
      LIMIT 50
    `);

    if (!tickets.length) {
      console.log(new Date().toISOString(), 'Sin tickets pendientes.');
      db.close();
      return;
    }

    for (const t of tickets) {
      try {
        const url = `https://api.conekta.io/orders/${encodeURIComponent(t.conekta_order_id)}`;
        const resp = await axios.get(url, { headers: conektaHeaders(), timeout: 15000 });
        const paymentStatus = resp.data && resp.data.payment_status ? resp.data.payment_status : 'pending';
        const internalStatus = mapInternalStatus(paymentStatus);

        if (paymentStatus !== (t.conekta_status || 'pending') || internalStatus !== t.status) {
          await run(db, 'UPDATE tickets SET conekta_status = ?, status = ? WHERE id = ?', [paymentStatus, internalStatus, t.id]);
          console.log(new Date().toISOString(), `Ticket #${t.id} actualizado:`, t.conekta_status, '->', paymentStatus);

          if (internalStatus === 'rejected') {
            await restoreStockIfRejected(db, t.id);
            console.log(new Date().toISOString(), `Stock restaurado para ticket #${t.id}`);
          }
        }
      } catch (e) {
        console.error(new Date().toISOString(), `Error consultando order ${t.conekta_order_id}:`, e.response?.status, e.response?.data?.details?.[0]?.message || e.message);
      }
    }
  } catch (e) {
    console.error(new Date().toISOString(), 'Error polling:', e.message);
  } finally {
    db.close();
  }
}

console.log('Hook Conekta (polling) iniciado');
console.log('DB:', SQLITE_PATH);
console.log('Cada', POLL_SECONDS, 'segundos');

pollOnce();
setInterval(pollOnce, POLL_SECONDS * 1000);

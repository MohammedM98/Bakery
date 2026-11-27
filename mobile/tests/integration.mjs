// The app's sync engine against a real Laravel server: `npm run test:integration` (needs PHP and the
// Laravel app's vendor/). It migrates a fresh SQLite file with the demo seed, starts `php artisan serve`,
// and runs: login, operations recorded offline, push, pull, a deleted customer, the same client_uuid twice,
// and another bakery's customer (404).
import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { after, before, test } from 'node:test';

import { createClient } from '../src/api/client.ts';
import { applyPush, balances, EMPTY, enqueue, pushBatch, sync } from '../src/sync/engine.ts';
import * as ops from '../src/sync/operations.ts';

const ROOT = resolve(import.meta.dirname, '../..');
const PORT = 8000 + Math.floor(Math.random() * 900) + 100;
const BASE = `http://127.0.0.1:${PORT}/api/v1`;
const db = join(mkdtempSync(join(tmpdir(), 'bakery-')), 'db.sqlite');
writeFileSync(db, '');
const env = { ...process.env, APP_ENV: 'local', DB_CONNECTION: 'sqlite', DB_DATABASE: db, SESSION_DRIVER: 'array', CACHE_STORE: 'array', QUEUE_CONNECTION: 'sync' };
const now = () => new Date().toISOString();
const today = () => now().slice(0, 10);
let server;

before(async () => {
  const m = spawnSync('php', ['artisan', 'migrate:fresh', '--seed', '--force'], { cwd: ROOT, env, encoding: 'utf8' });
  assert.equal(m.status, 0, m.stderr || m.stdout);
  server = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', `--port=${PORT}`], { cwd: ROOT, env, stdio: 'ignore' });
  for (let i = 0; i < 100; i++) {
    try {
      await fetch(`${BASE}/me`, { headers: { Accept: 'application/json' } });
      return;
    } catch {
      await new Promise((r) => setTimeout(r, 100));
    }
  }
  throw new Error('the server did not start');
});
after(() => server?.kill());

async function signIn(email) {
  let token = null;
  const request = createClient(BASE, () => token);
  const me = await request('POST', 'login', { email, password: 'password', device_name: 'اختبار التكامل' });
  token = me.token;
  delete me.token;
  return { request, me };
}

/** One phone: its LocalData, sync, and operations recorded "offline" (queued, sent at the next sync). */
async function phone(email) {
  const { request, me } = await signIn(email);
  const p = { request, d: { ...EMPTY, me } };
  p.sync = () => sync(request, () => p.d, (n) => (p.d = n), now);
  p.record = (build, input) => {
    const b = build({ uuid: randomUUID(), now: now(), today: today(), by: me.user.name, customers: p.d.customers }, input, -1);
    p.d = enqueue(p.d, b.op, { movements: b.movements, customer: b.customer });
    return b.op;
  };
  return p;
}

test('offline operations reach the server in order and the phone takes its numbers and balances', async () => {
  const p = await phone('owner@bakery.test');
  assert.deepEqual(await p.sync(), { ok: true, sent: 0, rejected: 0 });
  assert.ok(p.d.customers.length > 0, 'the first pull brings the customers');
  const existing = p.d.customers[0];

  const c = p.record(ops.customer, { name: 'زبون دون إنترنت', phone: '0590000001', notes: '', openingFlour: 10 });
  p.record(ops.deposit, { customerId: -1, kg: 5, note: '' });
  p.record(ops.flourSale, { customerId: existing.id, kg: 4, paid: false, fee: p.d.me.prices.flour_exchange_fee_per_kg });
  p.record(ops.payment, { customerId: existing.id, amount: 3, method: 'cash', fromBank: null, toBank: null, reference: '', sale: null });
  p.record(ops.adjustment, { customerId: existing.id, ledger: 'money', direction: 'debit', amount: 1, reason: 'تصحيح', corrects: null });
  p.record(ops.sale, { kg: 2, paid: true, buyerName: '', price: p.d.me.prices.regular_price_per_kg });
  assert.equal(pushBatch(p.d)[1].payload.customer_client_uuid, c.client_uuid);
  assert.equal(balances(p.d).get(-1).flour, -1500);

  const out = await p.sync();
  assert.deepEqual(out, { ok: true, sent: 6, rejected: 0 }, JSON.stringify(p.d.queue.filter((q) => q.status === 'rejected').map((q) => [q.type, q.reason])));
  const created = p.d.customers.find((x) => x.name === 'زبون دون إنترنت');
  assert.ok(created.id > 0, 'the customer has its server id');
  assert.equal(created.flour_balance_text, 'له 15 كغ');
  assert.equal(balances(p.d).get(created.id).pending, false);
  assert.ok(p.d.movements.every((m) => m.number != null), 'every movement has its server number');
  assert.equal(p.d.queue.filter((q) => q.status === 'sent').length, 6);

  // The phone's copy matches the server's own customer page.
  const page = await p.request('GET', `customers/${existing.id}?from=&to=`);
  assert.equal(p.d.customers.find((x) => x.id === existing.id).money_balance, page.customer.money_balance);
});

test('the same client_uuid twice is applied once', async () => {
  const p = await phone('owner@bakery.test');
  await p.sync();
  const id = p.d.customers[0].id;
  const op = p.record(ops.deposit, { customerId: id, kg: 1, note: '' });
  const batch = pushBatch(p.d);
  const first = await p.request('POST', 'sync/push', { operations: batch });
  const second = await p.request('POST', 'sync/push', { operations: batch });
  assert.equal(first.results[0].status, 'accepted');
  assert.equal(second.results[0].status, 'duplicate');
  assert.deepEqual(second.results[0].data.movements.map((m) => m.number), first.results[0].data.movements.map((m) => m.number));
  p.d = applyPush(p.d, second.results, now());
  assert.equal(p.d.queue.find((q) => q.client_uuid === op.client_uuid).status, 'sent');
});

test('an operation for a customer deleted meanwhile is refused with its reason, and the pull removes the customer', async () => {
  const p = await phone('owner@bakery.test');
  const made = await p.request('POST', 'customers', { client_uuid: randomUUID(), name: 'سيُحذف', mobile_number: '0590000002' });
  await p.sync();
  const op = p.record(ops.deposit, { customerId: made.customer.id, kg: 2, note: '' });
  await p.request('DELETE', `customers/${made.customer.id}`);

  const out = await p.sync();
  assert.deepEqual(out, { ok: true, sent: 0, rejected: 1 });
  const q = p.d.queue.find((x) => x.client_uuid === op.client_uuid);
  assert.equal(q.status, 'rejected');
  assert.equal(q.code, 'customer_not_found');
  assert.equal(q.reason, 'الزبون غير موجود، ربما حُذف.');
  assert.equal(p.d.customers.find((x) => x.id === made.customer.id), undefined, 'the tombstone removed it');
});

test("another bakery's customer is not found", async () => {
  const admin = await signIn('admin@bakery.test');
  await admin.request('POST', 'admin/bakeries', {
    client_uuid: randomUUID(),
    name: 'مخبز آخر',
    subscription_months: 1,
    owner_name: 'صاحب آخر',
    owner_email: 'other@bakery.test',
    owner_password: 'password',
  });
  const other = await signIn('other@bakery.test');
  const theirs = await other.request('POST', 'customers', { client_uuid: randomUUID(), name: 'زبون الآخر', mobile_number: '0590000003' });

  const p = await phone('owner@bakery.test');
  await assert.rejects(p.request('GET', `customers/${theirs.customer.id}`), (e) => e.status === 404);
  p.record(ops.deposit, { customerId: theirs.customer.id, kg: 1, note: '' });
  const out = await p.sync();
  assert.equal(out.rejected, 1);
  assert.equal(p.d.queue.find((x) => x.status === 'rejected').code, 'customer_not_found');
  const mine = await other.request('GET', `customers/${theirs.customer.id}?from=&to=`);
  assert.equal(mine.customer.flour_balance, '0.00', 'nothing was written in the other bakery');
});

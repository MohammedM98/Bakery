// The offline queue and sync (src/sync), with a fake server. `npm test`; tests/integration.mjs runs the same
// engine against a real Laravel server.
import assert from 'node:assert/strict';
import { test } from 'node:test';

import { ApiError } from '../src/api/client.ts';
import { receiptHtml, fromResult } from '../src/lib/receipt.ts';
import { applyPull, applyPush, balances, dismiss, EMPTY, enqueue, historyFloor, pushBatch, retry, sync } from '../src/sync/engine.ts';
import * as ops from '../src/sync/operations.ts';

const NOW = '2026-11-27T09:00:00.000Z';
const me = (active = true) => ({
  user: { id: 2, name: 'سامي', email: 's@b.test', role: 'bakery_staff' },
  bakery: { id: 1, name: 'مخبز النور', phone: null, address: null },
  subscription: { status: 'active', active, expires_at: '2026-12-27', days_left: 30 },
  permissions: ['view-customers'],
  prices: { regular_price_per_kg: '5.00', regular_price_text: '5.00 شيكل', flour_exchange_fee_per_kg: '1.50', flour_exchange_fee_text: '1.50 شيكل' },
  server_time: NOW,
  timezone: 'UTC',
  local_timezone: 'Asia/Hebron',
});
const customer = (id, money = '0.00', flour = '0.00', name = 'أحمد') => ({
  id,
  name,
  mobile_number: '0591111111',
  notes: null,
  money_balance: money,
  money_balance_text: `${money} شيكل`,
  flour_balance: flour,
  flour_balance_text: `${flour} كغ`,
});
const movement = (number, customerId, ledger, amount, extra = {}) => ({
  number,
  customer_id: customerId,
  sale_id: null,
  ledger,
  type: 'deposit',
  type_label: '',
  date: '2026-11-27',
  amount,
  direction: Number(amount) >= 0 ? 'debit' : 'credit',
  direction_label: Number(amount) >= 0 ? 'عليه' : 'له',
  amount_text: '',
  description: '',
  note: null,
  transfer: null,
  transfer_reference: null,
  recorded_by: 'سامي',
  created_at: NOW,
  ...extra,
});
const pull = (over = {}) => ({ ...me(), cursor: '5.abcd1234.20260829', has_more: false, full: false, customers: [], movements: [], banks: [], deleted: { customers: [], banks: [] }, ...over });
const ctx = (data, uuid) => ({ uuid, now: NOW, today: '2026-11-27', by: 'سامي', customers: data.customers });

function withCustomer() {
  return { ...EMPTY, me: me(), cursor: '1.abcd1234.20260829', customers: [customer(7, '10.00', '-20.00')] };
}

test('an offline operation is queued with its key, price and expected movements', () => {
  let d = withCustomer();
  const b = ops.flourSale(ctx(d, 'u-1'), { customerId: 7, kg: 10, paid: false, fee: '1.50' });
  d = enqueue(d, b.op, { movements: b.movements });
  assert.deepEqual(pushBatch(d), [
    {
      client_uuid: 'u-1',
      type: 'flour_sale',
      client_created_at: NOW,
      payload: { customer_id: 7, kg_amount: '10.00', payment_status: 'unpaid', sale_date: '2026-11-27', price_per_kg: '1.50' },
    },
  ]);
  const bal = balances(d).get(7);
  assert.equal(bal.pending, true);
  assert.equal(bal.flour, -1000); // له 20 كغ − 10 كغ
  assert.equal(bal.money, 2500); // 10.00 + 15.00 fee
  assert.equal(bal.flourText, 'له 10 كغ');
  assert.equal(d.movements.filter((m) => m.number == null).length, 2);
});

test('accepted and duplicate results replace the estimate with the server numbers and balances', () => {
  for (const status of ['accepted', 'duplicate']) {
    let d = withCustomer();
    const b = ops.deposit(ctx(d, 'u-2'), { customerId: 7, kg: 5, note: '' });
    d = enqueue(d, b.op, { movements: b.movements });
    d = applyPush(d, [{ client_uuid: 'u-2', status, data: { movements: [movement(41, 7, 'flour', '-5.00')], customer: customer(7, '10.00', '-25.00'), sale: null } }], NOW);
    assert.deepEqual(d.movements.map((m) => m.number), [41]);
    assert.equal(d.customers[0].flour_balance, '-25.00');
    assert.equal(balances(d).get(7).pending, false);
    assert.equal(balances(d).get(7).flourText, '-25.00 كغ', 'the server text is used when nothing waits');
    assert.equal(d.queue[0].status, 'sent');
    assert.equal(pushBatch(d).length, 0);
  }
});

test('a refusal stays on the sync screen with its reason; a retryable one waits for the next sync', () => {
  let d = withCustomer();
  for (const uuid of ['a', 'b']) {
    const b = ops.payment(ctx(d, uuid), { customerId: 7, amount: 5, method: 'cash', fromBank: null, toBank: null, reference: '', sale: null });
    d = enqueue(d, b.op, { movements: b.movements });
  }
  d = applyPush(
    d,
    [
      { client_uuid: 'a', status: 'rejected', code: 'customer_not_found', message: 'الزبون غير موجود، ربما حُذف.', retryable: false },
      { client_uuid: 'b', status: 'rejected', code: 'server_error', message: 'خطأ', retryable: true },
    ],
    NOW,
  );
  assert.equal(d.queue.find((q) => q.client_uuid === 'a').status, 'rejected');
  assert.equal(d.queue.find((q) => q.client_uuid === 'a').reason, 'الزبون غير موجود، ربما حُذف.');
  assert.equal(d.queue.find((q) => q.client_uuid === 'b').status, 'pending');
  assert.equal(d.movements.length, 2, 'nothing is dropped silently');

  const v = applyPush(d, [{ client_uuid: 'b', status: 'rejected', code: 'validation_failed', message: 'x', errors: { amount: ['المبلغ أكبر من المسموح.'] }, retryable: false }], NOW);
  assert.equal(v.queue.find((q) => q.client_uuid === 'b').reason, 'المبلغ أكبر من المسموح.');

  const back = retry(d, 'a');
  assert.equal(back.queue.find((q) => q.client_uuid === 'a').status, 'pending');
  const gone = dismiss(d, 'a');
  assert.equal(gone.queue.length, 1);
  assert.equal(gone.movements.length, 1);
});

test('a customer added offline is referenced by its client_uuid and follows its server id', () => {
  let d = withCustomer();
  const c = ops.customer(ctx(d, 'c-1'), { name: 'زبون جديد', phone: '0593333333', notes: '', openingFlour: 4 }, -5);
  d = enqueue(d, c.op, { movements: c.movements, customer: c.customer });
  const s = ops.deposit(ctx(d, 's-1'), { customerId: -5, kg: 2, note: '' });
  d = enqueue(d, s.op, { movements: s.movements });
  assert.deepEqual(pushBatch(d)[0].payload, { name: 'زبون جديد', mobile_number: '0593333333', notes: null, flour_balance_kg: '4.00' });
  assert.equal(pushBatch(d)[1].payload.customer_client_uuid, 'c-1');
  assert.equal(balances(d).get(-5).flour, -600);

  d = applyPush(d, [{ client_uuid: 'c-1', status: 'accepted', data: { customer: customer(31, '0.00', '-4.00', 'زبون جديد'), movements: [movement(50, 31, 'flour', '-4.00')] } }], NOW);
  assert.equal(d.customers.find((x) => x.id === -5), undefined);
  assert.equal(balances(d).get(31).flour, -600, 'the waiting deposit moved to the server id');
  assert.equal(d.queue.find((q) => q.client_uuid === 'c-1').local_id, -5);
});

test('pull replaces customers, adds movements, applies tombstones and starts over when full', () => {
  let d = withCustomer();
  d = { ...d, customers: [...d.customers, customer(8, '0.00', '0.00', 'بلال')], movements: [movement(1, 7, 'flour', '-20.00'), movement(2, 8, 'money', '3.00')], banks: [{ id: 1, name: 'بنك' }] };
  d = applyPull(d, pull({ customers: [customer(7, '12.00', '-20.00')], movements: [movement(3, 7, 'money', '2.00')], banks: [{ id: 2, name: 'جوال باي' }], deleted: { customers: [8], banks: [1] } }), NOW);
  assert.deepEqual(d.customers.map((c) => c.id), [7]);
  assert.deepEqual(d.movements.map((m) => m.number), [1, 3]);
  assert.deepEqual(d.banks.map((b) => b.id), [2]);
  assert.equal(d.cursor, '5.abcd1234.20260829');
  assert.equal(historyFloor(d.cursor), '2026-08-29');

  const b = ops.deposit(ctx(d, 'w'), { customerId: 7, kg: 1, note: '' });
  d = enqueue(d, b.op, { movements: b.movements });
  d = applyPull(d, pull({ full: true, customers: [customer(9, '0.00', '0.00', 'جديد')] }), NOW);
  assert.deepEqual(d.customers.map((c) => c.id).sort(), [7, 9], 'a customer with a waiting operation is kept');
  assert.deepEqual(d.movements.map((m) => m.number), [null], 'only waiting movements survive a full pull');
  assert.deepEqual(d.banks, []);

  const expired = applyPull(d, { ...pull({ customers: [customer(10)] }), subscription: { status: 'active', active: false, expires_at: '2026-11-01', days_left: -26 } }, NOW);
  assert.equal(expired.customers.length, 2, 'an expired bakery gets no data');
  assert.equal(expired.me.subscription.active, false);
});

test('sync pushes in order, pulls until has_more is false and recovers from a broken cursor', async () => {
  let d = { ...withCustomer(), cursor: 'broken' };
  const b = ops.deposit(ctx(d, 'u-9'), { customerId: 7, kg: 5, note: '' });
  d = enqueue(d, b.op, { movements: b.movements });
  const calls = [];
  const request = async (method, path, body) => {
    calls.push(`${method} ${path}`);
    if (path === 'sync/push') return { results: body.operations.map((o) => ({ client_uuid: o.client_uuid, status: 'accepted', data: { movements: [movement(60, 7, 'flour', '-5.00')], customer: customer(7, '10.00', '-25.00') } })) };
    if (path === 'sync/pull?since=broken') throw new ApiError('invalid_cursor', 'x', 422);
    if (path === 'sync/pull') return pull({ cursor: '7.abcd1234.20260829', has_more: true, full: true, customers: [customer(7, '10.00', '-25.00')] });
    return pull({ cursor: '8.abcd1234.20260829', movements: [movement(61, 7, 'money', '1.00')] });
  };
  const out = await sync(request, () => d, (n) => (d = n), () => NOW);
  assert.deepEqual(out, { ok: true, sent: 1, rejected: 0 });
  assert.deepEqual(calls, ['POST sync/push', 'GET sync/pull?since=broken', 'GET sync/pull', 'GET sync/pull?since=7.abcd1234.20260829']);
  assert.deepEqual(d.movements.map((m) => m.number).sort(), [61], 'the full pull dropped the old copy');
  assert.equal(d.cursor, '8.abcd1234.20260829');

  const fail = await sync(async () => { throw new ApiError('network', 'لا اتصال'); }, () => d, (n) => (d = n), () => NOW);
  assert.equal(fail.ok, false);
  assert.equal(fail.error.code, 'network');
});

test('the receipt has what section 13 lists and escapes names', () => {
  const r = fromResult(
    {
      movements: [movement(88, 7, 'money', '15.00', { recorded_by: 'سامي', created_at: '2026-11-27T09:01:00+00:00' })],
      customer: { ...customer(7, '-50.00', '20.00', 'أحمد <ب>'), money_balance_text: '⁦-50.00⁩ شيكل', flour_balance_text: 'عليه 20 كغ' },
      sale: { buyer: 'أحمد', kg_text: '10 كغ', price_per_kg: '1.50', total_text: '15.00 شيكل', payment_status_label: 'غير مدفوع' },
    },
    'مخبز النور',
  );
  const html = receiptHtml(r, 'small');
  for (const part of ['مخبز النور', 'حركة رقم 88', '27/11/2026 11:01', 'أحمد &#60;ب&#62;', '10 كغ', '1.50 شيكل', '15.00 شيكل', '⁦-50.00⁩ شيكل', 'عليه 20 كغ', 'سجّلها سامي', '80mm']) {
    assert.ok(html.includes(part), part);
  }
  assert.ok(receiptHtml(r, 'a4').includes('size: A4'));
});

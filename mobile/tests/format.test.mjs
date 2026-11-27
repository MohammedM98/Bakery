// Runs with `npm test` (Node's built-in runner; Node strips the TypeScript types).
// The money cases are tests/Unit/MoneyFormatTest.php's: the website, PHP and the app must agree.
import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
  formatDate,
  formatDays,
  formatFlourBalance,
  formatKg,
  formatMoney,
  fromCents,
  toCents,
} from '../src/lib/format.ts';

test('amounts are written with the word shekel', () => {
  assert.equal(formatMoney(1234.5), '1,234.50 شيكل');
  assert.equal(formatMoney(null), '0.00 شيكل');
  // A negative number is isolated left to right so its minus stays on the left in Arabic text.
  assert.equal(formatMoney('-50'), '⁦-50.00⁩ شيكل');
  assert.equal(formatMoney(90, { plus: true }), '⁦+90.00⁩ شيكل');
});

test('flour balance is words, never a sign', () => {
  assert.equal(formatFlourBalance(20), 'عليه 20 كغ');
  assert.equal(formatFlourBalance('-12.5'), 'له 12.5 كغ');
  assert.equal(formatFlourBalance(0), '0 كغ');
  assert.equal(formatKg('1500'), '1,500 كغ');
});

test('dates and day counts', () => {
  assert.equal(formatDate('2026-11-26'), '26/11/2026');
  assert.equal(formatDays(1), 'يوم واحد');
  assert.equal(formatDays(2), 'يومان');
  assert.equal(formatDays(4), '4 أيام');
  assert.equal(formatDays(30), '30 يومًا');
});

test('cents round trip without floats', () => {
  assert.equal(toCents('0.1') + toCents('0.2'), 30);
  assert.equal(fromCents(toCents('72.5') * 3), '217.50');
});

import assert from 'node:assert/strict';
import { test } from 'node:test';

import { statement } from '../src/state/statement.ts';

const m = (number, date, amount) => ({ number, customer_id: 7, ledger: 'money', date, amount });

test('the opening balance of a partial copy is the balance minus the movements from the start date', () => {
  // The phone holds only the last movements; the balance (from the server) is 40.00 + 5.00 waiting.
  const rows = [m(9, '2026-11-20', '10.00'), m(null, '2026-11-27', '5.00'), m(3, '2026-11-02', '30.00'), m(12, '2026-11-25', '-20.00')];
  const st = statement(rows, 7, 'money', 4500, '2026-11-15', '2026-11-27');
  assert.equal(st.opening, 4500 - 1000 + 2000 - 500);
  assert.deepEqual(st.rows.map((r) => r.movement.number), [9, 12, null]);
  assert.equal(st.closing, 4500);
  assert.equal(st.rows[1].credit, 2000);
});

import type { ApiMovement, Ledger } from '../api/types.ts';
import { toCents } from '../lib/format.ts';

export interface StatementRow {
  movement: ApiMovement;
  debit: number; // عليه, positive hundredths
  credit: number; // له, positive hundredths
  balance: number; // running, signed hundredths
}

/**
 * Customer::statement() on the phone's copy: rows by date then number (waiting ones last), the running balance,
 * and the opening balance as the server documents it for a partial copy: the current balance minus the
 * movements from the start date on (the copy may not go back to the first movement).
 */
export function statement(movements: ApiMovement[], customerId: number, ledger: Ledger, balance: number, from: string | null, to: string | null) {
  const mine = movements
    .filter((m) => m.customer_id === customerId && m.ledger === ledger)
    .sort((a, b) => (a.date !== b.date ? (a.date < b.date ? -1 : 1) : (a.number ?? Infinity) - (b.number ?? Infinity)));
  const after = mine.filter((m) => !from || m.date >= from);
  const opening = balance - after.reduce((n, m) => n + toCents(m.amount), 0);
  let running = opening;
  const rows: StatementRow[] = after
    .filter((m) => !to || m.date <= to)
    .map((m) => {
      const a = toCents(m.amount);
      running += a;
      return { movement: m, debit: a > 0 ? a : 0, credit: a < 0 ? -a : 0, balance: running };
    });
  return { opening, rows, closing: running };
}

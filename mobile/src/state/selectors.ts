import { toCents } from '@/lib/format';
import { bounds, inRange, shift, tick, type Period } from '@/lib/period';
import type { ApiMovement, ApiSale, Ledger, MovementType } from '@/mock/types';

/**
 * The numbers the server computes (DashboardController, SalesLogController, Customer::statement),
 * done locally from the same movements and sales so the screens work offline. Session 11 can keep
 * these for the offline copy and use the API's numbers when online.
 */

export function dashboard(movements: ApiMovement[], sales: ApiSale[], period: Period, date: string) {
  const range = bounds(period, date);
  const prevRange = bounds(period, shift(period, range[0], -1));
  const inP = sales.filter((s) => inRange(s.sale_date, range));
  const kg = (xs: ApiSale[], type?: 'cash' | 'flour') =>
    xs.filter((s) => !type || s.sale_type === type).reduce((n, s) => n + toCents(s.kg_amount), 0) / 100;
  const payments = (r: [string, string]) => movements.filter((m) => m.type === 'payment' && inRange(m.movement_date, r));
  const pays = payments(range);
  const income = -pays.reduce((n, m) => n + toCents(m.amount), 0);
  const transfer = -pays.filter((m) => m.from_bank).reduce((n, m) => n + toCents(m.amount), 0);
  const prevIncome = -payments(prevRange).reduce((n, m) => n + toCents(m.amount), 0);
  const total = inP.reduce((n, s) => n + toCents(s.total_amount), 0);
  const paid = inP.reduce((n, s) => n + toCents(s.paid_amount), 0);
  const unpaidSales = inP.filter((s) => toCents(s.paid_amount) < toCents(s.total_amount));

  const series: { label: string; kg: number }[] = [];
  for (let i = 6; i >= 0; i--) {
    const start = shift(period, range[0], -i);
    const r = bounds(period, start);
    series.push({ label: tick(period, r[0]), kg: kg(sales.filter((s) => inRange(s.sale_date, r))) });
  }

  const inPeriod = movements.filter((m) => inRange(m.movement_date, range));
  const saleTimes = new Set(inPeriod.filter((m) => m.type === 'sale').map((m) => `${m.sale_id}|${m.created_at}`));
  // A payment recorded with its sale (paid at once) is shown on the sale row, not twice.
  const recent = inPeriod
    .filter((m) => m.type !== 'exchange_fee' && !(m.type === 'payment' && saleTimes.has(`${m.sale_id}|${m.created_at}`)))
    .slice(-5)
    .reverse();

  return {
    range,
    breadKg: kg(inP),
    cashKg: kg(inP, 'cash'),
    flourKg: kg(inP, 'flour'),
    saleCount: inP.length,
    income,
    transfer,
    cash: income - transfer,
    prevIncome,
    unpaid: total - paid,
    unpaidCount: unpaidSales.length,
    paidRatio: total > 0 ? Math.round((paid / total) * 100) : 100,
    series,
    recent,
  };
}

export type LogType = 'all' | 'sale' | 'flour_sale' | 'payment' | 'deposit' | 'adjustment';

export const LOG_TYPES: readonly (readonly [LogType, string])[] = [
  ['all', 'الكل'],
  ['sale', 'بيع عادي'],
  ['flour_sale', 'مقابل طحين'],
  ['payment', 'دفعات'],
  ['deposit', 'إيداع طحين'],
  ['adjustment', 'تسوية'],
];

const LOG_MATCH: Record<Exclude<LogType, 'all'>, MovementType[]> = {
  sale: ['sale'],
  flour_sale: ['flour_withdrawal', 'exchange_fee'],
  payment: ['payment'],
  deposit: ['deposit'],
  adjustment: ['adjustment', 'opening_balance'],
};

export function salesLog(
  movements: ApiMovement[],
  sales: ApiSale[],
  period: Period,
  date: string,
  filters: { type: LogType; customer: number | 'walk_in' | null; recordedBy: string | null },
) {
  const range = bounds(period, date);
  const inP = movements.filter((m) => inRange(m.movement_date, range));
  const salesP = sales.filter((s) => inRange(s.sale_date, range));
  const kg = (t?: 'cash' | 'flour') => salesP.filter((s) => !t || s.sale_type === t).reduce((n, s) => n + toCents(s.kg_amount), 0) / 100;
  const pays = inP.filter((m) => m.type === 'payment');
  const income = -pays.reduce((n, m) => n + toCents(m.amount), 0);
  const transfer = -pays.filter((m) => m.from_bank).reduce((n, m) => n + toCents(m.amount), 0);
  const received = -inP.filter((m) => m.type === 'deposit').reduce((n, m) => n + toCents(m.amount), 0) / 100;
  const spent = inP.filter((m) => m.type === 'flour_withdrawal').reduce((n, m) => n + toCents(m.amount), 0) / 100;

  const rows = inP
    .filter((m) => filters.type === 'all' || LOG_MATCH[filters.type].includes(m.type))
    .filter((m) => filters.customer == null || (filters.customer === 'walk_in' ? m.customer_id == null : m.customer_id === filters.customer))
    .filter((m) => !filters.recordedBy || m.recorded_by_name === filters.recordedBy)
    .slice()
    .reverse();

  const bars: { label: string; income: number }[] = [];
  for (let i = 6; i >= 0; i--) {
    const r = bounds(period, shift(period, range[0], -i));
    const v = -movements.filter((m) => m.type === 'payment' && inRange(m.movement_date, r)).reduce((n, m) => n + toCents(m.amount), 0);
    bars.push({ label: tick(period, r[0]), income: v });
  }

  return {
    range,
    breadKg: kg(),
    cashKg: kg('cash'),
    flourKg: kg('flour'),
    income,
    transfer,
    cash: income - transfer,
    received,
    spent,
    rows,
    bars,
    recorders: Array.from(new Set(inP.map((m) => m.recorded_by_name))),
  };
}

export interface StatementRow {
  movement: ApiMovement;
  debit: number; // عليه, positive hundredths
  credit: number; // له, positive hundredths
  balance: number; // running, signed hundredths
}

/** Customer::statement(): previous balance, rows with the running balance, closing balance. */
export function statement(movements: ApiMovement[], customerId: number, ledger: Ledger, from: string | null, to: string | null) {
  const mine = movements.filter((m) => m.customer_id === customerId && m.ledger === ledger);
  const before = from ? mine.filter((m) => m.movement_date < from) : [];
  const opening = before.reduce((n, m) => n + toCents(m.amount), 0);
  let running = opening;
  const rows: StatementRow[] = mine
    .filter((m) => (!from || m.movement_date >= from) && (!to || m.movement_date <= to))
    .map((m) => {
      const a = toCents(m.amount);
      running += a;
      return { movement: m, debit: a > 0 ? a : 0, credit: a < 0 ? -a : 0, balance: running };
    });
  return { opening, rows, closing: running };
}

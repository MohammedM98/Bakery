/**
 * The receipt (CLAUDE.md section 13): bakery, date and time, movement number, customer or walk-in buyer,
 * quantity, price and amount, the balances after it (money by sign, flour in words) and who recorded it.
 * Printed only once the server confirmed the operation, from its answer ("رد حركة", docs/api.md).
 */

import type { ApiMovement, OperationResult } from '../api/types.ts';
import { formatDate, formatMoney, formatTime, localTime } from './format.ts';

export interface Receipt {
  bakery: string;
  at: string;
  numbers: number[];
  who: string;
  lines: [string, string][];
  balances: { money: string; flour: string } | null;
  by: string;
}

const line = (m: ApiMovement): [string, string] => [m.description, `${m.amount_text} ${m.direction_label}`];

export function fromResult(r: OperationResult, bakery: string): Receipt | null {
  const movements = r.movements ?? [];
  if (!movements.length) return null;
  const sale = r.sale;
  return {
    bakery,
    at: movements[0].created_at,
    numbers: movements.map((m) => m.number!).filter((n) => n != null),
    who: r.customer?.name ?? sale?.buyer ?? 'مشترٍ عابر',
    lines: sale
      ? [
          ['الكمية', sale.kg_text],
          ['السعر للكيلو', formatMoney(sale.price_per_kg)],
          ['المبلغ', sale.total_text],
          ['الحالة', sale.payment_status_label],
        ]
      : movements.map(line),
    balances: r.customer ? { money: r.customer.money_balance_text, flour: r.customer.flour_balance_text } : null,
    by: movements[0].recorded_by,
  };
}

/** One confirmed movement from a statement, with the running balance after it. */
export function fromMovement(m: ApiMovement, bakery: string, who: string, balanceAfter: { money?: string; flour?: string }): Receipt {
  return {
    bakery,
    at: m.created_at,
    numbers: m.number != null ? [m.number] : [],
    who,
    lines: [line(m)],
    balances: balanceAfter.money || balanceAfter.flour ? { money: balanceAfter.money ?? '', flour: balanceAfter.flour ?? '' } : null,
    by: m.recorded_by,
  };
}

const esc = (s: string) => s.replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);

/** "small" = an 80 mm roll (58–80 mm printers shrink it), "a4" = a page. */
export function receiptHtml(r: Receipt, size: 'small' | 'a4'): string {
  const small = size === 'small';
  const rows = (items: [string, string][]) => items.map(([k, v]) => `<tr><td>${esc(k)}</td><td class="v">${esc(v)}</td></tr>`).join('');
  const balances: [string, string][] = r.balances
    ? ([
        ['الرصيد المالي', r.balances.money],
        ['رصيد الطحين', r.balances.flour],
      ].filter(([, v]) => v) as [string, string][])
    : [];
  return `<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><style>
@page { size: ${small ? '80mm auto' : 'A4'}; margin: ${small ? '4mm' : '18mm'}; }
body { font-family: 'IBM Plex Sans Arabic', 'Noto Sans Arabic', 'Geeza Pro', sans-serif; color: #18202b; font-size: ${small ? '12px' : '15px'}; margin: 0; ${small ? 'width: 72mm;' : 'max-width: 150mm;'} }
h1 { font-size: ${small ? '16px' : '22px'}; font-weight: 600; margin: 0 0 2px; text-align: center; }
.meta { text-align: center; color: #4e5a6a; margin-bottom: 10px; }
table { width: 100%; border-collapse: collapse; }
td { padding: ${small ? '3px 0' : '6px 0'}; border-bottom: 1px solid #e5e9ef; vertical-align: top; }
td.v { text-align: left; font-variant-numeric: tabular-nums; white-space: nowrap; padding-inline-start: 8px; }
.sec { margin-top: 10px; font-weight: 600; }
.foot { margin-top: 12px; text-align: center; color: #4e5a6a; }
</style></head><body>
<h1>${esc(r.bakery)}</h1>
<div class="meta">${formatDate(localTime(r.at))} ${formatTime(r.at)}${r.numbers.length ? ` · حركة رقم ${r.numbers.join('، ')}` : ''}</div>
<table>${rows([['الزبون', r.who]])}${rows(r.lines)}</table>
${balances.length ? `<div class="sec">الرصيد بعد العملية</div><table>${rows(balances)}</table>` : ''}
<div class="foot">سجّلها ${esc(r.by)}</div>
</body></html>`;
}

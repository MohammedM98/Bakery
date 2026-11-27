/**
 * The six operations the phone records offline (Operations::OFFLINE): each builds the queued operation with
 * the payload /sync/push takes (docs/api.md, same fields as the website's forms) and the movements the server
 * is expected to write, shown as "ينتظر المزامنة" until it answers. Amounts are checked again by the server.
 */

import type { ApiCustomer, ApiMovement, Ledger, MovementType, OfflineType, QueuedOperation } from '../api/types.ts';
import { formatDate, formatKg, formatMoney, fromCents, toCents } from '../lib/format.ts';

export interface Ctx {
  uuid: string;
  now: string;
  today: string;
  by: string;
  customers: ApiCustomer[];
}

export interface Built {
  op: QueuedOperation;
  movements: ApiMovement[];
  customer?: ApiCustomer;
}

const LABELS: Record<MovementType, string> = {
  sale: 'بيع عادي',
  exchange_fee: 'رسم تبديل طحين',
  flour_withdrawal: 'بيع مقابل طحين',
  deposit: 'إيداع طحين',
  payment: 'دفعة',
  adjustment: 'تسوية',
  opening_balance: 'رصيد افتتاحي',
};

/** Decimal with at most 2 places, as the server's `decimal:0,2` rule takes it. */
const dec = (n: number) => fromCents(toCents(n));
/** 12.00 → "12", 12.5 → "12.5": the server's kg() in descriptions. */
const kgNum = (n: number) => formatKg(n).replace(' كغ', '');

function mv(c: Ctx, customerId: number | null, ledger: Ledger, type: MovementType, cents: number, description: string, extra: Partial<ApiMovement> = {}): ApiMovement {
  const size = Math.abs(cents);
  return {
    number: null,
    customer_id: customerId,
    sale_id: null,
    ledger,
    type,
    type_label: LABELS[type],
    date: c.today,
    amount: fromCents(cents),
    direction: cents >= 0 ? 'debit' : 'credit',
    direction_label: cents >= 0 ? 'عليه' : 'له',
    amount_text: ledger === 'money' ? formatMoney(fromCents(size)) : formatKg(fromCents(size)),
    description,
    note: null,
    transfer: null,
    transfer_reference: null,
    recorded_by: c.by,
    created_at: c.now,
    ...extra,
  };
}

/** A customer added offline has no server id yet: the server finds it by its customer.create client_uuid. */
function customerRef(c: Ctx, id: number) {
  const local = c.customers.find((x) => x.id === id);
  return local?.client_uuid && id < 0 ? { customer_client_uuid: local.client_uuid } : { customer_id: id };
}

const nameOf = (c: Ctx, id: number | null) => c.customers.find((x) => x.id === id)?.name ?? '';

function op(c: Ctx, type: OfflineType, payload: Record<string, unknown>, title: string, subtitle: string): QueuedOperation {
  return { client_uuid: c.uuid, type, client_created_at: c.now, payload, title, subtitle, status: 'pending' };
}

/** The website's cash sale: by the kilo, to a walk-in buyer (an optional name), at the last price the phone got. */
export function sale(c: Ctx, i: { kg: number; paid: boolean; buyerName: string; price: string }): Built {
  return {
    op: op(c, 'sale', { kg_amount: dec(i.kg), payment_status: i.paid ? 'paid' : 'unpaid', sale_date: c.today, buyer_name: i.buyerName.trim() || null, price_per_kg: i.price }, `بيع عادي، ${formatKg(i.kg)}`, i.buyerName.trim() || 'مشترٍ عابر'),
    // Walk-in movements are not kept on the phone (no statement); the queue shows the sale.
    movements: [],
  };
}

export function flourSale(c: Ctx, i: { customerId: number; kg: number; paid: boolean; fee: string }): Built {
  const fee = Math.round(toCents(i.fee) * i.kg);
  const kg = toCents(i.kg);
  return {
    op: op(c, 'flour_sale', { ...customerRef(c, i.customerId), kg_amount: dec(i.kg), payment_status: i.paid ? 'paid' : 'unpaid', sale_date: c.today, price_per_kg: i.fee }, `خبز مقابل طحين، ${formatKg(i.kg)}`, nameOf(c, i.customerId)),
    movements: [
      mv(c, i.customerId, 'flour', 'flour_withdrawal', kg, `خبز مقابل طحين ${kgNum(i.kg)} كغ`),
      ...(fee > 0 ? [mv(c, i.customerId, 'money', 'exchange_fee', fee, `رسم تبديل طحين ${kgNum(i.kg)} كغ × ${formatMoney(i.fee)}`)] : []),
      ...(i.paid && fee > 0 ? [mv(c, i.customerId, 'money', 'payment', -fee, `دفعة عن بيع ${formatDate(c.today)}`, { transfer: 'نقدًا' })] : []),
    ],
  };
}

export function deposit(c: Ctx, i: { customerId: number; kg: number; note: string }): Built {
  return {
    op: op(c, 'deposit', { ...customerRef(c, i.customerId), kg_amount: dec(i.kg), deposit_date: c.today, notes: i.note.trim() || null }, `إيداع طحين، ${formatKg(i.kg)}`, nameOf(c, i.customerId)),
    movements: [mv(c, i.customerId, 'flour', 'deposit', -toCents(i.kg), `إيداع طحين ${kgNum(i.kg)} كغ`, { note: i.note.trim() || null })],
  };
}

export function payment(
  c: Ctx,
  i: { customerId: number; amount: number; method: 'cash' | 'transfer'; fromBank: { id: number; name: string } | null; toBank: { id: number; name: string } | null; reference: string; sale: { id: number; sale_date: string } | null },
): Built {
  const transfer = i.method === 'transfer' && i.fromBank && i.toBank;
  return {
    op: op(
      c,
      'payment',
      {
        ...customerRef(c, i.customerId),
        amount: dec(i.amount),
        payment_date: c.today,
        sale_id: i.sale?.id ?? null,
        method: i.method,
        ...(transfer ? { from_bank_id: i.fromBank!.id, to_bank_id: i.toBank!.id, transfer_reference: i.reference.trim() || null } : {}),
      },
      `دفعة، ${formatMoney(dec(i.amount))}`,
      nameOf(c, i.customerId),
    ),
    movements: [
      mv(c, i.customerId, 'money', 'payment', -toCents(i.amount), i.sale ? `دفعة عن بيع ${formatDate(i.sale.sale_date)}` : 'دفعة', {
        sale_id: i.sale?.id ?? null,
        transfer: transfer ? `${i.fromBank!.name} ← ${i.toBank!.name}` : 'نقدًا',
        transfer_reference: transfer ? i.reference.trim() || null : null,
      }),
    ],
  };
}

export function adjustment(c: Ctx, i: { customerId: number; ledger: Ledger; direction: 'debit' | 'credit'; amount: number; reason: string; corrects: number | null }): Built {
  const cents = toCents(i.amount) * (i.direction === 'debit' ? 1 : -1);
  return {
    op: op(
      c,
      'adjustment',
      { ...customerRef(c, i.customerId), ledger: i.ledger, direction: i.direction, amount: dec(i.amount), reason: i.reason.trim(), movement_date: c.today, corrects_number: i.corrects },
      i.ledger === 'money' ? 'قيد تسوية مالي' : 'قيد تسوية طحين',
      nameOf(c, i.customerId),
    ),
    movements: [mv(c, i.customerId, i.ledger, 'adjustment', cents, `تسوية: ${i.reason.trim()}${i.corrects ? ` (تصحيح الحركة رقم ${i.corrects})` : ''}`)],
  };
}

/** A customer added offline: a negative local id until the server gives one. */
export function customer(c: Ctx, i: { name: string; phone: string; notes: string; openingFlour: number }, localId: number): Built {
  const flour = toCents(i.openingFlour);
  const local: ApiCustomer = {
    id: localId,
    name: i.name.trim(),
    mobile_number: i.phone.trim() || null,
    notes: i.notes.trim() || null,
    money_balance: '0.00',
    money_balance_text: formatMoney(0),
    flour_balance: '0.00',
    flour_balance_text: formatKg(0),
    client_uuid: c.uuid,
  };
  return {
    op: { ...op(c, 'customer.create', { name: local.name, mobile_number: local.mobile_number, notes: local.notes, ...(flour > 0 ? { flour_balance_kg: dec(i.openingFlour) } : {}) }, 'إضافة زبون', local.name), local_id: localId },
    customer: local,
    movements: flour > 0 ? [mv(c, localId, 'flour', 'opening_balance', -flour, 'رصيد افتتاحي')] : [],
  };
}

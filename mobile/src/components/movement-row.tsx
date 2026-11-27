import * as React from 'react';

import { PendingMark, RoundIcon, Row, TYPE_ICONS } from '@/components/common';
import { useSheets } from '@/components/sheets';
import { Text } from '@/components/ui/text';
import { formatFlourBalance, formatKg, formatMoney, formatTime, toCents } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ApiMovement, ApiSale } from '@/mock/types';

/** "بيع عادي، 12 كغ" / "دفعة، جوال باي ← بنك فلسطين" / "إيداع طحين 40 كغ"... */
export function describe(m: ApiMovement, sale?: ApiSale): string {
  const kg = sale ? Number(sale.kg_amount) : 0;
  switch (m.type) {
    case 'sale':
      return `بيع عادي، ${formatKg(kg)}`;
    case 'flour_withdrawal':
    case 'exchange_fee':
      return `خبز مقابل طحين، ${formatKg(kg || Math.abs(Number(m.amount)))}`;
    case 'payment':
      return m.from_bank ? `دفعة، ${m.from_bank} ← ${m.to_bank}` : 'دفعة نقدًا';
    case 'deposit':
      return `إيداع طحين ${formatKg(-Number(m.amount))}`;
    case 'adjustment':
      return m.ledger === 'money' ? 'تسوية مالية' : 'تسوية طحين';
    case 'opening_balance':
      return 'رصيد افتتاحي';
  }
}

/** One movement in a list: type icon, customer, what happened, amount (money with sign, flour in words). */
export function MovementRow({
  m,
  sale,
  first,
  showTime = true,
  flourBalanceAfter,
}: {
  m: ApiMovement;
  sale?: ApiSale;
  first?: boolean;
  showTime?: boolean;
  /** For deposits on the home screen the prototype shows the customer's flour balance after it. */
  flourBalanceAfter?: number;
}) {
  const sheets = useSheets();
  const a = toCents(m.amount);
  const isPayment = m.type === 'payment';
  let amount: string;
  if (m.ledger === 'flour') amount = flourBalanceAfter != null ? formatFlourBalance(flourBalanceAfter / 100) : `${a > 0 ? 'عليه' : 'له'} ${formatKg(Math.abs(a) / 100)}`;
  else if (m.type === 'flour_withdrawal' || m.type === 'exchange_fee' || m.type === 'sale') amount = formatMoney((sale ? toCents(sale.total_amount) : a) / 100);
  else amount = formatMoney(Math.abs(a) / 100, { plus: isPayment });
  const sub = describe(m, sale);
  return (
    <Row
      first={first}
      onPress={() => sheets.open({ kind: 'movement', movementId: m.id })}
      accessibilityLabel={`${m.customer_name ?? 'مشترٍ عابر'}، ${sub}، ${amount}`}
      start={<RoundIcon icon={TYPE_ICONS[m.type]} />}
      title={m.customer_name ?? 'مشترٍ عابر'}
      subtitle={m.number == null ? <PendingMark label={`${sub}، ينتظر المزامنة`} /> : `${sub}${showTime ? `، ${formatTime(m.created_at)}` : ''}`}
      end={<Text className={cn('font-plex-medium', isPayment && 'text-success')}>{amount}</Text>}
    />
  );
}

import { Redirect, useLocalSearchParams } from 'expo-router';
import { ArrowLeftRight, Banknote, Pencil, Scale, StickyNote, UserX, Wheat, type LucideIcon } from 'lucide-react-native';
import * as React from 'react';
import { Pressable, ScrollView, View } from 'react-native';

import { Badge, Chip, EmptyState, ListCard, Notice, PendingMark, Row, Segmented } from '@/components/common';
import { Hero, Surface } from '@/components/glass';
import { IconButton, PageHeader, Screen } from '@/components/screen';
import { useSheets } from '@/components/sheets';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { formatAmount, formatDate, formatFlourBalance, formatMoney, formatShortDate, formatTime, localTime, ltr } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ApiMovement, CustomerDetail, Ledger } from '@/api/types';
import { today, useApp, useRemote } from '@/state/app';
import { statement } from '@/state/statement';
import { movementKey } from '@/sync/engine';

type Tab = Ledger | 'sales';
type Range = 'month' | 'three' | 'year' | 'all';
const RANGES: [Range, string][] = [
  ['month', 'هذا الشهر'],
  ['three', 'آخر 3 أشهر'],
  ['year', 'السنة'],
  ['all', 'الكل'],
];

function rangeDates(r: Range): [string | null, string | null] {
  const t = today();
  const monthStart = `${t.slice(0, 8)}01`;
  if (r === 'month') return [monthStart, t];
  if (r === 'three') {
    const d = new Date(`${monthStart}T12:00:00Z`);
    d.setUTCMonth(d.getUTCMonth() - 2);
    return [d.toISOString().slice(0, 10), t];
  }
  if (r === 'year') return [`${t.slice(0, 4)}-01-01`, t];
  return [null, null];
}

export default function CustomerPage() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const app = useApp();
  const sheets = useSheets();
  const customer = app.customer(Number(id));
  // A customer added offline got its server id: follow it.
  const moved = !customer ? app.state.queue.find((q) => q.local_id === Number(id) && q.result?.customer)?.result?.customer : undefined;
  const canMoney = app.can('view-money-statement');
  const canFlour = app.can('view-flour-statement');
  const canSales = app.can('view-sales');
  const tabs = [
    ...(canMoney ? ([['money', 'الحساب المالي']] as const) : []),
    ...(canFlour ? ([['flour', 'الطحين']] as const) : []),
    ...(canSales ? ([['sales', 'المبيعات']] as const) : []),
  ] as [Tab, string][];
  const [tab, setTab] = React.useState<Tab>(tabs[0]?.[0] ?? 'money');
  const [range, setRange] = React.useState<Range>('month');

  if (moved) return <Redirect href={`/customers/${moved.id}`} />;
  if (!customer)
    return (
      <Screen>
        <PageHeader title="الزبون" back />
        <EmptyState icon={UserX} title="الزبون غير موجود" body="ربما حُذف من جهاز آخر" />
      </Screen>
    );

  const b = app.balances.get(customer.id) ?? { money: 0, flour: 0, pending: false, moneyText: '', flourText: '' };
  const actions = [
    app.can('record-flour-sale') && { label: 'خبز مقابل طحين', icon: ArrowLeftRight, onPress: () => sheets.open({ kind: 'flour', customerId: customer.id }) },
    app.can('record-deposit') && { label: 'إيداع طحين', icon: Wheat, onPress: () => sheets.open({ kind: 'deposit', customerId: customer.id }) },
    app.can('record-payment') && { label: 'دفعة', icon: Banknote, onPress: () => sheets.open({ kind: 'payment', customerId: customer.id }) },
    app.can('record-adjustment') && { label: 'تسوية', icon: Scale, onPress: () => sheets.open({ kind: 'adjustment', customerId: customer.id }) },
  ].filter(Boolean) as { label: string; icon: LucideIcon; onPress: () => void }[];

  return (
    <Screen>
      <PageHeader
        back
        title={customer.name}
        subtitle={customer.mobile_number ? ltr(customer.mobile_number) : undefined}
        end={app.can('edit-customers') ? <IconButton icon={Pencil} label="تعديل الزبون" onPress={() => sheets.open({ kind: 'customer', customerId: customer.id })} /> : null}
      />

      <Hero className="mb-4 flex-row flex-wrap gap-3 p-5">
        <View className="min-w-[130px] flex-1">
          <Text className="text-hero-muted text-[13px]">الرصيد المالي</Text>
          <Text className="font-plex-semi text-[22px] leading-[30px] text-hero-foreground">
            {formatAmount(b.money / 100)}
            <Text className="text-hero-muted font-plex-medium text-[14px]"> شيكل {b.money > 0 ? 'عليه' : b.money < 0 ? 'له' : ''}</Text>
          </Text>
        </View>
        <View className="min-w-[130px] flex-1 border-s border-hero-foreground/10 ps-4">
          <Text className="text-hero-muted text-[13px]">رصيد الطحين</Text>
          <Text className="font-plex-semi text-[22px] leading-[30px] text-hero-foreground">{b.flourText}</Text>
        </View>
        {b.pending ? (
          <View className="w-full flex-row items-center gap-1.5">
            <View className="bg-ring h-1.5 w-1.5 rounded-full" />
            <Text className="text-hero-muted text-[13px]">رصيد مؤقت: عمليات تنتظر المزامنة</Text>
          </View>
        ) : null}
      </Hero>

      {actions.length ? (
        <View className="mb-5 flex-row gap-2">
          {actions.map((a) => (
            <Pressable key={a.label} onPress={a.onPress} accessibilityRole="button" className="flex-1 items-center gap-2 active:opacity-80">
              <Surface level="l2" radius={20} className="h-14 w-14 items-center justify-center">
                <Icon as={a.icon} size={20} strokeWidth={1.5} className="text-foreground" />
              </Surface>
              <Text className="text-center text-[13px] leading-[18px]">{a.label}</Text>
            </Pressable>
          ))}
        </View>
      ) : null}

      {customer.notes ? <Notice icon={StickyNote} body={customer.notes} /> : null}

      {tabs.length ? (
        <>
          <Segmented label="الكشف" className="mb-3" options={tabs} value={tab} onChange={setTab} />
          {tab === 'sales' ? (
            <SalesTab customerId={customer.id} />
          ) : (
            <StatementTab customerId={customer.id} ledger={tab} balance={tab === 'money' ? b.money : b.flour} range={range} setRange={setRange} />
          )}
        </>
      ) : null}
    </Screen>
  );
}

/** A statement line, from the phone's copy or from the server (a range older than the copy). */
interface Line {
  key: string;
  movement?: ApiMovement;
  number: number | null;
  date: string;
  description: string;
  transfer: string | null;
  amount: string;
  credit: boolean;
  balance: string;
}

function StatementTab({ customerId, ledger, balance, range, setRange }: { customerId: number; ledger: Ledger; balance: number; range: Range; setRange: (r: Range) => void }) {
  const app = useApp();
  const sheets = useSheets();
  const [from, to] = rangeDates(range);
  const money = ledger === 'money';
  const fmt = (v: number) => (money ? formatMoney(v / 100) : formatFlourBalance(v / 100));
  const floor = app.state.historyFloor;
  // The phone keeps the last 90 days; an older start is read from the server when online.
  const older = !!floor && (!from || from < floor) && customerId > 0;
  const remote = useRemote<CustomerDetail>(older && app.state.online ? `customers/${customerId}?from=${from ?? ''}&to=${to ?? ''}` : null);
  const server = older ? (money ? remote.data?.money_statement : remote.data?.flour_statement) : null;
  const local = React.useMemo(() => statement(app.state.movements, customerId, ledger, balance, from, to), [app.state.movements, customerId, ledger, balance, from, to]);

  let lines: Line[];
  let opening: string;
  let closing: string;
  if (server) {
    opening = server.opening_text;
    closing = server.closing_text;
    lines = server.rows.map((r) => ({
      key: `n${r.number}`,
      number: r.number,
      date: r.date,
      description: r.description,
      transfer: money ? r.transfer : null,
      amount: money ? (r.credit ? formatMoney(-Number(r.credit)) : (r.debit_text ?? '')) : `${r.credit ? 'له' : 'عليه'} ${r.credit_text ?? r.debit_text}`,
      credit: !!r.credit,
      balance: r.balance_text,
    }));
  } else {
    opening = fmt(local.opening);
    closing = fmt(local.closing);
    lines = local.rows.map((r) => {
      const m = r.movement;
      return {
        key: movementKey(m),
        movement: m,
        number: m.number,
        date: m.date,
        description: m.description,
        transfer: money && m.type === 'payment' ? m.transfer : null,
        amount: money ? formatMoney((r.credit ? -r.credit : r.debit) / 100) : `${r.credit ? 'له' : 'عليه'} ${m.amount_text}`,
        credit: !!r.credit,
        balance: fmt(r.balance),
      };
    });
  }
  const rows = lines.slice().reverse().slice(0, 60);

  return (
    <>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} className="-mx-5 mb-3" contentContainerStyle={{ paddingHorizontal: 20, gap: 8 }}>
        {RANGES.map(([k, l]) => (
          <Chip key={k} label={l} on={range === k} onPress={() => setRange(k)} />
        ))}
      </ScrollView>
      {older && !server ? (
        <Notice
          tone="warning"
          body={app.state.online ? (remote.error ? `تعذّر جلب الكشف الكامل: ${remote.error}` : 'جارٍ جلب الكشف الكامل من الخادم') : `على الهاتف الحركات من ${formatDate(floor)}. الكشف الأقدم يحتاج إنترنت`}
        />
      ) : null}
      <ListCard>
        <Row first title={<Text variant="muted">الرصيد الختامي</Text>} end={<Text className="font-plex-semi">{closing}</Text>} />
        {rows.map((r) => (
          <Row
            key={r.key}
            onPress={r.movement ? () => sheets.open({ kind: 'movement', movementKey: r.key }) : undefined}
            title={
              <Text className="font-plex-medium" numberOfLines={2}>
                {r.description}
              </Text>
            }
            subtitle={r.number == null ? <PendingMark label={`${formatShortDate(r.date)}، ينتظر المزامنة`} /> : `رقم ${r.number}، ${formatShortDate(r.date)}${r.transfer ? `، ${r.transfer}` : ''}`}
            end={<Text className={cn('font-plex-medium', r.credit ? 'text-success' : 'text-destructive')}>{r.amount}</Text>}
            endSub={<Text variant="muted">الرصيد {r.balance}</Text>}
          />
        ))}
        <Row title={<Text variant="muted">رصيد سابق{from ? `، ${formatShortDate(from)}` : ''}</Text>} end={<Text variant="muted">{opening}</Text>} />
      </ListCard>
      {lines.length > rows.length ? (
        <Text variant="muted" className="mt-2 text-center">
          تظهر آخر {rows.length} حركة من {lines.length}. اختر فترة أقصر
        </Text>
      ) : null}
    </>
  );
}

function SalesTab({ customerId }: { customerId: number }) {
  const app = useApp();
  const sheets = useSheets();
  const remote = useRemote<CustomerDetail>(customerId > 0 ? `customers/${customerId}` : null);
  const sales = (remote.data?.sales ?? []).slice(0, 40);
  if (!remote.data) return <EmptyState icon={Wheat} title={app.state.online ? 'جارٍ التحميل' : 'مبيعات الزبون تحتاج إنترنت'} />;
  if (!sales.length) return <EmptyState icon={Wheat} title="لا مبيعات لهذا الزبون" />;
  return (
    <>
      {remote.stale ? <Text variant="muted" className="mb-2">آخر تحديث {formatDate(localTime(remote.at))} {formatTime(remote.at)}</Text> : null}
      <ListCard>
        {sales.map((s, i) => {
          const unpaid = s.payment_status === 'unpaid';
          return (
            <Row
              key={s.id}
              first={i === 0}
              title={`${s.sale_type === 'flour_exchange' ? 'مقابل طحين' : 'بيع عادي'}، ${s.kg_text}`}
              subtitle={formatDate(s.sale_date)}
              end={<Text className="font-plex-medium">{s.total_text}</Text>}
              endSub={<Badge label={s.payment_status_label} tone={unpaid ? 'danger' : 'success'} />}
              onPress={unpaid && app.can('record-payment') ? () => sheets.open({ kind: 'payment', customerId, saleId: s.id }) : undefined}
            />
          );
        })}
      </ListCard>
    </>
  );
}

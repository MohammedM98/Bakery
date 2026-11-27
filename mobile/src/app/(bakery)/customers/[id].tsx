import { useLocalSearchParams } from 'expo-router';
import { ArrowLeftRight, Banknote, FileSpreadsheet, FileText, Pencil, Scale, StickyNote, UserX, Wheat, type LucideIcon } from 'lucide-react-native';
import * as React from 'react';
import { Pressable, ScrollView, View } from 'react-native';

import { Badge, Chip, EmptyState, ListCard, Notice, PendingMark, Row, Segmented } from '@/components/common';
import { Hero, Surface } from '@/components/glass';
import { IconButton, PageHeader, Screen } from '@/components/screen';
import { useSheets } from '@/components/sheets';
import { useToast } from '@/components/toast';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { formatAmount, formatDate, ltr, formatFlourBalance, formatKg, formatMoney, formatShortDate, toCents } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Ledger } from '@/mock/types';
import { TODAY, useApp } from '@/state/app';
import { statement } from '@/state/selectors';

type Tab = Ledger | 'sales';
type Range = 'month' | 'three' | 'year' | 'all';
const RANGES: [Range, string][] = [
  ['month', 'هذا الشهر'],
  ['three', 'آخر 3 أشهر'],
  ['year', 'السنة'],
  ['all', 'الكل'],
];

function rangeDates(r: Range): [string | null, string | null] {
  const monthStart = `${TODAY.slice(0, 8)}01`;
  if (r === 'month') return [monthStart, TODAY];
  if (r === 'three') {
    const d = new Date(`${monthStart}T12:00:00Z`);
    d.setUTCMonth(d.getUTCMonth() - 2);
    return [d.toISOString().slice(0, 10), TODAY];
  }
  if (r === 'year') return [`${TODAY.slice(0, 4)}-01-01`, TODAY];
  return [null, null];
}

export default function CustomerPage() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const app = useApp();
  const sheets = useSheets();
  const customer = app.state.customers.find((c) => c.id === Number(id));
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

  if (!customer)
    return (
      <Screen>
        <PageHeader title="الزبون" back />
        <EmptyState icon={UserX} title="الزبون غير موجود" body="ربما حُذف من جهاز آخر" />
      </Screen>
    );

  const b = app.balances.get(customer.id) ?? { money: 0, flour: 0, pending: false };
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
        subtitle={customer.phone ? ltr(customer.phone) : undefined}
        end={app.can('edit-customers') ? <IconButton icon={Pencil} label="تعديل الزبون" onPress={() => sheets.open({ kind: 'customer', customerId: customer.id })} /> : null}
      />

      <Hero className="mb-4 flex-row flex-wrap gap-3 p-5">
        <View className="min-w-[130px] flex-1">
          <Text className="text-hero-muted text-[13px]">الرصيد المالي</Text>
          <Text className="font-plex-semi text-[22px] leading-[30px] text-hero-foreground">
            {formatAmount(Math.abs(b.money) / 100)}
            <Text className="text-hero-muted font-plex-medium text-[14px]"> شيكل {b.money > 0 ? 'عليه' : b.money < 0 ? 'له' : ''}</Text>
          </Text>
        </View>
        <View className="min-w-[130px] flex-1 border-s border-hero-foreground/10 ps-4">
          <Text className="text-hero-muted text-[13px]">رصيد الطحين</Text>
          <Text className="font-plex-semi text-[22px] leading-[30px] text-hero-foreground">{formatFlourBalance(b.flour / 100)}</Text>
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
            <StatementTab customerId={customer.id} ledger={tab} range={range} setRange={setRange} />
          )}
        </>
      ) : null}
    </Screen>
  );
}

function StatementTab({ customerId, ledger, range, setRange }: { customerId: number; ledger: Ledger; range: Range; setRange: (r: Range) => void }) {
  const app = useApp();
  const sheets = useSheets();
  const toast = useToast();
  const [from, to] = rangeDates(range);
  const st = React.useMemo(() => statement(app.state.movements, customerId, ledger, from, to), [app.state.movements, customerId, ledger, from, to]);
  const money = ledger === 'money';
  const fmt = (v: number) => (money ? formatMoney(v / 100) : formatFlourBalance(v / 100));
  const rows = st.rows.slice().reverse().slice(0, 60);

  return (
    <>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} className="-mx-5 mb-3" contentContainerStyle={{ paddingHorizontal: 20, gap: 8 }}>
        {RANGES.map(([k, l]) => (
          <Chip key={k} label={l} on={range === k} onPress={() => setRange(k)} />
        ))}
      </ScrollView>
      {app.can('export-statements') ? (
        <View className="mb-3 flex-row gap-2">
          {(
            [
              ['PDF', FileText],
              ['Excel', FileSpreadsheet],
            ] as const
          ).map(([label, icon]) => (
            <Button
              key={label}
              variant="glass"
              size="sm"
              className="flex-1"
              disabled={!app.state.online}
              onPress={() => toast({ kind: 'success', title: `جارٍ تحميل الكشف ${label}` })}>
              <Icon as={icon} size={18} />
              <Text>{label === 'PDF' ? 'تحميل PDF' : 'تحميل Excel'}</Text>
            </Button>
          ))}
        </View>
      ) : null}
      {!app.state.online && app.can('export-statements') ? (
        <Text variant="muted" className="mb-3">
          التحميل يحتاج إنترنت
        </Text>
      ) : null}
      <ListCard>
        <Row first title={<Text variant="muted">الرصيد الختامي</Text>} end={<Text className="font-plex-semi">{fmt(st.closing)}</Text>} />
        {rows.map((r) => {
          const m = r.movement;
          const v = r.debit || r.credit;
          return (
            <Row
              key={m.id}
              onPress={() => sheets.open({ kind: 'movement', movementId: m.id })}
              title={
                <Text className="font-plex-medium" numberOfLines={2}>
                  {m.description}
                </Text>
              }
              subtitle={m.number == null ? <PendingMark label={`${formatShortDate(m.movement_date)}، ينتظر المزامنة`} /> : `رقم ${m.number}، ${formatShortDate(m.movement_date)}${money && m.type === 'payment' ? `، ${m.from_bank ? `${m.from_bank} ← ${m.to_bank}` : 'نقدًا'}` : ''}`}
              end={
                <Text className={cn('font-plex-medium', r.credit ? 'text-success' : 'text-destructive')}>
                  {money ? formatMoney((r.credit ? -v : v) / 100) : `${r.credit ? 'له' : 'عليه'} ${formatKg(v / 100)}`}
                </Text>
              }
              endSub={<Text variant="muted">الرصيد {fmt(r.balance)}</Text>}
            />
          );
        })}
        <Row title={<Text variant="muted">رصيد سابق{from ? `، ${formatShortDate(from)}` : ''}</Text>} end={<Text variant="muted">{fmt(st.opening)}</Text>} />
      </ListCard>
      {st.rows.length > rows.length ? (
        <Text variant="muted" className="mt-2 text-center">
          تظهر آخر {rows.length} حركة من {st.rows.length}. اختر فترة أقصر أو حمّل الكشف
        </Text>
      ) : null}
    </>
  );
}

function SalesTab({ customerId }: { customerId: number }) {
  const app = useApp();
  const sheets = useSheets();
  const sales = app.state.sales.filter((s) => s.customer_id === customerId).slice(-40).reverse();
  if (!sales.length) return <EmptyState icon={Wheat} title="لا مبيعات لهذا الزبون" />;
  return (
    <ListCard>
      {sales.map((s, i) => {
        const rest = toCents(s.total_amount) - toCents(s.paid_amount);
        return (
          <Row
            key={s.id}
            first={i === 0}
            title={`${s.sale_type === 'flour' ? 'مقابل طحين' : 'بيع عادي'}، ${formatKg(s.kg_amount)}`}
            subtitle={formatDate(s.sale_date)}
            end={<Text className="font-plex-medium">{formatMoney(s.total_amount)}</Text>}
            endSub={rest > 0 ? <Badge label="غير مدفوع" tone="danger" /> : <Badge label="مدفوع" tone="success" />}
            onPress={rest > 0 && app.can('record-payment') ? () => sheets.open({ kind: 'payment', customerId, saleId: s.id }) : undefined}
          />
        );
      })}
    </ListCard>
  );
}


import { router } from 'expo-router';
import { ChevronLeft, ChevronRight, ListFilter, SearchX, Wheat, WifiOff } from 'lucide-react-native';
import * as React from 'react';
import { Pressable, ScrollView, View } from 'react-native';

import { BarChart } from '@/components/charts';
import type { MovementType } from '@/api/types';
import { Chip, EmptyState, ListCard, Notice, Segmented } from '@/components/common';
import { Hero, Surface } from '@/components/glass';
import { LineRow } from '@/components/movement-row';
import { NotAllowed, Screen } from '@/components/screen';
import { Icon } from '@/components/ui/icon';
import { Skeleton } from '@/components/ui/skeleton';
import { Text } from '@/components/ui/text';
import { formatAmount, formatDate, formatTime, localTime } from '@/lib/format';
import { PERIODS, type Period } from '@/lib/period';
import { today, useApp, useRemote } from '@/state/app';

type LogType = '' | 'sale' | 'flour_sale' | 'payment' | 'deposit' | 'adjustment';
const LOG_TYPES: [LogType, string][] = [
  ['', 'الكل'],
  ['sale', 'بيع عادي'],
  ['flour_sale', 'مقابل طحين'],
  ['payment', 'دفعات'],
  ['deposit', 'إيداع طحين'],
  ['adjustment', 'تسوية'],
];

interface LogMovement {
  number: number;
  type: MovementType;
  type_label: string;
  time: string | null;
  customer_id: number | null;
  customer: string | null;
  ledger: 'money' | 'flour';
  amount: string;
  amount_text: string;
  direction_label: string;
  description: string;
  recorded_by: string | null;
}

interface LogData {
  range: { from: string; to: string; label: string };
  previousDate: string;
  nextDate: string | null;
  summary: Record<'bread_kg' | 'money_cash' | 'money_transfer', string> &
    Record<'bread_regular_kg_text' | 'bread_flour_kg_text' | 'money_received_text' | 'flour_spent_kg_text' | 'flour_received_kg_text', string>;
  /** A Laravel paginator (flat: data, current_page, last_page, total). */
  movements: { data: LogMovement[]; current_page: number; last_page: number; total: number };
  options: { customers: { id: number; name: string }[]; recordedBy: string[] };
  chart: { label: string; received: number }[];
}

/** Sales log (website: سجل مبيعات المخبز, GET /sales-log): period with prev/next, summary for the whole period, filtered movements. */
export default function SalesLog() {
  const app = useApp();
  const [period, setPeriod] = React.useState<Period>('week');
  const [date, setDate] = React.useState(today());
  const [type, setType] = React.useState<LogType>('');
  const [customer, setCustomer] = React.useState('');
  const [recordedBy, setRecordedBy] = React.useState('');
  const [filtersOpen, setFiltersOpen] = React.useState(false);
  const [extra, setMore] = React.useState<{ query: string; rows: LogMovement[]; page: number; last: number } | null>(null);
  const query = `period=${period}&date=${date}&type=${type}&customer=${customer}&recorded_by=${encodeURIComponent(recordedBy)}`;
  const remote = useRemote<LogData>(app.can('view-sales-log') ? `sales-log?${query}` : null);
  // Pages loaded with 'عرض المزيد' belong to one filter; another filter starts again from page 1.
  const more = extra?.query === query ? extra : null;
  if (!app.can('view-sales-log')) return <NotAllowed />;

  const d = remote.data;
  const filtered = type !== '' || customer !== '' || recordedBy !== '';
  const rows = [...(d?.movements.data ?? []), ...(more?.rows ?? [])];
  const page = more?.page ?? d?.movements.current_page ?? 1;
  const last = more?.last ?? d?.movements.last_page ?? 1;
  const loadMore = async () => {
    const r = await app.call<LogData>('GET', `sales-log?${query}&page=${page + 1}`);
    if (r.ok) setMore({ query, rows: [...(more?.rows ?? []), ...r.data!.movements.data], page: page + 1, last: r.data!.movements.last_page });
  };
  const kg = (v: string) => formatAmount(v).replace('.00', '');

  return (
    <Screen>
      <View className="mb-4 mt-2 flex-row items-center gap-3">
        <Text variant="title" className="flex-1">
          السجل
        </Text>
        {d ? (
          <Surface level="l2" radius={999} blur={false} className="h-11 flex-row items-center">
            <Pressable onPress={() => setDate(d.previousDate)} accessibilityRole="button" accessibilityLabel="الفترة السابقة" className="h-11 w-10 items-center justify-center">
              <Icon as={ChevronRight} size={18} strokeWidth={1.5} className="text-foreground" />
            </Pressable>
            <Text className="font-plex-medium text-[13px]">{d.range.label}</Text>
            <Pressable onPress={() => d.nextDate && setDate(d.nextDate)} disabled={!d.nextDate} accessibilityRole="button" accessibilityLabel="الفترة التالية" className="h-11 w-10 items-center justify-center">
              <Icon as={ChevronLeft} size={18} strokeWidth={1.5} className={!d.nextDate ? 'text-muted-foreground/40' : 'text-foreground'} />
            </Pressable>
          </Surface>
        ) : null}
      </View>
      <Segmented
        label="الفترة"
        className="mb-4"
        options={PERIODS}
        value={period}
        onChange={(p) => {
          setPeriod(p);
          setDate(today());
        }}
      />
      {remote.stale ? <Notice tone="warning" icon={WifiOff} body={`السجل كما كان عند آخر اتصال، ${formatDate(localTime(remote.at))} ${formatTime(remote.at)}`} /> : null}

      {!d ? (
        remote.error || !app.state.online ? (
          <EmptyState icon={WifiOff} title="السجل يحتاج إنترنت" body={remote.error ?? 'يُقرأ من الخادم ويُحفظ آخر ما فُتح منه للعرض دون اتصال'} action={app.state.online ? 'إعادة المحاولة' : undefined} onAction={remote.reload} />
        ) : (
          <View className="gap-3">
            <Skeleton className="h-28 w-full rounded-[24px]" />
            <Skeleton className="h-24 w-full rounded-[22px]" />
          </View>
        )
      ) : (
        <>
          <Hero className="mb-3 p-5">
            <View className="flex-row items-center gap-3">
              <View className="min-w-0 flex-1">
                <Text className="text-hero-muted text-[13px]">الخبز المباع</Text>
                <Text className="font-plex-semi text-[28px] leading-[36px] text-hero-foreground">
                  {kg(d.summary.bread_kg)}
                  <Text className="text-hero-muted font-plex-medium text-[15px]"> كغ</Text>
                </Text>
              </View>
              <Icon as={Wheat} size={24} strokeWidth={1.5} className="text-wheat" />
            </View>
            <Text className="text-hero-muted text-[13px]">
              عادي <Text className="font-plex-semi text-[13px] text-hero-foreground">{d.summary.bread_regular_kg_text}</Text>
              {'   '}مقابل طحين <Text className="font-plex-semi text-[13px] text-hero-foreground">{d.summary.bread_flour_kg_text}</Text>
            </Text>
          </Hero>
          <View className="mb-3 flex-row gap-3">
            <Surface level="l1" radius={22} className="flex-1 p-4">
              <Text variant="muted">الدخل المالي</Text>
              <Text className="font-plex-semi text-[18px] leading-[26px]">{d.summary.money_received_text}</Text>
              <Text variant="muted">
                نقدًا {formatAmount(d.summary.money_cash)}، تحويل {formatAmount(d.summary.money_transfer)}
              </Text>
            </Surface>
            <Surface level="l1" radius={22} className="flex-1 p-4">
              <Text variant="muted">الطحين</Text>
              <Text className="font-plex-semi text-[18px] leading-[26px]">
                {d.summary.flour_received_kg_text} <Text variant="muted">مستلَم</Text>
              </Text>
              <Text variant="muted">مصروف {d.summary.flour_spent_kg_text}</Text>
            </Surface>
          </View>
          {d.chart?.length ? (
            <Surface level="l1" radius={22} className="mb-5 p-4">
              <Text className="font-plex-semi mb-2 text-[15px]">الدخل المالي، آخر 7 فترات</Text>
              <BarChart
                values={d.chart.map((b) => b.received)}
                labels={d.chart.map((b) => b.label)}
                format={(v) => formatAmount(v).replace('.00', '')}
                label={`الدخل المالي لآخر 7 فترات، الحالية ${d.summary.money_received_text}`}
              />
            </Surface>
          ) : null}

          <View className="mb-3 flex-row items-center justify-between">
            <Text variant="section">الحركات</Text>
            <Pressable onPress={() => setFiltersOpen((o) => !o)} accessibilityRole="button" accessibilityState={{ expanded: filtersOpen }} className="h-11 flex-row items-center gap-1.5 px-1">
              <Icon as={ListFilter} size={18} strokeWidth={1.5} className={filtered ? 'text-foreground' : 'text-muted-foreground'} />
              <Text className={filtered ? 'font-plex-medium text-[14px]' : 'text-muted-foreground text-[14px]'}>{filtered ? 'تصفية مفعّلة' : 'تصفية'}</Text>
            </Pressable>
          </View>
          {filtersOpen ? (
            <View className="mb-3 gap-2">
              <ChipRow label="النوع" items={LOG_TYPES.map(([k, l]) => ({ key: k, label: l }))} value={type} onChange={(k) => setType(k as LogType)} />
              <ChipRow
                label="الزبون"
                items={[{ key: '', label: 'الكل' }, { key: 'walk_in', label: 'مشترٍ عابر' }, ...d.options.customers.map((c) => ({ key: String(c.id), label: c.name }))]}
                value={customer}
                onChange={setCustomer}
              />
              <ChipRow label="سجّلها" items={[{ key: '', label: 'الكل' }, ...d.options.recordedBy.map((r) => ({ key: r, label: r }))]} value={recordedBy} onChange={setRecordedBy} />
            </View>
          ) : null}

          {rows.length ? (
            <ListCard>
              {rows.map((m, i) => (
                <LineRow
                  key={m.number}
                  first={i === 0}
                  type={m.type}
                  title={m.customer ?? 'مشترٍ عابر'}
                  subtitle={`${m.description}${m.time ? `، ${m.time}` : ''}`}
                  amount={m.ledger === 'flour' ? `${m.direction_label} ${m.amount_text}` : m.amount_text}
                  positive={m.type === 'payment'}
                  onPress={m.customer_id && app.can('view-customers') ? () => router.push(`/customers/${m.customer_id}`) : undefined}
                />
              ))}
              {page < last && app.state.online ? (
                <Pressable onPress={loadMore} accessibilityRole="button" className="h-12 items-center justify-center border-t border-border">
                  <Text className="font-plex-medium text-[14px]">عرض المزيد ({d.movements.total - rows.length})</Text>
                </Pressable>
              ) : null}
            </ListCard>
          ) : (
            <EmptyState
              icon={SearchX}
              title={filtered ? 'لا حركات تطابق التصفية' : 'لا حركات في هذه الفترة'}
              action={filtered ? 'مسح التصفية' : undefined}
              onAction={() => (setType(''), setCustomer(''), setRecordedBy(''))}
            />
          )}
        </>
      )}
    </Screen>
  );
}

function ChipRow({ label, items, value, onChange }: { label: string; items: { key: string; label: string }[]; value: string; onChange: (k: string) => void }) {
  return (
    <View className="flex-row items-center gap-2">
      <Text variant="muted" className="w-12">
        {label}
      </Text>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8, paddingEnd: 20 }} className="-me-5">
        {items.map((it) => (
          <Chip key={it.key} label={it.label} on={value === it.key} onPress={() => onChange(it.key)} />
        ))}
      </ScrollView>
    </View>
  );
}

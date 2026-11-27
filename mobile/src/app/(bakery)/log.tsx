import { ChevronLeft, ChevronRight, ListFilter, SearchX, Wheat } from 'lucide-react-native';
import * as React from 'react';
import { Pressable, ScrollView, View } from 'react-native';

import { BarChart } from '@/components/charts';
import { Chip, EmptyState, ListCard, Segmented } from '@/components/common';
import { Hero, Surface } from '@/components/glass';
import { MovementRow } from '@/components/movement-row';
import { NotAllowed, Screen } from '@/components/screen';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { formatAmount, formatKg, formatMoney } from '@/lib/format';
import { bounds, label, PERIODS, shift, type Period } from '@/lib/period';
import { TODAY, useApp } from '@/state/app';
import { LOG_TYPES, salesLog, type LogType } from '@/state/selectors';

/** Sales log (website: سجل مبيعات المخبز): period with prev/next, summary for the whole period, filtered movements. */
export default function SalesLog() {
  const app = useApp();
  const [period, setPeriod] = React.useState<Period>('week');
  const [date, setDate] = React.useState(TODAY);
  const [type, setType] = React.useState<LogType>('all');
  const [customer, setCustomer] = React.useState<number | 'walk_in' | null>(null);
  const [recordedBy, setRecordedBy] = React.useState<string | null>(null);
  const [filtersOpen, setFiltersOpen] = React.useState(false);
  const [limit, setLimit] = React.useState(30);
  const d = React.useMemo(
    () => salesLog(app.state.movements, app.state.sales, period, date, { type, customer, recordedBy }),
    [app.state.movements, app.state.sales, period, date, type, customer, recordedBy],
  );
  const salesById = React.useMemo(() => new Map(app.state.sales.map((s) => [s.id, s])), [app.state.sales]);
  if (!app.can('view-sales-log')) return <NotAllowed />;

  const [start, end] = bounds(period, date);
  const atToday = end >= TODAY;
  const go = (n: number) => setDate(shift(period, start, n));
  const filtered = type !== 'all' || customer != null || recordedBy != null;

  return (
    <Screen>
      <View className="mb-4 mt-2 flex-row items-center gap-3">
        <Text variant="title" className="flex-1">
          السجل
        </Text>
        <Surface level="l2" radius={999} blur={false} className="h-11 flex-row items-center">
          <Pressable onPress={() => go(-1)} accessibilityRole="button" accessibilityLabel="الفترة السابقة" className="h-11 w-10 items-center justify-center">
            <Icon as={ChevronRight} size={18} strokeWidth={1.5} className="text-foreground" />
          </Pressable>
          <Text className="font-plex-medium text-[13px]">{label(period, start, end)}</Text>
          <Pressable onPress={() => !atToday && go(1)} disabled={atToday} accessibilityRole="button" accessibilityLabel="الفترة التالية" className="h-11 w-10 items-center justify-center">
            <Icon as={ChevronLeft} size={18} strokeWidth={1.5} className={atToday ? 'text-muted-foreground/40' : 'text-foreground'} />
          </Pressable>
        </Surface>
      </View>
      <Segmented
        label="الفترة"
        className="mb-4"
        options={PERIODS}
        value={period}
        onChange={(p) => {
          setPeriod(p);
          setDate(TODAY);
        }}
      />

      <Hero className="mb-3 p-5">
        <View className="flex-row items-center gap-3">
          <View className="min-w-0 flex-1">
            <Text className="text-hero-muted text-[13px]">الخبز المباع</Text>
            <Text className="font-plex-semi text-[28px] leading-[36px] text-white">
              {formatAmount(d.breadKg).replace('.00', '')}
              <Text className="text-hero-muted font-plex-medium text-[15px]"> كغ</Text>
            </Text>
          </View>
          <Icon as={Wheat} size={24} strokeWidth={1.5} className="text-wheat" />
        </View>
        <Text className="text-hero-muted text-[13px]">
          عادي <Text className="font-plex-semi text-[13px] text-white">{formatKg(d.cashKg)}</Text>
          {'   '}مقابل طحين <Text className="font-plex-semi text-[13px] text-white">{formatKg(d.flourKg)}</Text>
        </Text>
      </Hero>
      <View className="mb-3 flex-row gap-3">
        <Surface level="l1" radius={22} className="flex-1 p-4">
          <Text variant="muted">الدخل المالي</Text>
          <Text className="font-plex-semi text-[18px] leading-[26px]">{formatMoney(d.income / 100)}</Text>
          <Text variant="muted">
            نقدًا {formatAmount(d.cash / 100)}، تحويل {formatAmount(d.transfer / 100)}
          </Text>
        </Surface>
        <Surface level="l1" radius={22} className="flex-1 p-4">
          <Text variant="muted">الطحين</Text>
          <Text className="font-plex-semi text-[18px] leading-[26px]">
            {formatKg(d.received)} <Text variant="muted">مستلَم</Text>
          </Text>
          <Text variant="muted">مصروف {formatKg(d.spent)}</Text>
        </Surface>
      </View>
      <Surface level="l1" radius={22} className="mb-5 p-4">
        <Text className="font-plex-semi mb-2 text-[15px]">الدخل المالي، آخر 7 فترات</Text>
        <BarChart
          values={d.bars.map((b) => b.income / 100)}
          labels={d.bars.map((b) => b.label)}
          format={(v) => formatAmount(v).replace('.00', '')}
          label={`الدخل المالي لآخر 7 فترات، الحالية ${formatMoney(d.income / 100)}`}
        />
      </Surface>

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
            items={[{ key: 'all', label: 'الكل' }, { key: 'walk_in', label: 'مشترٍ عابر' }, ...app.state.customers.map((c) => ({ key: String(c.id), label: c.name }))]}
            value={customer == null ? 'all' : String(customer)}
            onChange={(k) => setCustomer(k === 'all' ? null : k === 'walk_in' ? 'walk_in' : Number(k))}
          />
          <ChipRow label="سجّلها" items={[{ key: 'all', label: 'الكل' }, ...d.recorders.map((r) => ({ key: r, label: r }))]} value={recordedBy ?? 'all'} onChange={(k) => setRecordedBy(k === 'all' ? null : k)} />
        </View>
      ) : null}

      {d.rows.length ? (
        <ListCard>
          {d.rows.slice(0, limit).map((m, i) => (
            <MovementRow key={m.id} m={m} sale={m.sale_id ? salesById.get(m.sale_id) : undefined} first={i === 0} />
          ))}
          {d.rows.length > limit ? (
            <Pressable onPress={() => setLimit((l) => l + 30)} accessibilityRole="button" className="h-12 items-center justify-center border-t border-border">
              <Text className="font-plex-medium text-[14px]">عرض المزيد ({d.rows.length - limit})</Text>
            </Pressable>
          ) : null}
        </ListCard>
      ) : (
        <EmptyState
          icon={SearchX}
          title={filtered ? 'لا حركات تطابق التصفية' : 'لا حركات في هذه الفترة'}
          action={filtered ? 'مسح التصفية' : undefined}
          onAction={() => (setType('all'), setCustomer(null), setRecordedBy(null))}
        />
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

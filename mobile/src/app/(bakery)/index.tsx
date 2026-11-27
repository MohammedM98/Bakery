import { router } from 'expo-router';
import { ArrowLeftRight, Banknote, Bell, ChevronLeft, ShoppingBag, Wheat } from 'lucide-react-native';
import * as React from 'react';
import { Pressable, View } from 'react-native';

import { LineChart } from '@/components/charts';
import { ListCard, Segmented } from '@/components/common';
import { Hero, Surface } from '@/components/glass';
import { MovementRow } from '@/components/movement-row';
import { IconButton, Screen, SectionTitle } from '@/components/screen';
import { useSheets } from '@/components/sheets';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { formatAmount, formatKg, formatMoney } from '@/lib/format';
import { CURRENT, PERIODS, PREVIOUS, type Period } from '@/lib/period';
import { TODAY, useApp } from '@/state/app';
import { dashboard } from '@/state/selectors';

const DAY_NAMES = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

export default function Home() {
  const app = useApp();
  if (!app.can('view-dashboard')) return <QuickHome />;
  return <Dashboard />;
}

function Header() {
  const { user, state, pendingCount } = useApp();
  const day = DAY_NAMES[new Date(`${TODAY}T12:00:00Z`).getUTCDay()];
  return (
    <View className="mb-5 mt-2 flex-row items-center gap-3">
      <Surface level="l2" radius={22} blur={false} style={{ width: 44, height: 44 }} className="items-center justify-center">
        <Text className="font-plex-semi text-[16px]">{user?.name.slice(0, 1)}</Text>
      </Surface>
      <View className="flex-1">
        <Text variant="muted" className="leading-[18px]">
          {day} {TODAY.slice(8, 10)}/{TODAY.slice(5, 7)}
        </Text>
        <Text className="font-plex-semi text-[17px]">{state.bakery.name}</Text>
      </View>
      <IconButton icon={Bell} label={pendingCount ? `التنبيهات، ${pendingCount} عمليات تنتظر` : 'التنبيهات'} onPress={() => router.push('/more/sync')}>
        {pendingCount ? <View className="bg-primary dark:bg-ring absolute end-[11px] top-[10px] h-2 w-2 rounded-full" /> : null}
      </IconButton>
    </View>
  );
}

function Dashboard() {
  const app = useApp();
  const [period, setPeriod] = React.useState<Period>('day');
  const d = React.useMemo(() => dashboard(app.state.movements, app.state.sales, period, TODAY), [app.state.movements, app.state.sales, period]);
  const salesById = React.useMemo(() => new Map(app.state.sales.map((s) => [s.id, s])), [app.state.sales]);

  return (
    <Screen>
      <Header />
      <Segmented label="الفترة" className="mb-4" options={PERIODS} value={period} onChange={setPeriod} />

      <Pressable onPress={() => (app.can('view-sales-log') ? router.push('/log') : undefined)} accessibilityRole="button" accessibilityLabel={`خبز مباع ${CURRENT[period]} ${formatKg(d.breadKg)}`}>
        <Hero className="mb-5 flex-row items-center gap-3.5 p-5">
          <View className="h-11 w-11 items-center justify-center rounded-[14px] bg-white/[0.06]">
            <Icon as={Wheat} size={24} strokeWidth={1.5} className="text-wheat" />
          </View>
          <View className="min-w-0 flex-1">
            <Text className="text-hero-muted text-[13px]">خبز مباع {CURRENT[period]}</Text>
            <Text className="font-plex-semi text-[26px] leading-[34px] text-white">
              {formatAmount(d.breadKg).replace('.00', '')}
              <Text className="text-hero-muted font-plex-medium text-[15px]"> كغ</Text>
            </Text>
            <Text className="text-hero-muted text-[13px]">
              عادي {formatKg(d.cashKg)}، طحين {formatKg(d.flourKg)}
            </Text>
          </View>
          {app.can('view-sales-log') ? (
            <View className="h-10 w-10 items-center justify-center rounded-full bg-white">
              <Icon as={ChevronLeft} size={20} strokeWidth={1.5} className="text-hero" />
            </View>
          ) : null}
        </Hero>
      </Pressable>

      <SectionTitle title="نظرة سريعة" />
      <View className="mb-3 flex-row gap-3">
        <Surface level="l1" radius={22} className="min-h-[168px] flex-1 p-4">
          <Text className="font-plex-medium">المقبوضات</Text>
          <Text className="font-plex-semi mt-2 text-[18px] leading-[26px]">{formatMoney(d.income / 100)}</Text>
          <Text variant="muted" numberOfLines={1}>
            نقدًا <Text className="font-plex-medium text-[13px]">{formatAmount(d.cash / 100).replace('.00', '')}</Text>
            {'   '}تحويل <Text className="font-plex-medium text-[13px]">{formatAmount(d.transfer / 100).replace('.00', '')}</Text>
          </Text>
          <Text variant="muted" className="mt-auto">
            مقابل {formatAmount(d.prevIncome / 100)} {PREVIOUS[period]}
          </Text>
        </Surface>
        <Surface level="l1" radius={22} className="min-h-[168px] flex-1 p-4">
          <Text className="font-plex-medium">المبيعات</Text>
          <Text variant="muted" className="mt-2" numberOfLines={1}>
            <Text className="font-plex-semi text-[18px]">{formatAmount(d.breadKg).replace('.00', '')}</Text> كغ{'   '}
            <Text className="font-plex-semi text-[18px]">{d.saleCount}</Text> عملية
          </Text>
          <View className="mt-auto">
            <LineChart values={d.series.map((x) => x.kg)} tag={formatKg(d.breadKg)} label={`الخبز المباع آخر 7 فترات، ${CURRENT[period]} ${formatKg(d.breadKg)}`} />
          </View>
        </Surface>
      </View>

      {app.can('view-sales') ? (
        <Surface level="l1" radius={22} className="mb-5 flex-row items-center gap-3 p-4">
          <View className="flex-1">
            <Text className="font-plex-medium">غير مدفوع</Text>
            <Text className="text-destructive font-plex-semi text-[18px] leading-[26px]">{formatMoney(d.unpaid / 100)}</Text>
            <Text variant="muted">
              {d.unpaidCount} عمليات، {d.paidRatio}% مدفوع
            </Text>
          </View>
          <Button variant="pill" size="sm" onPress={() => router.push({ pathname: '/more/sales', params: { unpaid: '1' } })}>
            <Text>عرض غير المدفوع</Text>
          </Button>
        </Surface>
      ) : (
        <View className="mb-2" />
      )}

      <SectionTitle title="آخر الحركات" action={app.can('view-sales-log') ? 'عرض الكل' : undefined} onAction={() => router.push('/log')} />
      {d.recent.length ? (
        <ListCard>
          {d.recent.map((m, i) => (
            <MovementRow key={m.id} m={m} sale={m.sale_id ? salesById.get(m.sale_id) : undefined} first={i === 0} />
          ))}
        </ListCard>
      ) : (
        <ListCard className="items-center py-6">
          <Text variant="muted">لا حركات {CURRENT[period]}</Text>
        </ListCard>
      )}
    </Screen>
  );
}

/** Home for staff without "view dashboard": the actions they may do, big and close to the thumb. */
function QuickHome() {
  const app = useApp();
  const sheets = useSheets();
  const actions = [
    app.can('record-sale') && { label: 'تسجيل بيع', icon: ShoppingBag, onPress: () => sheets.open({ kind: 'sale' }) },
    app.can('record-deposit') && { label: 'إيداع طحين', icon: Wheat, onPress: () => sheets.open({ kind: 'deposit', customerId: null }) },
    app.can('record-payment') && app.can('view-customers') && { label: 'دفعة من زبون', icon: Banknote, onPress: () => router.push('/customers') },
    app.can('record-flour-sale') && app.can('view-customers') && { label: 'خبز مقابل طحين', icon: ArrowLeftRight, onPress: () => router.push('/customers') },
  ].filter(Boolean) as { label: string; icon: typeof Wheat; onPress: () => void }[];
  return (
    <Screen>
      <Header />
      <Text variant="section" className="mb-3">
        ماذا تسجّل الآن؟
      </Text>
      <View className="mb-5 flex-row flex-wrap gap-3">
        {actions.map((a) => (
          <Pressable key={a.label} onPress={a.onPress} accessibilityRole="button" className="min-w-[46%] flex-1 active:opacity-80">
            <Surface level="l1" radius={22} className="h-28 justify-between p-4">
              <Icon as={a.icon} size={22} strokeWidth={1.5} className="text-foreground" />
              <Text className="font-plex-medium">{a.label}</Text>
            </Surface>
          </Pressable>
        ))}
      </View>
      <Text variant="muted" className="text-center">
        تعرض اللوحة الكاملة لمن يملك صلاحية «عرض اللوحة الرئيسية»
      </Text>
    </Screen>
  );
}

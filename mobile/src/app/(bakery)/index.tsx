import { router } from 'expo-router';
import { ArrowLeftRight, Banknote, Bell, ChevronLeft, ShoppingBag, Wheat, WifiOff } from 'lucide-react-native';
import * as React from 'react';
import { Pressable, View } from 'react-native';

import { LineChart } from '@/components/charts';
import { EmptyState, ListCard, Notice, Segmented } from '@/components/common';
import { Hero, Surface } from '@/components/glass';
import { LineRow } from '@/components/movement-row';
import { IconButton, Screen, SectionTitle } from '@/components/screen';
import { useSheets } from '@/components/sheets';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import type { MovementType } from '@/api/types';
import { Skeleton } from '@/components/ui/skeleton';
import { formatAmount, formatDate, formatShortDate, formatTime, localTime } from '@/lib/format';
import { CURRENT, PERIODS, PREVIOUS, type Period } from '@/lib/period';
import { today, useApp, useRemote } from '@/state/app';

const DAY_NAMES = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

export default function Home() {
  const app = useApp();
  if (!app.can('view-dashboard')) return <QuickHome />;
  return <Dashboard />;
}

function Header() {
  const { user, me, pendingCount, rejectedCount } = useApp();
  const t = today();
  const day = DAY_NAMES[new Date(`${t}T12:00:00Z`).getUTCDay()];
  const waiting = pendingCount + rejectedCount;
  return (
    <View className="mb-5 mt-2 flex-row items-center gap-3">
      <Surface level="l2" radius={22} blur={false} style={{ width: 44, height: 44 }} className="items-center justify-center">
        <Text className="font-plex-semi text-[16px]">{user?.name.slice(0, 1)}</Text>
      </Surface>
      <View className="flex-1">
        <Text variant="muted" className="leading-[18px]">
          {day} {formatShortDate(t)}
        </Text>
        <Text className="font-plex-semi text-[17px]">{me?.bakery?.name}</Text>
      </View>
      <IconButton icon={Bell} label={waiting ? `التنبيهات، ${waiting} عمليات تنتظر أو رُفضت` : 'التنبيهات'} onPress={() => router.push('/more/sync')}>
        {waiting ? <View className="bg-primary dark:bg-ring absolute end-2.5 top-2.5 h-2 w-2 rounded-full" /> : null}
      </IconButton>
    </View>
  );
}

interface DashboardData {
  rangeLabel: string;
  bread: { kg: string; regular_text: string; flour_text: string; kg_text: string };
  takings: { received_text: string; cash: string; transfer: string; unpaid: string; unpaid_text: string; paidPercent: number | null };
  recent: { number: number; type: MovementType; who: string; detail: string; ledger: 'money' | 'flour'; amount: string; amount_text: string }[];
  chart: { label: string; kg: number; received: number }[];
}

/** The website dashboard's own figures (GET /dashboard); offline, the last answer with its time. */
function Dashboard() {
  const app = useApp();
  const [period, setPeriod] = React.useState<Period>('day');
  const remote = useRemote<DashboardData>(`dashboard?period=${period}`);
  const d = remote.data;
  const kg = (v: string | number) => formatAmount(v).replace('.00', '');

  return (
    <Screen>
      <Header />
      <Segmented label="الفترة" className="mb-4" options={PERIODS} value={period} onChange={setPeriod} />
      {remote.stale ? <Notice tone="warning" icon={WifiOff} body={`أرقام آخر اتصال، ${formatDate(localTime(remote.at))} ${formatTime(remote.at)}. العمليات الجديدة تظهر بعد المزامنة`} /> : null}
      {!d ? (
        remote.error || !app.state.online ? (
          <EmptyState icon={WifiOff} title="لا أرقام على هذا الهاتف بعد" body={remote.error ?? 'اللوحة تُقرأ من الخادم. اتصل بالإنترنت مرة'} action={app.state.online ? 'إعادة المحاولة' : undefined} onAction={remote.reload} />
        ) : (
          <View className="gap-3">
            <Skeleton className="h-28 w-full rounded-[24px]" />
            <Skeleton className="h-40 w-full rounded-[22px]" />
          </View>
        )
      ) : (
        <>
          <Pressable onPress={() => (app.can('view-sales-log') ? router.push('/log') : undefined)} accessibilityRole="button" accessibilityLabel={`خبز مباع ${CURRENT[period]} ${d.bread.kg_text}`}>
            <Hero className="mb-5 flex-row items-center gap-3.5 p-5">
              <View className="h-11 w-11 items-center justify-center rounded-[14px] bg-hero-foreground/[0.06]">
                <Icon as={Wheat} size={24} strokeWidth={1.5} className="text-wheat" />
              </View>
              <View className="min-w-0 flex-1">
                <Text className="text-hero-muted text-[13px]">خبز مباع {CURRENT[period]}</Text>
                <Text className="font-plex-semi text-[26px] leading-[34px] text-hero-foreground">
                  {kg(d.bread.kg)}
                  <Text className="text-hero-muted font-plex-medium text-[15px]"> كغ</Text>
                </Text>
                <Text className="text-hero-muted text-[13px]">
                  عادي {d.bread.regular_text}، طحين {d.bread.flour_text}
                </Text>
              </View>
              {app.can('view-sales-log') ? (
                <View className="h-10 w-10 items-center justify-center rounded-full bg-hero-foreground">
                  <Icon as={ChevronLeft} size={20} strokeWidth={1.5} className="text-hero" />
                </View>
              ) : null}
            </Hero>
          </Pressable>

          <SectionTitle title="نظرة سريعة" />
          <View className="mb-3 flex-row gap-3">
            <Surface level="l1" radius={22} className="min-h-[168px] flex-1 p-4">
              <Text className="font-plex-medium">المقبوضات</Text>
              <Text className="font-plex-semi mt-2 text-[18px] leading-[26px]">{d.takings.received_text}</Text>
              <Text variant="muted" numberOfLines={1}>
                نقدًا <Text className="font-plex-medium text-[13px]">{kg(d.takings.cash)}</Text>
                {'   '}تحويل <Text className="font-plex-medium text-[13px]">{kg(d.takings.transfer)}</Text>
              </Text>
              <Text variant="muted" className="mt-auto">
                مقابل {formatAmount(d.chart.at(-2)?.received ?? 0)} {PREVIOUS[period]}
              </Text>
            </Surface>
            <Surface level="l1" radius={22} className="min-h-[168px] flex-1 p-4">
              <Text className="font-plex-medium">المبيعات</Text>
              <Text variant="muted" className="mt-2" numberOfLines={1}>
                <Text className="font-plex-semi text-[18px]">{kg(d.bread.kg)}</Text> كغ
              </Text>
              <View className="mt-auto">
                <LineChart values={d.chart.map((x) => x.kg)} tag={d.bread.kg_text} label={`الخبز المباع آخر 7 فترات، ${CURRENT[period]} ${d.bread.kg_text}`} />
              </View>
            </Surface>
          </View>

          {app.can('view-sales') ? (
            <Surface level="l1" radius={22} className="mb-5 flex-row items-center gap-3 p-4">
              <View className="flex-1">
                <Text className="font-plex-medium">غير مدفوع</Text>
                <Text className="text-destructive font-plex-semi text-[18px] leading-[26px]">{d.takings.unpaid_text}</Text>
                <Text variant="muted">{d.takings.paidPercent == null ? `لا مبيعات ${CURRENT[period]}` : `${d.takings.paidPercent}% من مبيعات الفترة مدفوع`}</Text>
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
                <LineRow key={m.number} first={i === 0} type={m.type} title={m.who} subtitle={m.detail} amount={m.amount_text} positive={m.type === 'payment'} />
              ))}
            </ListCard>
          ) : (
            <ListCard className="items-center py-6">
              <Text variant="muted">لا حركات {CURRENT[period]}</Text>
            </ListCard>
          )}
        </>
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
              <View className="self-start">
                <Icon as={a.icon} size={22} strokeWidth={1.5} className="text-foreground" />
              </View>
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

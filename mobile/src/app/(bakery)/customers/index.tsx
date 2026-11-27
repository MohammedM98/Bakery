import { router } from 'expo-router';
import { Plus, RefreshCw, Search, SearchX, Users, WifiOff } from 'lucide-react-native';
import * as React from 'react';
import { ScrollView, View } from 'react-native';

import { Avatar, Chip, EmptyState, FlourText, ListItemFrame, MoneyText, Notice, Row } from '@/components/common';
import { Well } from '@/components/glass';
import { ListScreen, NotAllowed, PageHeader, Screen } from '@/components/screen';
import { useSheets } from '@/components/sheets';
import { useToast } from '@/components/toast';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Text } from '@/components/ui/text';
import { ltr } from '@/lib/format';
import type { ApiCustomer } from '@/mock/types';
import { useApp } from '@/state/app';

type Filter = 'all' | 'owe' | 'flour';

export default function Customers() {
  const app = useApp();
  const sheets = useSheets();
  const [q, setQ] = React.useState('');
  const [filter, setFilter] = React.useState<Filter>('all');

  if (!app.can('view-customers')) return <NotAllowed />;
  if (!app.state.hasLocalData) return <NoLocalData />;

  const list = app.state.customers.filter((c) => {
    const b = app.balances.get(c.id);
    if (filter === 'owe' && !(b && b.money > 0)) return false;
    if (filter === 'flour' && !(b && b.flour < 0)) return false;
    const t = q.trim();
    return !t || c.name.includes(t) || (c.phone ?? '').includes(t);
  });
  const count = app.state.customers.length;

  const header = (
    <>
      <PageHeader
        title="الزبائن"
        subtitle={`${count} ${count >= 3 && count <= 10 ? 'زبائن' : 'زبونًا'}`}
        end={
          app.can('create-customers') ? (
            <Button variant="pill" size="sm" onPress={() => sheets.open({ kind: 'customer' })}>
              <Icon as={Plus} size={18} />
              <Text>إضافة</Text>
            </Button>
          ) : null
        }
      />
      <View className="relative mb-3">
        <Input value={q} onChangeText={setQ} placeholder="بحث بالاسم أو رقم الجوال" accessibilityLabel="بحث بالاسم أو رقم الجوال" className="px-11" returnKeyType="search" />
        <View className="absolute inset-y-0 start-4 justify-center" pointerEvents="none">
          <Icon as={Search} size={20} strokeWidth={1.5} className="text-muted-foreground" />
        </View>
      </View>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} className="-mx-5 mb-4" contentContainerStyle={{ paddingHorizontal: 20, gap: 8 }}>
        <Chip label="الكل" on={filter === 'all'} onPress={() => setFilter('all')} />
        <Chip label="عليهم مال" on={filter === 'owe'} onPress={() => setFilter('owe')} />
        <Chip label="لهم طحين" on={filter === 'flour'} onPress={() => setFilter('flour')} />
      </ScrollView>
    </>
  );

  return (
    <ListScreen<ApiCustomer>
      header={header}
      data={list}
      keyExtractor={(c) => String(c.id)}
      renderItem={(c, i) => {
        const b = app.balances.get(c.id);
        return (
          <ListItemFrame first={i === 0} last={i === list.length - 1}>
            <Row
              first={i === 0}
              onPress={() => router.push(`/customers/${c.id}`)}
              start={<Avatar name={c.name} />}
              title={c.name}
              subtitle={c.phone ? ltr(c.phone) : 'بلا رقم'}
              end={<MoneyText cents={b?.money ?? 0} />}
              endSub={<FlourText hundredths={b?.flour ?? 0} className="text-[13px]" />}
            />
          </ListItemFrame>
        );
      }}
      empty={
        count === 0 ? (
          <EmptyState icon={Users} title="لا يوجد زبائن بعد" body="أضف أول زبون لتسجيل مبيعاته وأرصدته" action={app.can('create-customers') ? 'إضافة زبون' : undefined} actionIcon={Plus} onAction={() => sheets.open({ kind: 'customer' })} />
        ) : (
          <EmptyState icon={SearchX} title="لا نتائج" body="جرّب اسمًا آخر أو امسح البحث" action="مسح البحث" onAction={() => (setQ(''), setFilter('all'))} />
        )
      }
    />
  );
}

/** First launch on a new phone with no connection: nothing to show until one sync (option A). */
function NoLocalData() {
  const app = useApp();
  const toast = useToast();
  return (
    <Screen>
      <PageHeader title="الزبائن" />
      <EmptyState
        icon={WifiOff}
        title="لا توجد بيانات على هذا الجهاز بعد"
        body="اتصل بالإنترنت مرة واحدة لتحميل الزبائن والأرصدة، ثم يعمل التطبيق دون اتصال"
        action="إعادة المحاولة"
        actionIcon={RefreshCw}
        onAction={() => {
          const r = app.syncNow();
          if (!r.ok) toast({ kind: 'error', title: 'ما زال الجهاز دون إنترنت', body: 'يمكنك تسجيل بيع لمشترٍ عابر الآن' });
        }}
      />
      <Notice tone="warning" icon={WifiOff} title="بانتظار أول مزامنة" body="يمكنك تسجيل بيع لمشترٍ عابر الآن" />
      <Well className="gap-4 p-4">
        {[0, 1, 2].map((i) => (
          <View key={i} className="flex-row items-center gap-3">
            <Skeleton className="h-10 w-10 rounded-full" />
            <View className="flex-1 gap-2">
              <Skeleton className="h-3 w-1/2" />
              <Skeleton className="h-3 w-1/3" />
            </View>
          </View>
        ))}
      </Well>
    </Screen>
  );
}

import { router } from 'expo-router';
import { Plus, Search, SearchX, Store, WifiOff } from 'lucide-react-native';
import * as React from 'react';
import { View } from 'react-native';

import { Badge, EmptyState, ListCard, Notice, RoundIcon, Row } from '@/components/common';
import { Surface } from '@/components/glass';
import { PageHeader, Screen } from '@/components/screen';
import { useSheets } from '@/components/sheets';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { Text } from '@/components/ui/text';
import { subscriptionStatus } from '@/lib/subscription';
import type { ApiBakery, Paged } from '@/api/types';
import { useApp, useRemote } from '@/state/app';

/** Platform owner: bakeries with their subscription state (option A). */
export default function Bakeries() {
  const app = useApp();
  const sheets = useSheets();
  const [q, setQ] = React.useState('');
  const remote = useRemote<Paged<ApiBakery>>('admin/bakeries');
  // The API pages by 15: fetch the rest so the list and the search cover every bakery.
  const [rest, setRest] = React.useState<{ first: ApiBakery[]; rows: ApiBakery[] } | null>(null);
  const first = remote.data?.data;
  const last = remote.data?.meta.last_page ?? 1;
  const { call } = app;
  React.useEffect(() => {
    if (!first || last < 2) return;
    let live = true;
    Promise.all(Array.from({ length: last - 1 }, (_, i) => call<Paged<ApiBakery>>('GET', `admin/bakeries?page=${i + 2}`))).then((pages) => {
      if (live) setRest({ first, rows: pages.flatMap((r) => (r.ok ? r.data!.data : [])) });
    });
    return () => {
      live = false;
    };
  }, [first, last, call]);
  const all = [...(first ?? []), ...(rest && rest.first === first ? rest.rows : [])];
  const list = all.filter((b) => !q.trim() || b.name.includes(q.trim()));
  const n = remote.data?.meta.total ?? 0;
  return (
    <Screen net={false}>
      <PageHeader
        title="المخابز"
        end={
          <Surface level="l2" radius={999} blur={false} className="h-9 justify-center px-3">
            <Text className="text-[13px]">{n >= 3 && n <= 10 ? `${n} مخابز` : `${n} مخبزًا`}</Text>
          </Surface>
        }
      />
      {!app.state.online ? <Notice tone="warning" icon={WifiOff} title="يحتاج إنترنت" body="إدارة المخابز والاشتراكات تتم بالإنترنت فقط" /> : null}
      <View className="relative mb-4">
        <Input value={q} onChangeText={setQ} placeholder="ابحث باسم المخبز" accessibilityLabel="ابحث باسم المخبز" className="px-11" />
        <View className="absolute inset-y-0 start-4 justify-center" pointerEvents="none">
          <Icon as={Search} size={20} strokeWidth={1.5} className="text-muted-foreground" />
        </View>
      </View>
      {list.length ? (
        <ListCard>
          {list.map((b, i) => {
            const s = subscriptionStatus(b.subscription);
            return (
              <Row
                key={b.id}
                first={i === 0}
                onPress={() => router.push(`/bakeries/${b.id}`)}
                start={<RoundIcon icon={Store} />}
                title={b.name}
                subtitle={s.detail}
                end={<Badge label={s.label} tone={s.tone} />}
              />
            );
          })}
        </ListCard>
      ) : !remote.data ? (
        <EmptyState icon={WifiOff} title={remote.error ?? (app.state.online ? 'جارٍ التحميل' : 'المخابز تحتاج إنترنت')} />
      ) : n ? (
        <EmptyState icon={SearchX} title="لا نتائج" action="مسح البحث" onAction={() => setQ('')} />
      ) : (
        <EmptyState icon={Store} title="لا مخابز بعد" body="أضف أول مخبز وحساب صاحبه" action="إضافة مخبز" actionIcon={Plus} onAction={() => sheets.open({ kind: 'bakery' })} />
      )}
    </Screen>
  );
}

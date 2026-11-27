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
import { TODAY, useApp } from '@/state/app';

/** Platform owner: bakeries with their subscription state (option A). */
export default function Bakeries() {
  const app = useApp();
  const sheets = useSheets();
  const [q, setQ] = React.useState('');
  const list = app.state.bakeries.filter((b) => !q.trim() || b.name.includes(q.trim()));
  const n = app.state.bakeries.length;
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
            const s = subscriptionStatus(b, TODAY);
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
      ) : n ? (
        <EmptyState icon={SearchX} title="لا نتائج" action="مسح البحث" onAction={() => setQ('')} />
      ) : (
        <EmptyState icon={Store} title="لا مخابز بعد" body="أضف أول مخبز وحساب صاحبه" action="إضافة مخبز" actionIcon={Plus} onAction={() => sheets.open({ kind: 'bakery' })} />
      )}
    </Screen>
  );
}

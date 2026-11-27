import { useLocalSearchParams } from 'expo-router';
import { RefreshCw, Store } from 'lucide-react-native';
import * as React from 'react';

import { EmptyState, InfoList } from '@/components/common';
import { useConfirm } from '@/components/confirm';
import { Hero } from '@/components/glass';
import { PageHeader, Screen } from '@/components/screen';
import { useSheets } from '@/components/sheets';
import { useToast } from '@/components/toast';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { formatDate, formatMoney } from '@/lib/format';
import { subscriptionStatus } from '@/lib/subscription';
import { TODAY, useApp } from '@/state/app';

/** One bakery: subscription in the hero, owner details, renew (sheet) and stop (confirm). */
export default function BakeryPage() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const app = useApp();
  const sheets = useSheets();
  const confirm = useConfirm();
  const toast = useToast();
  const b = app.state.bakeries.find((x) => String(x.id) === id);
  if (!b)
    return (
      <Screen net={false}>
        <PageHeader back title="المخبز" />
        <EmptyState icon={Store} title="المخبز غير موجود" />
      </Screen>
    );
  const s = subscriptionStatus(b, TODAY);
  const online = app.state.online;
  return (
    <Screen net={false}>
      <PageHeader back title={b.name} />
      <Hero className="mb-4 p-5">
        <Text className="text-hero-muted text-[13px]">الاشتراك</Text>
        <Text className="font-plex-semi text-[22px] leading-[32px] text-white">
          {s.label === 'فعّال' || s.label === 'ينتهي قريبًا' ? `فعّال حتى ${formatDate(b.subscription_ends_at)}` : s.label === 'منتهٍ' ? `انتهى في ${formatDate(b.subscription_ends_at)}` : 'موقوف'}
        </Text>
        <Text className="text-hero-muted text-[13px]">{s.detail}</Text>
      </Hero>
      <InfoList
        items={[
          ['صاحب المخبز', b.owner_name],
          ['البريد', b.owner_email],
          ['العنوان', b.address ?? '—'],
          ['الموظفون', String(b.staff_count)],
          ['سعر الكيلو', formatMoney(b.price_per_kg)],
        ]}
      />
      <Button className="mt-4 w-full" disabled={!online} onPress={() => sheets.open({ kind: 'renew', bakeryId: b.id })}>
        <Icon as={RefreshCw} size={18} />
        <Text>تجديد الاشتراك</Text>
      </Button>
      <Button
        variant={b.is_active ? 'danger' : 'glass'}
        className="mt-2 w-full"
        disabled={!online}
        onPress={() =>
          b.is_active
            ? confirm({
                title: `إيقاف اشتراك ${b.name}؟`,
                body: 'لن يستطيع صاحب المخبز وموظفوه الدخول حتى تعيد التفعيل.',
                action: 'إيقاف',
                destructive: true,
                onConfirm: () => {
                  app.setBakeryActive(b.id, false);
                  toast({ kind: 'success', title: 'تم إيقاف الاشتراك' });
                },
              })
            : (app.setBakeryActive(b.id, true), toast({ kind: 'success', title: 'تم تفعيل الاشتراك' }))
        }>
        <Text>{b.is_active ? 'إيقاف الاشتراك' : 'تفعيل الاشتراك'}</Text>
      </Button>
      {!online ? (
        <Text variant="muted" className="mt-2 text-center">
          التجديد والإيقاف يحتاجان إنترنت
        </Text>
      ) : null}
    </Screen>
  );
}

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
import { formatDate, ltr } from '@/lib/format';
import { subscriptionStatus } from '@/lib/subscription';
import type { ApiBakery } from '@/api/types';
import { useApp, useRemote } from '@/state/app';

/** One bakery: subscription in the hero, owner details, renew (sheet) and stop (confirm). */
export default function BakeryPage() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const app = useApp();
  const sheets = useSheets();
  const confirm = useConfirm();
  const toast = useToast();
  const remote = useRemote<{ bakery: ApiBakery }>(`admin/bakeries/${id}`);
  const b = remote.data?.bakery;
  if (!b)
    return (
      <Screen net={false}>
        <PageHeader back title="المخبز" />
        <EmptyState icon={Store} title={remote.error ?? (app.state.online ? 'جارٍ التحميل' : 'يحتاج إنترنت')} />
      </Screen>
    );
  const s = subscriptionStatus(b.subscription);
  const active = b.subscription.status === 'active';
  const ends = b.subscription.expires_at;
  const setStatus = async (status: 'active' | 'inactive') => {
    const r = await app.online('POST', `admin/bakeries/${b.id}/status`, { status });
    if (!r.ok) return toast({ kind: 'error', title: r.error });
    remote.reload();
    toast({ kind: 'success', title: status === 'active' ? 'تم تفعيل الاشتراك' : 'تم إيقاف الاشتراك' });
  };
  const online = app.state.online;
  return (
    <Screen net={false}>
      <PageHeader back title={b.name} />
      <Hero className="mb-4 p-5">
        <Text className="text-hero-muted text-[13px]">الاشتراك</Text>
        <Text className="font-plex-semi text-[22px] leading-[32px] text-hero-foreground">
          {s.label === 'فعّال' || s.label === 'ينتهي قريبًا' ? (ends ? `فعّال حتى ${formatDate(ends)}` : 'فعّال') : s.label === 'منتهٍ' ? `انتهى في ${formatDate(ends)}` : 'موقوف'}
        </Text>
        <Text className="text-hero-muted text-[13px]">{s.detail}</Text>
      </Hero>
      <InfoList
        items={[
          ['صاحب المخبز', b.owner?.name ?? '—'],
          ['البريد', b.owner ? ltr(b.owner.email) : '—'],
          ['الهاتف', b.phone ? ltr(b.phone) : '—'],
          ['العنوان', b.address ?? '—'],
        ]}
      />
      <Button className="mt-4 w-full" disabled={!online} onPress={() => sheets.open({ kind: 'renew', bakery: b })}>
        <Icon as={RefreshCw} size={18} />
        <Text>تجديد الاشتراك</Text>
      </Button>
      <Button
        variant={active ? 'danger' : 'glass'}
        className="mt-2 w-full"
        disabled={!online}
        onPress={() =>
          active
            ? confirm({
                title: `إيقاف اشتراك ${b.name}؟`,
                body: 'لن يستطيع صاحب المخبز وموظفوه الدخول حتى تعيد التفعيل.',
                action: 'إيقاف',
                destructive: true,
                onConfirm: () => setStatus('inactive'),
              })
            : setStatus('active')
        }>
        <Text>{active ? 'إيقاف الاشتراك' : 'تفعيل الاشتراك'}</Text>
      </Button>
      {!online ? (
        <Text variant="muted" className="mt-2 text-center">
          التجديد والإيقاف يحتاجان إنترنت
        </Text>
      ) : null}
    </Screen>
  );
}

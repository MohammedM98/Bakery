import { ArrowLeftRight, Banknote, CircleCheck, Printer, RefreshCw, RotateCcw, Scale, ShoppingBag, UserPlus, Wheat, X } from 'lucide-react-native';
import * as React from 'react';
import { Pressable, View } from 'react-native';

import { ListCard, Notice, PendingMark, RoundIcon, Row } from '@/components/common';
import { useConfirm } from '@/components/confirm';
import { usePrint } from '@/components/sheets';
import { Surface } from '@/components/glass';
import { PageHeader, Screen } from '@/components/screen';
import { useToast } from '@/components/toast';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { formatDate, formatTime, localTime } from '@/lib/format';
import type { OfflineType, QueuedOperation } from '@/api/types';
import { useApp } from '@/state/app';

const ICONS: Record<OfflineType, typeof Wheat> = {
  sale: ShoppingBag,
  flour_sale: ArrowLeftRight,
  deposit: Wheat,
  payment: Banknote,
  adjustment: Scale,
  'customer.create': UserPlus,
};

/**
 * Sync (CLAUDE.md section 13): what waits on this phone, what was sent, and what the server refused,
 * with its reason. A refused operation is never dropped silently: the user dismisses it.
 */
export default function Sync() {
  const app = useApp();
  const toast = useToast();
  const confirm = useConfirm();
  const print = usePrint();
  const pending = app.state.queue.filter((q) => q.status === 'pending');
  const rejected = app.state.queue.filter((q) => q.status === 'rejected');
  const sent = app.state.queue.filter((q) => q.status === 'sent').slice(-8).reverse();
  const online = app.state.online;

  const sync = async () => {
    const r = await app.syncNow();
    if (!r.ok) return toast({ kind: 'error', title: 'تعذّرت المزامنة', body: r.error.text });
    toast(
      r.rejected
        ? { kind: 'error', title: `رفض الخادم ${r.rejected} عمليات`, body: 'السبب مكتوب تحت كل عملية' }
        : { kind: 'success', title: r.sent ? `أُرسلت ${r.sent} عمليات` : 'كل شيء مُرسل', body: 'الأرصدة الآن من الخادم' },
    );
  };

  const remove = (q: QueuedOperation) =>
    confirm({
      title: `إزالة «${q.title}»؟`,
      body: 'رفضها الخادم ولن تُرسل. تُزال من هذا الهاتف مع أثرها على الرصيد المؤقت.',
      action: 'إزالة',
      destructive: true,
      onConfirm: () => app.dismissRejected(q.client_uuid),
    });

  const iconButton = (label: string, icon: typeof X, onPress: () => void) => (
    <Pressable onPress={onPress} accessibilityRole="button" accessibilityLabel={label} className="h-11 w-11 items-center justify-center">
      <Icon as={icon} size={18} strokeWidth={1.5} className="text-muted-foreground" />
    </Pressable>
  );

  const list = (title: string, items: QueuedOperation[], kind: 'pending' | 'rejected' | 'sent') =>
    items.length ? (
      <>
        <Text className="font-plex-semi mb-3 text-[17px]">{title}</Text>
        <ListCard className="mb-5">
          {items.map((q, i) => (
            <Row
              key={q.client_uuid}
              first={i === 0}
              start={<RoundIcon icon={ICONS[q.type]} tone={kind === 'rejected' ? 'danger' : kind === 'sent' ? 'success' : 'neutral'} />}
              title={q.title}
              subtitle={
                kind === 'rejected' ? (
                  <Text className="text-destructive text-[13px]">{q.reason}</Text>
                ) : kind === 'sent' ? (
                  `${q.subtitle}${q.result?.movements?.[0]?.number ? `، رقم ${q.result.movements[0].number}` : ''}`
                ) : (
                  `${q.subtitle}، ${formatTime(q.client_created_at)}`
                )
              }
              end={
                kind === 'pending' ? (
                  <PendingMark label="ينتظر" />
                ) : kind === 'rejected' ? (
                  <View className="flex-row">
                    {online ? iconButton('إعادة إرسال العملية', RotateCcw, () => app.retryRejected(q.client_uuid)) : null}
                    {iconButton('إزالة العملية المرفوضة', X, () => remove(q))}
                  </View>
                ) : online && q.result?.movements?.length ? (
                  iconButton('طباعة الإيصال', Printer, () => print(q.result!))
                ) : (
                  <Icon as={CircleCheck} size={18} strokeWidth={1.5} className="text-success" />
                )
              }
            />
          ))}
        </ListCard>
      </>
    ) : null;

  return (
    <Screen>
      <PageHeader back title="المزامنة" />
      <Surface level="l1" radius={24} className="mb-4 items-center p-5">
        <Text variant="muted">عمليات تنتظر الإرسال</Text>
        <Text className="font-plex-semi text-[40px] leading-[52px]" accessibilityRole="text" accessibilityLiveRegion="polite">
          {pending.length}
        </Text>
        <Text variant="muted">{app.state.lastSyncAt ? `آخر مزامنة ${formatDate(localTime(app.state.lastSyncAt))} ${formatTime(app.state.lastSyncAt)}` : 'لم تتم مزامنة بعد'}</Text>
        <Button variant="pill" size="sm" className="mt-3" disabled={!online || app.state.syncing} onPress={sync}>
          <Icon as={RefreshCw} size={16} />
          <Text>{app.state.syncing ? 'جارٍ المزامنة' : 'مزامنة الآن'}</Text>
        </Button>
      </Surface>
      {!online ? <Notice tone="warning" title="دون إنترنت" body="العمليات محفوظة على الهاتف، وتُرسل تلقائيًا عند الاتصال. الخادم يعطيها أرقامها ويتحقق منها" /> : null}
      {list('رفضها الخادم', rejected, 'rejected')}
      {list('تنتظر', pending, 'pending')}
      {list('أُرسلت مؤخرًا', sent, 'sent')}
    </Screen>
  );
}

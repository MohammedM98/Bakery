import { ArrowLeftRight, Banknote, CircleCheck, RefreshCw, Scale, ShoppingBag, UserPlus, Wheat, X } from 'lucide-react-native';
import * as React from 'react';
import { Pressable } from 'react-native';

import { ListCard, Notice, PendingMark, RoundIcon, Row } from '@/components/common';
import { Surface } from '@/components/glass';
import { PageHeader, Screen } from '@/components/screen';
import { useToast } from '@/components/toast';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { formatTime } from '@/lib/format';
import type { OperationKind, QueuedOperation } from '@/mock/types';
import { useApp } from '@/state/app';

const ICONS: Record<OperationKind, typeof Wheat> = {
  sale: ShoppingBag,
  flour_sale: ArrowLeftRight,
  deposit: Wheat,
  payment: Banknote,
  adjustment: Scale,
  customer: UserPlus,
};

/**
 * Sync (CLAUDE.md section 13): what waits on this phone, what was sent, and what the server refused,
 * with its reason. A refused operation is never dropped silently: the user dismisses it.
 */
export default function Sync() {
  const app = useApp();
  const toast = useToast();
  const pending = app.state.queue.filter((q) => q.status === 'pending');
  const rejected = app.state.queue.filter((q) => q.status === 'rejected');
  const sent = app.state.queue.filter((q) => q.status === 'sent').slice(0, 8);

  const sync = () => {
    const r = app.syncNow();
    if (!r.ok) return toast({ kind: 'error', title: 'تعذّرت المزامنة', body: r.error });
    toast({ kind: 'success', title: r.count ? `تمت مزامنة ${r.count} عمليات` : 'كل شيء مُرسل', body: 'آخر مزامنة الآن' });
  };

  const list = (title: string, items: QueuedOperation[], kind: 'pending' | 'rejected' | 'sent') =>
    items.length ? (
      <>
        <Text className="font-plex-semi mb-3 text-[17px]">{title}</Text>
        <ListCard className="mb-5">
          {items.map((q, i) => (
            <Row
              key={q.client_id}
              first={i === 0}
              start={<RoundIcon icon={ICONS[q.kind]} tone={kind === 'rejected' ? 'danger' : kind === 'sent' ? 'success' : 'neutral'} />}
              title={q.title}
              subtitle={kind === 'rejected' ? <Text className="text-destructive text-[13px]">{q.reason}</Text> : `${q.subtitle}، ${formatTime(q.created_at)}`}
              end={
                kind === 'pending' ? (
                  <PendingMark label="ينتظر" />
                ) : kind === 'rejected' ? (
                  <Pressable onPress={() => app.dismissRejected(q.client_id)} accessibilityRole="button" accessibilityLabel="إخفاء العملية المرفوضة" className="h-11 w-11 items-center justify-center">
                    <Icon as={X} size={18} strokeWidth={1.5} className="text-muted-foreground" />
                  </Pressable>
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
        <Text className="font-plex-semi text-[40px] leading-[52px]">{pending.length}</Text>
        <Text variant="muted">{app.state.online ? `آخر مزامنة ${formatTime(app.state.lastSyncAt)}` : 'تُرسل تلقائيًا عند الاتصال'}</Text>
        <Button variant="pill" size="sm" className="mt-3" disabled={!app.state.online} onPress={sync}>
          <Icon as={RefreshCw} size={16} />
          <Text>مزامنة الآن</Text>
        </Button>
      </Surface>
      {!app.state.online ? <Notice tone="warning" title="دون إنترنت" body="العمليات محفوظة على الهاتف. الخادم يعطيها أرقامها ويتحقق منها عند الإرسال" /> : null}
      {list('تنتظر', pending, 'pending')}
      {list('رفضها الخادم', rejected, 'rejected')}
      {list('أُرسلت مؤخرًا', sent, 'sent')}
    </Screen>
  );
}

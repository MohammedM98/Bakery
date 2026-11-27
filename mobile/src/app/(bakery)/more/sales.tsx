import { useLocalSearchParams } from 'expo-router';
import { Plus, ShoppingBag, WifiOff } from 'lucide-react-native';
import * as React from 'react';
import { View } from 'react-native';

import { Badge, Chip, EmptyState, ListItemFrame, Notice, RoundIcon, Row, Segmented, TYPE_ICONS } from '@/components/common';
import { useConfirm } from '@/components/confirm';
import { ListScreen, NotAllowed, PageHeader } from '@/components/screen';
import { useSheets } from '@/components/sheets';
import { useToast } from '@/components/toast';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { formatDate, formatShortDate, formatTime, localTime } from '@/lib/format';
import type { ApiSale, Paged } from '@/api/types';
import { useApp, useRemote } from '@/state/app';

/** Sales (website: المبيعات, GET /sales): two tabs, regular and for flour, with payment status and "confirm payment" (online). */
export default function Sales() {
  const app = useApp();
  const sheets = useSheets();
  const confirm = useConfirm();
  const toast = useToast();
  const params = useLocalSearchParams<{ unpaid?: string }>();
  const [type, setType] = React.useState<ApiSale['sale_type']>('cash');
  const [unpaidOnly, setUnpaidOnly] = React.useState(params.unpaid === '1');
  const remote = useRemote<{ sales: Paged<ApiSale> }>(app.can('view-sales') ? `sales?type=${type}${unpaidOnly ? '&status=unpaid' : ''}` : null);
  if (!app.can('view-sales')) return <NotAllowed />;

  const list = remote.data?.sales.data ?? [];
  const online = app.state.online;

  const markPaid = (s: ApiSale) =>
    confirm({
      title: 'تأكيد دفع العملية؟',
      body: `يُسجَّل دفع ${s.remaining_text} نقدًا بتاريخ اليوم`,
      action: 'تأكيد الدفع',
      onConfirm: async () => {
        const r = await app.online('POST', `sales/${s.id}/mark-paid`, {});
        toast(r.ok ? { kind: 'success', title: 'تم تأكيد الدفع' } : { kind: 'error', title: r.error });
        if (r.ok) remote.reload();
      },
    });

  const header = (
    <>
      <PageHeader
        back
        title="المبيعات"
        end={
          app.can('record-sale') ? (
            <Button variant="pill" size="sm" onPress={() => sheets.open({ kind: 'sale' })}>
              <Icon as={Plus} size={18} />
              <Text>بيع</Text>
            </Button>
          ) : null
        }
      />
      <Segmented
        label="نوع البيع"
        className="mb-3"
        value={type}
        onChange={setType}
        options={[
          ['cash', 'بيع عادي'],
          ['flour_exchange', 'مقابل طحين'],
        ]}
      />
      <View className="mb-4 flex-row gap-2">
        <Chip label="الكل" on={!unpaidOnly} onPress={() => setUnpaidOnly(false)} />
        <Chip label="غير مدفوع" on={unpaidOnly} onPress={() => setUnpaidOnly(true)} />
      </View>
      {remote.stale ? <Notice tone="warning" icon={WifiOff} body={`القائمة كما كانت عند آخر اتصال، ${formatDate(localTime(remote.at))} ${formatTime(remote.at)}. تأكيد الدفع يحتاج إنترنت`} /> : null}
    </>
  );

  return (
    <ListScreen<ApiSale>
      header={header}
      data={list}
      keyExtractor={(s) => String(s.id)}
      renderItem={(s, i) => {
        const unpaid = s.payment_status === 'unpaid';
        return (
          <ListItemFrame first={i === 0} last={i === list.length - 1}>
            <Row
              first={i === 0}
              start={<RoundIcon icon={TYPE_ICONS[s.sale_type === 'flour_exchange' ? 'flour_withdrawal' : 'sale']} />}
              title={s.buyer}
              subtitle={`${s.kg_text}، ${formatShortDate(s.sale_date)}`}
              end={<Text className="font-plex-medium">{s.total_text}</Text>}
              endSub={<Badge label={s.payment_status_label} tone={unpaid ? 'danger' : 'success'} />}
              onPress={unpaid && online && app.can('record-payment') ? () => markPaid(s) : undefined}
              accessibilityLabel={unpaid ? `${s.buyer}، ${s.total_text}، تأكيد الدفع` : undefined}
            />
          </ListItemFrame>
        );
      }}
      footer={
        list.some((s) => s.payment_status === 'unpaid') && online && app.can('record-payment') ? (
          <Text variant="muted" className="mt-3 text-center">
            المس أي عملية غير مدفوعة لتأكيد دفعها
          </Text>
        ) : remote.data && remote.data.sales.meta.total > list.length ? (
          <Text variant="muted" className="mt-3 text-center">
            تظهر آخر {list.length} عملية من {remote.data.sales.meta.total}
          </Text>
        ) : undefined
      }
      empty={
        !remote.data ? (
          <EmptyState icon={WifiOff} title={online ? (remote.error ?? 'جارٍ التحميل') : 'المبيعات تحتاج إنترنت'} action={online && remote.error ? 'إعادة المحاولة' : undefined} onAction={remote.reload} />
        ) : (
          <EmptyState icon={ShoppingBag} title={unpaidOnly ? 'لا عمليات غير مدفوعة' : 'لا مبيعات بعد'} action={app.can('record-sale') ? 'تسجيل عملية بيع' : undefined} actionIcon={Plus} onAction={() => sheets.open({ kind: 'sale' })} />
        )
      }
    />
  );
}

import { useLocalSearchParams } from 'expo-router';
import { Plus, ShoppingBag } from 'lucide-react-native';
import * as React from 'react';
import { View } from 'react-native';

import { Badge, Chip, EmptyState, ListItemFrame, PendingMark, RoundIcon, Row, Segmented, TYPE_ICONS } from '@/components/common';
import { useConfirm } from '@/components/confirm';
import { ListScreen, NotAllowed, PageHeader } from '@/components/screen';
import { useSheets } from '@/components/sheets';
import { useToast } from '@/components/toast';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { formatDate, formatKg, formatMoney, formatTime, fromCents, toCents } from '@/lib/format';
import type { ApiSale, SaleType } from '@/mock/types';
import { useApp } from '@/state/app';

/** Sales (website: المبيعات): two tabs, regular and for flour, with payment status and "confirm payment". */
export default function Sales() {
  const app = useApp();
  const sheets = useSheets();
  const confirm = useConfirm();
  const toast = useToast();
  const params = useLocalSearchParams<{ unpaid?: string }>();
  const [type, setType] = React.useState<SaleType>('cash');
  const [unpaidOnly, setUnpaidOnly] = React.useState(params.unpaid === '1');
  if (!app.can('view-sales')) return <NotAllowed />;

  const list = app.state.sales
    .filter((s) => s.sale_type === type && (!unpaidOnly || toCents(s.paid_amount) < toCents(s.total_amount)))
    .slice()
    .sort((a, b) => (a.created_at < b.created_at ? 1 : -1))
    .slice(0, 200);
  const pendingIds = new Set(app.state.movements.filter((m) => m.number == null && m.sale_id).map((m) => m.sale_id));

  const markPaid = (s: ApiSale) =>
    confirm({
      title: 'تأكيد دفع العملية؟',
      body: `يُسجَّل دفع ${formatMoney(fromCents(toCents(s.total_amount) - toCents(s.paid_amount)))} نقدًا بتاريخ اليوم`,
      action: 'تأكيد الدفع',
      onConfirm: () => {
        const r = app.markSalePaid(s.id);
        toast(r.ok ? { kind: 'success', title: 'تم تأكيد الدفع' } : { kind: 'error', title: r.error });
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
          ['flour', 'مقابل طحين'],
        ]}
      />
      <View className="mb-4 flex-row gap-2">
        <Chip label="الكل" on={!unpaidOnly} onPress={() => setUnpaidOnly(false)} />
        <Chip label="غير مدفوع" on={unpaidOnly} onPress={() => setUnpaidOnly(true)} />
      </View>
    </>
  );

  return (
    <ListScreen<ApiSale>
      header={header}
      data={list}
      keyExtractor={(s) => String(s.id)}
      renderItem={(s, i) => {
        const rest = toCents(s.total_amount) - toCents(s.paid_amount);
        const pending = pendingIds.has(s.id);
        return (
          <ListItemFrame first={i === 0} last={i === list.length - 1}>
            <Row
              first={i === 0}
              start={<RoundIcon icon={TYPE_ICONS[s.sale_type === 'flour' ? 'flour_withdrawal' : 'sale']} />}
              title={s.customer_name ?? s.buyer_name ?? 'مشترٍ عابر'}
              subtitle={pending ? <PendingMark label={`${formatKg(s.kg_amount)}، ينتظر المزامنة`} /> : `${formatKg(s.kg_amount)}، ${formatDate(s.sale_date).slice(0, 5)} ${formatTime(s.created_at)}`}
              end={<Text className="font-plex-medium">{formatMoney(s.total_amount)}</Text>}
              endSub={rest > 0 ? <Badge label="غير مدفوع" tone="danger" /> : <Badge label="مدفوع" tone="success" />}
              onPress={rest > 0 && app.can('record-payment') ? () => markPaid(s) : undefined}
              accessibilityLabel={rest > 0 ? 'تأكيد الدفع' : undefined}
            />
          </ListItemFrame>
        );
      }}
      footer={
        list.some((s) => toCents(s.paid_amount) < toCents(s.total_amount)) && app.can('record-payment') ? (
          <Text variant="muted" className="mt-3 text-center">
            المس أي عملية غير مدفوعة لتأكيد دفعها
          </Text>
        ) : undefined
      }
      empty={<EmptyState icon={ShoppingBag} title={unpaidOnly ? 'لا عمليات غير مدفوعة' : 'لا مبيعات بعد'} action={app.can('record-sale') ? 'تسجيل عملية بيع' : undefined} actionIcon={Plus} onAction={() => sheets.open({ kind: 'sale' })} />}
    />
  );
}

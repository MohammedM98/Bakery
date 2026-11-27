import * as React from 'react';

import { Field, InfoList, Notice, Segmented, UnitInput } from '@/components/common';
import { NotAllowed, PageHeader, Screen } from '@/components/screen';
import { useToast } from '@/components/toast';
import { Button } from '@/components/ui/button';
import { Text } from '@/components/ui/text';
import { formatTime } from '@/lib/format';
import { useApp } from '@/state/app';
import { useTheme, type SchemePreference } from '@/state/theme';

/** Prices change online only, so every phone sells at the same price (CLAUDE.md section 13). */
export default function Settings() {
  const app = useApp();
  const toast = useToast();
  const { preference, setPreference } = useTheme();
  const [price, setPrice] = React.useState(app.state.bakery.price_per_kg);
  const [fee, setFee] = React.useState(app.state.bakery.flour_exchange_fee_per_kg);
  const [error, setError] = React.useState<string | null>(null);
  if (!app.can('manage-settings')) return <NotAllowed />;
  const online = app.state.online;

  return (
    <Screen>
      <PageHeader back title="الإعدادات" />
      {!online ? <Notice tone="warning" title="يحتاج إنترنت" body="يمكنك رؤية الأسعار الحالية، وتغييرها عند الاتصال" /> : null}
      <Field label="سعر كيلو الخبز" error={error}>
        <UnitInput label="سعر كيلو الخبز" unit="شيكل" value={price} onChangeText={(v) => (setPrice(v), setError(null))} invalid={!!error} />
      </Field>
      <Field label="رسم تبديل الطحين للكيلو" hint="يُضاف على الحساب المالي عند كل بيع مقابل طحين">
        <UnitInput label="رسم تبديل الطحين للكيلو" unit="شيكل" value={fee} onChangeText={setFee} />
      </Field>
      <Notice body="البيع المسجّل دون إنترنت يُحسب بآخر سعر وصل للهاتف" />
      <Button
        className="mb-6 w-full"
        disabled={!online}
        onPress={() => {
          const r = app.saveSettings(price, fee);
          if (!r.ok) return setError(r.error);
          toast({ kind: 'success', title: 'تم حفظ الإعدادات' });
        }}>
        <Text>حفظ</Text>
      </Button>

      <Text variant="muted" className="mb-2">
        التطبيق
      </Text>
      <Field label="المظهر">
        <Segmented<SchemePreference>
          label="المظهر"
          value={preference}
          onChange={setPreference}
          options={[
            ['system', 'حسب الجهاز'],
            ['light', 'فاتح'],
            ['dark', 'داكن'],
          ]}
        />
      </Field>
      <InfoList items={[['آخر مزامنة', formatTime(app.state.lastSyncAt)]]} />
    </Screen>
  );
}

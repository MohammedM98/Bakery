import { router } from 'expo-router';
import { Printer, Scale, Trash2 } from 'lucide-react-native';
import * as React from 'react';
import { Platform, ScrollView, View } from 'react-native';

import { Chip, Field, InfoList, Notice, PendingMark, RoundIcon, Segmented, TextField, TYPE_ICONS, UnitInput, LTR_INPUT } from '@/components/common';
import { useConfirm } from '@/components/confirm';
import { Keypad } from '@/components/keypad';
import { BottomSheet } from '@/components/sheet';
import { useToast } from '@/components/toast';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { formatDate, formatDays, formatFlourBalance, formatKg, formatMoney, formatTime, fromCents, ltr, toCents } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Ledger } from '@/mock/types';
import { TODAY, useApp } from '@/state/app';
import { statement } from '@/state/selectors';

/**
 * Every form and detail view is a bottom sheet (CLAUDE.md section 13). One host renders the open
 * sheet; screens call useSheets().open({...}). On web, ?sheet=sale|payment|deposit|flour|adjustment|
 * movement|customer|bank|renew|bakery (with &customer=1, &movement=N, &bakery=2) opens one for screenshots.
 */
export type SheetRequest =
  | { kind: 'sale'; customerId?: number | null }
  | { kind: 'payment'; customerId: number; saleId?: number | null }
  | { kind: 'deposit'; customerId: number | null }
  | { kind: 'flour'; customerId: number }
  | { kind: 'adjustment'; customerId: number; corrects?: number | null; ledger?: Ledger }
  | { kind: 'movement'; movementId: number }
  | { kind: 'customer'; customerId?: number | null }
  | { kind: 'bank'; bankId?: number | null }
  | { kind: 'renew'; bakeryId: number }
  | { kind: 'bakery' };

const SheetContext = React.createContext<{ open: (r: SheetRequest) => void; close: () => void }>({ open: () => {}, close: () => {} });
export const useSheets = () => React.useContext(SheetContext);

function fromUrl(): SheetRequest | null {
  if (Platform.OS !== 'web' || typeof window === 'undefined') return null;
  const q = new URLSearchParams(window.location.search);
  const kind = q.get('sheet');
  const customerId = Number(q.get('customer') ?? 1);
  switch (kind) {
    case 'sale':
      return { kind, customerId: q.get('customer') ? customerId : null };
    case 'payment':
    case 'flour':
    case 'adjustment':
      return { kind, customerId };
    case 'deposit':
      return { kind, customerId: q.get('customer') ? customerId : null };
    case 'movement':
      return { kind, movementId: Number(q.get('movement') ?? 0) };
    case 'customer':
      return { kind, customerId: q.get('customer') ? customerId : null };
    case 'bank':
      return { kind, bankId: q.get('bank') ? Number(q.get('bank')) : null };
    case 'renew':
      return { kind, bakeryId: Number(q.get('bakery') ?? 2) };
    case 'bakery':
      return { kind };
    default:
      return null;
  }
}

export function SheetHost({ children }: { children: React.ReactNode }) {
  const [req, setReq] = React.useState<SheetRequest | null>(fromUrl);
  const api = React.useMemo(() => ({ open: setReq, close: () => setReq(null) }), []);
  const close = api.close;
  return (
    <SheetContext.Provider value={api}>
      {children}
      {req?.kind === 'sale' ? <SaleSheet key="sale" initialCustomer={req.customerId ?? null} onClose={close} /> : null}
      {req?.kind === 'payment' ? <PaymentSheet customerId={req.customerId} saleId={req.saleId ?? null} onClose={close} /> : null}
      {req?.kind === 'deposit' ? <DepositSheet initialCustomer={req.customerId} onClose={close} /> : null}
      {req?.kind === 'flour' ? <FlourSaleSheet customerId={req.customerId} onClose={close} /> : null}
      {req?.kind === 'adjustment' ? <AdjustmentSheet customerId={req.customerId} corrects={req.corrects ?? null} initialLedger={req.ledger ?? 'money'} onClose={close} /> : null}
      {req?.kind === 'movement' ? <MovementSheet movementId={req.movementId} onClose={close} open={api.open} /> : null}
      {req?.kind === 'customer' ? <CustomerSheet customerId={req.customerId ?? null} onClose={close} /> : null}
      {req?.kind === 'bank' ? <BankSheet bankId={req.bankId ?? null} onClose={close} /> : null}
      {req?.kind === 'renew' ? <RenewSheet bakeryId={req.bakeryId} onClose={close} /> : null}
      {req?.kind === 'bakery' ? <BakerySheet onClose={close} /> : null}
    </SheetContext.Provider>
  );
}

/** After a save: a toast that says whether it reached the server or waits for the next sync. */
function useSaved() {
  const toast = useToast();
  const { state } = useApp();
  return (title: string, body?: string) =>
    state.online
      ? toast({ kind: 'success', title, body })
      : toast({ kind: 'offline', title: 'حُفظ دون إنترنت', body: `${title}. سيُرسل عند الاتصال`, action: { label: 'المزامنة', onPress: () => router.push('/more/sync') } });
}

function SaveButton({ label, onPress, disabled }: { label: string; onPress: () => void; disabled?: boolean }) {
  return (
    <Button onPress={onPress} disabled={disabled} className="mt-2 w-full">
      <Text>{label}</Text>
    </Button>
  );
}

/** Pick a customer inside a sheet: chips for the first few, the rest scroll sideways. */
function CustomerPicker({ value, onChange, allowWalkIn }: { value: number | null; onChange: (id: number | null) => void; allowWalkIn?: boolean }) {
  const { state } = useApp();
  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} className="mb-4 -mx-5" contentContainerStyle={{ paddingHorizontal: 20, gap: 8 }}>
      {allowWalkIn ? <Chip label="مشترٍ عابر" on={value == null} onPress={() => onChange(null)} /> : null}
      {state.customers.map((c) => (
        <Chip key={c.id} label={c.name} on={value === c.id} onPress={() => onChange(c.id)} />
      ))}
    </ScrollView>
  );
}

// ---------------------------------------------------------------------------------------------

function SaleSheet({ initialCustomer, onClose }: { initialCustomer: number | null; onClose: () => void }) {
  const app = useApp();
  const saved = useSaved();
  const [qty, setQty] = React.useState(Platform.OS === 'web' && typeof window !== 'undefined' && window.location.search.includes('sheet=sale') ? '12' : '');
  const [paid, setPaid] = React.useState<'paid' | 'unpaid'>('paid');
  const [customer, setCustomer] = React.useState<number | null>(initialCustomer);
  const [picking, setPicking] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);
  const price = app.state.bakery.price_per_kg;
  const kg = Number(qty) || 0;
  const total = Math.round(toCents(price) * kg);
  const name = app.state.customers.find((c) => c.id === customer)?.name ?? 'مشترٍ عابر';

  const save = () => {
    const r = app.recordSale({ customerId: customer, buyerName: '', kg, paid: paid === 'paid' });
    if (!r.ok) return setError(r.error);
    saved('تم تسجيل البيع', `${formatKg(kg)}، ${formatMoney(total / 100)}`);
    onClose();
  };

  return (
    <BottomSheet
      open
      onClose={onClose}
      title="تسجيل عملية بيع"
      aside={app.can('view-customers') ? <Chip label={name} onPress={() => setPicking((p) => !p)} /> : null}>
      {picking ? (
        <CustomerPicker
          value={customer}
          allowWalkIn
          onChange={(id) => {
            setCustomer(id);
            setPicking(false);
          }}
        />
      ) : null}
      <View className="my-1 flex-row items-baseline justify-center gap-1.5" accessible accessibilityLabel={`الكمية ${kg} كغ`}>
        <Text className={cn('font-plex-semi text-[48px] leading-[58px]', !qty && 'text-muted-foreground')}>{qty || '0'}</Text>
        <Text className="text-muted-foreground text-[18px]">كغ</Text>
      </View>
      <Text variant="muted" className="mb-4 text-center text-[14px]">
        {kg} كغ × {price} شيكل = <Text className="font-plex-semi text-[14px]">{formatMoney(total / 100)}</Text>
      </Text>
      <Segmented
        label="حالة الدفع"
        className="mb-4"
        value={paid}
        onChange={setPaid}
        options={[
          ['paid', 'مدفوع'],
          ['unpaid', 'غير مدفوع'],
        ]}
      />
      <Keypad
        value={qty}
        onChange={(v) => {
          setQty(v);
          setError(null);
        }}
      />
      {error ? <Text className="text-destructive mb-2 text-center text-[13px]">{error}</Text> : null}
      {paid === 'unpaid' && customer == null ? <Notice body="البيع غير المدفوع لمشترٍ عابر يبقى في قائمة غير المدفوع حتى تأكيد دفعه" /> : null}
      <SaveButton label="حفظ العملية" onPress={save} disabled={!kg} />
    </BottomSheet>
  );
}

// ---------------------------------------------------------------------------------------------

function PaymentSheet({ customerId, saleId, onClose }: { customerId: number; saleId: number | null; onClose: () => void }) {
  const app = useApp();
  const saved = useSaved();
  const balance = app.balances.get(customerId)?.money ?? 0;
  const [amount, setAmount] = React.useState(Platform.OS === 'web' && typeof window !== 'undefined' && window.location.search.includes('sheet=payment') ? '90.00' : '');
  const [method, setMethod] = React.useState<'cash' | 'transfer'>('transfer');
  const [from, setFrom] = React.useState<string | null>(app.state.banks[0]?.name ?? null);
  const [to, setTo] = React.useState<string | null>(app.state.banks[1]?.name ?? null);
  const [reference, setReference] = React.useState('');
  const [forSale, setForSale] = React.useState<number | null>(saleId);
  const [error, setError] = React.useState<string | null>(null);
  const unpaid = app.state.sales.filter((s) => s.customer_id === customerId && toCents(s.paid_amount) < toCents(s.total_amount)).slice(0, 6);

  const save = () => {
    const r = app.recordPayment({ customerId, amount: Number(amount), method, fromBank: from, toBank: to, reference, saleId: forSale });
    if (!r.ok) return setError(r.error);
    saved('تم تسجيل الدفعة', formatMoney(amount));
    onClose();
  };

  return (
    <BottomSheet open onClose={onClose} title="تسجيل دفعة" aside={<Text variant="muted">{balance > 0 ? `عليه ${formatMoney(balance / 100)}` : balance < 0 ? `له ${formatMoney(-balance / 100)}` : 'لا شيء عليه'}</Text>}>
      <Field label="المبلغ" error={error}>
        <UnitInput label="المبلغ" unit="شيكل" value={amount} onChangeText={(v) => (setAmount(v), setError(null))} invalid={!!error} autoFocus={Platform.OS !== 'web'} />
      </Field>
      {balance > 0 ? (
        <Text onPress={() => setAmount(fromCents(balance))} accessibilityRole="button" className="text-muted-foreground -mt-1 mb-3 self-start py-1 text-[13px] underline">
          كامل المستحق {formatMoney(balance / 100)}
        </Text>
      ) : null}
      <Segmented
        label="طريقة الدفع"
        className="mb-4"
        value={method}
        onChange={setMethod}
        options={[
          ['cash', 'نقدًا'],
          ['transfer', 'تحويل'],
        ]}
      />
      {method === 'transfer' ? (
        <>
          <Text variant="label" className="mb-1.5">
            من ← إلى
          </Text>
          <View className="mb-1 flex-row flex-wrap items-center gap-2">
            <Text variant="muted" className="w-6">
              من
            </Text>
            {app.state.banks.map((b) => (
              <Chip key={b.id} label={b.name} on={from === b.name} onPress={() => setFrom(b.name)} />
            ))}
          </View>
          <View className="mb-4 flex-row flex-wrap items-center gap-2">
            <Text variant="muted" className="w-6">
              إلى
            </Text>
            {app.state.banks.map((b) => (
              <Chip key={b.id} label={b.name} on={to === b.name} onPress={() => setTo(b.name)} />
            ))}
          </View>
          <Field label="رقم الحوالة (اختياري)">
            <TextField value={reference} onChangeText={setReference} placeholder="مثل TR-90210" autoCapitalize="characters" style={LTR_INPUT} />
          </Field>
        </>
      ) : null}
      <Text variant="label" className="mb-1.5">
        الدفعة عن
      </Text>
      <View className="mb-4 flex-row flex-wrap gap-2">
        <Chip label="على الحساب" on={forSale == null} onPress={() => setForSale(null)} />
        {unpaid.map((s) => (
          <Chip key={s.id} label={`بيع ${formatDate(s.sale_date).slice(0, 5)}، ${fromCents(toCents(s.total_amount) - toCents(s.paid_amount))}`} on={forSale === s.id} onPress={() => setForSale(s.id)} />
        ))}
      </View>
      <SaveButton label="حفظ الدفعة" onPress={save} />
    </BottomSheet>
  );
}

// ---------------------------------------------------------------------------------------------

function DepositSheet({ initialCustomer, onClose }: { initialCustomer: number | null; onClose: () => void }) {
  const app = useApp();
  const saved = useSaved();
  const [customer, setCustomer] = React.useState<number | null>(initialCustomer);
  const [kg, setKg] = React.useState('');
  const [note, setNote] = React.useState('');
  const [error, setError] = React.useState<string | null>(null);
  const bal = customer ? (app.balances.get(customer)?.flour ?? 0) : 0;
  const after = bal - Math.round(Number(kg) * 100);

  const save = () => {
    if (!customer) return setError('اختر الزبون');
    const r = app.recordDeposit({ customerId: customer, kg: Number(kg), note });
    if (!r.ok) return setError(r.error);
    saved('تم تسجيل الإيداع', formatKg(kg));
    onClose();
  };

  return (
    <BottomSheet open onClose={onClose} title="إيداع طحين" aside={customer ? <Text variant="muted">{formatFlourBalance(bal / 100)}</Text> : null}>
      {initialCustomer == null ? (
        <>
          <Text variant="label" className="mb-1.5">
            الزبون
          </Text>
          <CustomerPicker value={customer} onChange={setCustomer} />
        </>
      ) : null}
      <Field label="الكمية" error={error} hint={customer && Number(kg) > 0 ? `الرصيد بعدها: ${formatFlourBalance(after / 100)}` : undefined}>
        <UnitInput label="الكمية" unit="كغ" value={kg} onChangeText={(v) => (setKg(v), setError(null))} invalid={!!error} />
      </Field>
      <Field label="ملاحظة (اختياري)">
        <TextField value={note} onChangeText={setNote} placeholder="مثل كيسان طحين أبيض" />
      </Field>
      <SaveButton label="حفظ الإيداع" onPress={save} />
    </BottomSheet>
  );
}

// ---------------------------------------------------------------------------------------------

function FlourSaleSheet({ customerId, onClose }: { customerId: number; onClose: () => void }) {
  const app = useApp();
  const saved = useSaved();
  const [kg, setKg] = React.useState(Platform.OS === 'web' && typeof window !== 'undefined' && window.location.search.includes('sheet=flour') ? '8' : '');
  const [paid, setPaid] = React.useState<'paid' | 'unpaid'>('unpaid');
  const [error, setError] = React.useState<string | null>(null);
  const bal = app.balances.get(customerId)?.flour ?? 0; // − = له
  const n = Math.round(Number(kg) * 100);
  const after = bal + n;
  const fee = app.state.bakery.flour_exchange_fee_per_kg;
  const feeTotal = Math.round(toCents(fee) * (Number(kg) || 0));

  const save = () => {
    const r = app.recordFlourSale({ customerId, kg: Number(kg), paid: paid === 'paid' });
    if (!r.ok) return setError(r.error);
    saved('تم تسجيل البيع مقابل الطحين', formatKg(kg));
    onClose();
  };

  return (
    <BottomSheet open onClose={onClose} title="خبز مقابل طحين" aside={<Text variant="muted">رصيده {formatFlourBalance(bal / 100)}</Text>}>
      <Field label="الكمية" error={error}>
        <UnitInput label="الكمية" unit="كغ" value={kg} onChangeText={(v) => (setKg(v), setError(null))} invalid={!!error} />
      </Field>
      {n > 0 && after > 0 ? <Notice tone="warning" body={`الرصيد لا يكفي. سيُسجَّل البيع ويصبح ${formatFlourBalance(after / 100)}`} /> : null}
      <Notice body={`رسم التبديل ${kg || 0} × ${fee} شيكل = ${formatMoney(feeTotal / 100)} على حسابه المالي`} />
      <Segmented
        label="حالة دفع الرسم"
        className="mb-4"
        value={paid}
        onChange={setPaid}
        options={[
          ['paid', 'مدفوع'],
          ['unpaid', 'غير مدفوع'],
        ]}
      />
      <SaveButton label="حفظ" onPress={save} />
    </BottomSheet>
  );
}

// ---------------------------------------------------------------------------------------------

function AdjustmentSheet({ customerId, corrects, initialLedger, onClose }: { customerId: number; corrects: number | null; initialLedger: Ledger; onClose: () => void }) {
  const app = useApp();
  const saved = useSaved();
  const [ledger, setLedger] = React.useState<Ledger>(initialLedger);
  const [direction, setDirection] = React.useState<'debit' | 'credit'>('credit');
  const [amount, setAmount] = React.useState('');
  const [reason, setReason] = React.useState('');
  const [number, setNumber] = React.useState(corrects ? String(corrects) : '');
  const [error, setError] = React.useState<{ field: 'amount' | 'reason'; text: string } | null>(null);

  const save = () => {
    const r = app.recordAdjustment({ customerId, ledger, direction, amount: Number(amount), reason, corrects: Number(number) || null });
    if (!r.ok) return setError({ field: r.error.includes('سبب') ? 'reason' : 'amount', text: r.error });
    saved('تم تسجيل قيد التسوية');
    onClose();
  };

  return (
    <BottomSheet open onClose={onClose} title="قيد تسوية">
      <Field label="الكشف">
        <Segmented
          label="الكشف"
          value={ledger}
          onChange={setLedger}
          options={[
            ['money', 'المالي'],
            ['flour', 'الطحين'],
          ]}
        />
      </Field>
      <Field label="الاتجاه">
        <Segmented
          label="الاتجاه"
          value={direction}
          onChange={setDirection}
          options={[
            ['debit', 'عليه'],
            ['credit', 'له'],
          ]}
        />
      </Field>
      <Field label={ledger === 'money' ? 'المبلغ' : 'الكمية'} error={error?.field === 'amount' ? error.text : null}>
        <UnitInput label="المبلغ" unit={ledger === 'money' ? 'شيكل' : 'كغ'} value={amount} onChangeText={(v) => (setAmount(v), setError(null))} invalid={error?.field === 'amount'} />
      </Field>
      <Field label="السبب" error={error?.field === 'reason' ? error.text : null}>
        <TextField value={reason} onChangeText={(v) => (setReason(v), setError(null))} placeholder="مثل تصحيح الحركة رقم 152" maxLength={200} invalid={error?.field === 'reason'} />
      </Field>
      <Field label="رقم الحركة المصحَّحة (اختياري)">
        <TextField value={number} onChangeText={(v) => setNumber(v.replace(/\D/g, ''))} keyboardType="number-pad" style={LTR_INPUT} />
      </Field>
      <SaveButton label="حفظ التسوية" onPress={save} />
    </BottomSheet>
  );
}

// ---------------------------------------------------------------------------------------------

function MovementSheet({ movementId, onClose, open }: { movementId: number; onClose: () => void; open: (r: SheetRequest) => void }) {
  const app = useApp();
  const toast = useToast();
  const m = app.state.movements.find((x) => x.id === movementId) ?? app.state.movements.filter((x) => x.type === 'payment' && x.from_bank).at(-1);
  if (!m) return null;
  const isMoney = m.ledger === 'money';
  const a = toCents(m.amount);
  const st = m.customer_id ? statement(app.state.movements, m.customer_id, m.ledger, null, null) : null;
  const after = st?.rows.find((r) => r.movement.id === m.id)?.balance;
  const tone = a < 0 ? 'success' : 'neutral';
  const canPrint = app.state.online && m.number != null;

  return (
    <BottomSheet open onClose={onClose} title={m.description}>
      <View className="mb-4 flex-row items-center gap-3">
        <RoundIcon icon={TYPE_ICONS[m.type]} tone={tone} />
        <Text variant="muted" className="flex-1">
          {m.customer_name ?? 'مشترٍ عابر'}، {formatDate(m.movement_date)} الساعة {formatTime(m.created_at)}
        </Text>
      </View>
      <Text className={cn('font-plex-semi mb-4 text-[32px] leading-[42px]', a < 0 && 'text-success')}>
        {isMoney ? formatMoney(Math.abs(a) / 100) : formatKg(Math.abs(a) / 100)}
        <Text className="text-muted-foreground text-[15px]"> {a > 0 ? 'عليه' : 'له'}</Text>
      </Text>
      <InfoList
        items={[
          ['الحالة', m.number == null ? <PendingMark /> : <Text className="text-success font-plex-medium text-[14px]">مُزامَنة</Text>],
          ['رقم الحركة', m.number == null ? 'يعطيه الخادم عند المزامنة' : String(m.number)],
        ]}
      />
      {m.type === 'payment' ? (
        <InfoList
          items={[
            ['طريقة الدفع', m.from_bank ? `${m.from_bank} ← ${m.to_bank}` : 'نقدًا'],
            ...(m.transfer_reference ? ([['رقم الحوالة', ltr(m.transfer_reference)]] as [string, string][]) : []),
          ]}
        />
      ) : null}
      <InfoList
        items={[
          ...(after != null ? ([['الرصيد بعد الحركة', isMoney ? formatMoney(after / 100) : formatFlourBalance(after / 100)]] as [string, string][]) : []),
          ['سجّلها', m.recorded_by_name],
          ...(m.note ? ([['ملاحظة', m.note]] as [string, string][]) : []),
        ]}
      />
      <View className="mt-4 flex-row gap-2">
        {m.customer_id && app.can('record-adjustment') ? (
          <Button variant="glass" className="flex-1" onPress={() => open({ kind: 'adjustment', customerId: m.customer_id!, corrects: m.number, ledger: m.ledger })}>
            <Icon as={Scale} size={18} />
            <Text>قيد تسوية</Text>
          </Button>
        ) : null}
        <Button
          variant="pill"
          className="flex-1"
          disabled={!canPrint}
          onPress={() => toast({ kind: 'success', title: 'أُرسل الإيصال للطابعة', body: `الحركة رقم ${m.number}` })}>
          <Icon as={Printer} size={18} />
          <Text>طباعة الإيصال</Text>
        </Button>
      </View>
      {!canPrint ? (
        <Text variant="muted" className="mt-2 text-center">
          {app.state.online ? 'الطباعة بعد أن يؤكد الخادم العملية' : 'الطباعة تحتاج إنترنت'}
        </Text>
      ) : null}
    </BottomSheet>
  );
}

// ---------------------------------------------------------------------------------------------

function CustomerSheet({ customerId, onClose }: { customerId: number | null; onClose: () => void }) {
  const app = useApp();
  const toast = useToast();
  const confirm = useConfirm();
  const saved = useSaved();
  const existing = app.state.customers.find((c) => c.id === customerId);
  const [name, setName] = React.useState(existing?.name ?? '');
  const [phone, setPhone] = React.useState(existing?.phone ?? '');
  const [notes, setNotes] = React.useState(existing?.notes ?? '');
  const [flour, setFlour] = React.useState('');
  const [error, setError] = React.useState<string | null>(null);
  const editing = !!existing;
  const offlineEdit = editing && !app.state.online;

  const save = () => {
    if (editing) {
      const r = app.updateCustomer(existing.id, { name, phone, notes });
      if (!r.ok) return setError(r.error);
      toast({ kind: 'success', title: 'تم حفظ بيانات الزبون' });
    } else {
      const r = app.addCustomer({ name, phone, notes, openingFlour: Number(flour) || 0 });
      if (!r.ok) return setError(r.error);
      saved('تمت إضافة الزبون', name);
    }
    onClose();
  };

  return (
    <BottomSheet open onClose={onClose} title={editing ? 'تعديل الزبون' : 'إضافة زبون'}>
      {offlineEdit ? <Notice tone="warning" title="يحتاج إنترنت" body="تعديل بيانات الزبون يتم بالإنترنت فقط" /> : null}
      <Field label="الاسم" error={error}>
        <TextField value={name} onChangeText={(v) => (setName(v), setError(null))} invalid={!!error} autoFocus={Platform.OS !== 'web'} />
      </Field>
      <Field label="رقم الجوال (اختياري)">
        <TextField value={phone} onChangeText={setPhone} keyboardType="phone-pad" inputMode="tel" style={LTR_INPUT} />
      </Field>
      <Field label="ملاحظات (اختياري)">
        <TextField value={notes} onChangeText={setNotes} multiline className="h-24 py-3" textAlignVertical="top" />
      </Field>
      {!editing && app.can('record-deposit') ? (
        <Field label="رصيد طحين ابتدائي له (اختياري)">
          <UnitInput label="رصيد طحين ابتدائي" unit="كغ" value={flour} onChangeText={setFlour} />
        </Field>
      ) : null}
      <SaveButton label={editing ? 'حفظ التعديل' : 'إضافة الزبون'} onPress={save} disabled={offlineEdit} />
      {editing && app.can('delete-customers') ? (
        <Button
          variant="danger"
          className="mt-2 w-full"
          disabled={!app.state.online}
          onPress={() =>
            confirm({
              title: `حذف الزبون «${existing.name}»؟`,
              body: 'تُحذف معه كل مبيعاته وحركاته نهائيًا، ولا يمكن التراجع.',
              action: 'حذف',
              destructive: true,
              onConfirm: () => {
                app.deleteCustomer(existing.id);
                onClose();
                router.replace('/customers');
                toast({ kind: 'success', title: 'تم حذف الزبون' });
              },
            })
          }>
          <Icon as={Trash2} size={18} />
          <Text>حذف الزبون</Text>
        </Button>
      ) : null}
    </BottomSheet>
  );
}

function BankSheet({ bankId, onClose }: { bankId: number | null; onClose: () => void }) {
  const app = useApp();
  const toast = useToast();
  const existing = app.state.banks.find((b) => b.id === bankId);
  const [name, setName] = React.useState(existing?.name ?? '');
  const [error, setError] = React.useState<string | null>(null);
  return (
    <BottomSheet open onClose={onClose} title={existing ? 'تعديل البنك' : 'إضافة بنك أو محفظة'}>
      <Field label="الاسم" error={error} hint="يظهر في «من ← إلى» عند تسجيل دفعة بتحويل">
        <TextField value={name} onChangeText={(v) => (setName(v), setError(null))} placeholder="مثل بنك فلسطين" invalid={!!error} />
      </Field>
      {existing ? <Notice body="تغيير الاسم لا يغيّر الدفعات المسجّلة؛ تبقى بالاسم القديم" /> : null}
      <SaveButton
        label="حفظ"
        onPress={() => {
          const r = app.saveBank(existing?.id ?? null, name);
          if (!r.ok) return setError(r.error);
          toast({ kind: 'success', title: existing ? 'تم تعديل البنك' : 'تمت إضافة البنك' });
          onClose();
        }}
      />
    </BottomSheet>
  );
}

// ---------------------------------------------------------------------------------------------

function RenewSheet({ bakeryId, onClose }: { bakeryId: number; onClose: () => void }) {
  const app = useApp();
  const toast = useToast();
  const b = app.state.bakeries.find((x) => x.id === bakeryId);
  const [months, setMonths] = React.useState(3);
  if (!b) return null;
  const from = b.subscription_ends_at > TODAY ? b.subscription_ends_at : TODAY;
  const end = new Date(`${from}T12:00:00Z`);
  end.setUTCMonth(end.getUTCMonth() + months);
  const left = Math.round((Date.parse(b.subscription_ends_at) - Date.parse(TODAY)) / 86400000);
  return (
    <BottomSheet open onClose={onClose} title="تجديد الاشتراك" aside={left >= 0 ? <Text className="text-warning text-[13px]">متبقٍ {formatDays(left)}</Text> : null}>
      <Text variant="muted" className="mb-2">
        {b.name}
      </Text>
      <View className="mb-1 flex-row gap-2">
        {[1, 3, 6, 12].map((m) => (
          <View key={m} className="flex-1">
            <Chip label={String(m)} on={months === m} onPress={() => setMonths(m)} />
          </View>
        ))}
      </View>
      <Text variant="muted" className="mb-4">
        أشهر
      </Text>
      <Notice body={`ينتهي الاشتراك الجديد في ${formatDate(end.toISOString())}`} />
      <SaveButton
        label={`تجديد ${months === 1 ? 'شهر' : months === 12 ? '12 شهرًا' : `${months} أشهر`}`}
        onPress={() => {
          app.renewBakery(b.id, months);
          toast({ kind: 'success', title: 'تم تجديد الاشتراك', body: b.name });
          onClose();
        }}
      />
    </BottomSheet>
  );
}

function BakerySheet({ onClose }: { onClose: () => void }) {
  const app = useApp();
  const toast = useToast();
  const [f, setF] = React.useState({ name: '', address: '', ownerName: '', ownerEmail: '', password: '', months: 1 });
  const [error, setError] = React.useState<string | null>(null);
  const set = (k: keyof typeof f) => (v: string) => (setF((x) => ({ ...x, [k]: v })), setError(null));
  return (
    <BottomSheet open onClose={onClose} title="إضافة مخبز">
      {error ? <Notice tone="danger" body={error} /> : null}
      <Field label="اسم المخبز">
        <TextField value={f.name} onChangeText={set('name')} />
      </Field>
      <Field label="العنوان (اختياري)">
        <TextField value={f.address} onChangeText={set('address')} />
      </Field>
      <Field label="اسم صاحب المخبز">
        <TextField value={f.ownerName} onChangeText={set('ownerName')} />
      </Field>
      <Field label="البريد الإلكتروني">
        <TextField value={f.ownerEmail} onChangeText={set('ownerEmail')} keyboardType="email-address" autoCapitalize="none" style={LTR_INPUT} />
      </Field>
      <Field label="كلمة المرور" hint="8 أحرف على الأقل">
        <TextField value={f.password} onChangeText={set('password')} secureTextEntry />
      </Field>
      <Field label="مدة الاشتراك">
        <View className="flex-row gap-2">
          {[1, 3, 6, 12].map((m) => (
            <View key={m} className="flex-1">
              <Chip label={`${m}`} on={f.months === m} onPress={() => setF((x) => ({ ...x, months: m }))} />
            </View>
          ))}
        </View>
      </Field>
      <SaveButton
        label="إضافة المخبز"
        onPress={() => {
          const r = app.addBakery(f);
          if (!r.ok) return setError(r.error);
          toast({ kind: 'success', title: 'تمت إضافة المخبز', body: f.name });
          onClose();
        }}
      />
    </BottomSheet>
  );
}


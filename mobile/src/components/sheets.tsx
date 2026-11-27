import { router } from 'expo-router';
import { Printer, Scale, Trash2 } from 'lucide-react-native';
import * as React from 'react';
import * as Print from 'expo-print';
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
import type { ApiBakery, CustomerDetail, Ledger, OperationResult } from '@/api/types';
import { fromMovement, fromResult, receiptHtml, type Receipt } from '@/lib/receipt';
import { today, useApp, useRemote, type OpResult } from '@/state/app';
import { statement } from '@/state/statement';
import { movementKey } from '@/sync/engine';

/**
 * Every form and detail view is a bottom sheet (CLAUDE.md section 13). One host renders the open
 * sheet; screens call useSheets().open({...}). On web, ?sheet=sale|payment|deposit|flour|adjustment|
 * movement|customer|bank|renew|bakery (with &customer=1, &movement=N, &bakery=2) opens one for screenshots.
 */
export type SheetRequest =
  | { kind: 'sale' }
  | { kind: 'payment'; customerId: number; saleId?: number | null }
  | { kind: 'deposit'; customerId: number | null }
  | { kind: 'flour'; customerId: number }
  | { kind: 'adjustment'; customerId: number; corrects?: number | null; ledger?: Ledger }
  | { kind: 'movement'; movementKey: string }
  | { kind: 'customer'; customerId?: number | null }
  | { kind: 'bank'; bankId?: number | null }
  | { kind: 'renew'; bakery: ApiBakery }
  | { kind: 'print'; receipt: Receipt }
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
      return { kind };
    case 'payment':
    case 'flour':
    case 'adjustment':
      return { kind, customerId };
    case 'deposit':
      return { kind, customerId: q.get('customer') ? customerId : null };
    case 'movement':
      return { kind, movementKey: `n${q.get('movement') ?? 0}` };
    case 'customer':
      return { kind, customerId: q.get('customer') ? customerId : null };
    case 'bank':
      return { kind, bankId: q.get('bank') ? Number(q.get('bank')) : null };
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
      {req?.kind === 'sale' ? <SaleSheet key="sale" onClose={close} /> : null}
      {req?.kind === 'payment' ? <PaymentSheet customerId={req.customerId} saleId={req.saleId ?? null} onClose={close} /> : null}
      {req?.kind === 'deposit' ? <DepositSheet initialCustomer={req.customerId} onClose={close} /> : null}
      {req?.kind === 'flour' ? <FlourSaleSheet customerId={req.customerId} onClose={close} /> : null}
      {req?.kind === 'adjustment' ? <AdjustmentSheet customerId={req.customerId} corrects={req.corrects ?? null} initialLedger={req.ledger ?? 'money'} onClose={close} /> : null}
      {req?.kind === 'movement' ? <MovementSheet movementKey={req.movementKey} onClose={close} open={api.open} /> : null}
      {req?.kind === 'customer' ? <CustomerSheet customerId={req.customerId ?? null} onClose={close} /> : null}
      {req?.kind === 'bank' ? <BankSheet bankId={req.bankId ?? null} onClose={close} /> : null}
      {req?.kind === 'renew' ? <RenewSheet bakery={req.bakery} onClose={close} /> : null}
      {req?.kind === 'print' ? <PrintSheet receipt={req.receipt} onClose={close} /> : null}
      {req?.kind === 'bakery' ? <BakerySheet onClose={close} /> : null}
    </SheetContext.Provider>
  );
}

/** After a save: a toast that says whether the server confirmed it (with its receipt) or it waits for the next sync. */
function useSaved() {
  const toast = useToast();
  const print = usePrint();
  const { state } = useApp();
  return (r: OpResult & { ok: true }, title: string, body?: string) =>
    r.queued
      ? toast({
          kind: 'offline',
          title: state.online ? 'حُفظ وسيُرسل لاحقًا' : 'حُفظ دون إنترنت',
          body: state.online ? `${title}. تعذّر الإرسال الآن، ويُعاد تلقائيًا` : `${title}. سيُرسل عند الاتصال`,
          action: { label: 'المزامنة', onPress: () => router.push('/more/sync') },
        })
      : toast({ kind: 'success', title, body, ...(r.result?.movements?.length ? { action: { label: 'طباعة', onPress: () => print(r.result!) } } : {}) });
}

/** Print a confirmed operation: choose the small receipt or A4. */
export function usePrint() {
  const { open } = useSheets();
  const { me } = useApp();
  return (result: OperationResult) => {
    const receipt = fromResult(result, me?.bakery?.name ?? '');
    if (receipt) open({ kind: 'print', receipt });
  };
}

function PrintSheet({ receipt, onClose }: { receipt: Receipt; onClose: () => void }) {
  const app = useApp();
  const toast = useToast();
  const go = async (size: 'small' | 'a4') => {
    try {
      await Print.printAsync({ html: receiptHtml(receipt, size) });
      onClose();
    } catch {
      toast({ kind: 'error', title: 'تعذّرت الطباعة', body: 'تحقق من الطابعة وحاول مرة أخرى' });
    }
  };
  return (
    <BottomSheet open onClose={onClose} title="طباعة الإيصال" aside={<Text variant="muted">حركة رقم {receipt.numbers.join('، ')}</Text>}>
      <InfoList items={[['الزبون', receipt.who], ...receipt.lines.slice(0, 3)]} />
      {!app.state.online ? <Notice tone="warning" body="الطباعة تحتاج إنترنت" /> : null}
      <View className="mt-2 flex-row gap-2">
        <Button className="flex-1" disabled={!app.state.online} onPress={() => go('small')}>
          <Icon as={Printer} size={18} />
          <Text>إيصال صغير</Text>
        </Button>
        <Button variant="glass" className="flex-1" disabled={!app.state.online} onPress={() => go('a4')}>
          <Text>صفحة A4</Text>
        </Button>
      </View>
    </BottomSheet>
  );
}

/** The sheet's one primary button; disabled while saving so a double tap never records twice. */
function SaveButton({ label, onPress, disabled }: { label: string; onPress: () => unknown; disabled?: boolean }) {
  const [busy, setBusy] = React.useState(false);
  return (
    <Button
      onPress={async () => {
        if (busy) return;
        setBusy(true);
        try {
          await onPress();
        } finally {
          setBusy(false);
        }
      }}
      disabled={disabled || busy}
      className="mt-2 w-full">
      <Text>{busy ? 'جارٍ الحفظ' : label}</Text>
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

function SaleSheet({ onClose }: { onClose: () => void }) {
  const app = useApp();
  const saved = useSaved();
  const [qty, setQty] = React.useState(Platform.OS === 'web' && typeof window !== 'undefined' && window.location.search.includes('sheet=sale') ? '12' : '');
  const [paid, setPaid] = React.useState<'paid' | 'unpaid'>('paid');
  const [buyer, setBuyer] = React.useState('');
  const [naming, setNaming] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);
  const price = app.me?.prices?.regular_price_per_kg ?? '0.00';
  const kg = Number(qty) || 0;
  const total = Math.round(toCents(price) * kg);

  const save = async () => {
    const r = await app.recordSale({ buyerName: buyer, kg, paid: paid === 'paid' });
    if (!r.ok) return setError(r.error);
    saved(r, 'تم تسجيل البيع', `${formatKg(kg)}، ${(r.ok && r.result?.sale?.total_text) || formatMoney(total / 100)}`);
    onClose();
  };

  return (
    <BottomSheet open onClose={onClose} title="تسجيل عملية بيع" aside={<Chip label={buyer.trim() || 'مشترٍ عابر'} onPress={() => setNaming((p) => !p)} />}>
      {naming ? (
        <Field label="اسم المشتري (اختياري)">
          <TextField value={buyer} onChangeText={setBuyer} placeholder="مشترٍ عابر" autoFocus={Platform.OS !== 'web'} onSubmitEditing={() => setNaming(false)} />
        </Field>
      ) : null}
      <View className="my-1 flex-row items-baseline justify-center gap-1.5" accessible accessibilityLabel={`الكمية ${kg} كغ`}>
        <Text className={cn('font-plex-semi text-[48px] leading-[58px]', !qty && 'text-muted-foreground')}>{qty || '0'}</Text>
        <Text className="text-muted-foreground text-[18px]">كغ</Text>
      </View>
      <Text variant="muted" className="mb-4 text-center text-[14px]">
        {kg} كغ × {formatMoney(price)} = <Text className="font-plex-semi text-[14px]">{formatMoney(total / 100)}</Text>
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
      {error ? (
        <Text accessibilityRole="alert" className="text-destructive mb-2 text-center text-[13px]">
          {error}
        </Text>
      ) : null}
      {paid === 'unpaid' ? <Notice body="البيع غير المدفوع لمشترٍ عابر يبقى في قائمة غير المدفوع حتى تأكيد دفعه" /> : null}
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
  const [method, setMethod] = React.useState<'cash' | 'transfer'>('cash');
  const banks = app.state.banks;
  const [from, setFrom] = React.useState<number | null>(banks[0]?.id ?? null);
  const [to, setTo] = React.useState<number | null>(banks[1]?.id ?? banks[0]?.id ?? null);
  const [reference, setReference] = React.useState('');
  const [forSale, setForSale] = React.useState<number | null>(saleId);
  const [error, setError] = React.useState<string | null>(null);
  // The customer's unpaid sales come from the server (the last answer offline); a customer added offline has none.
  const detail = useRemote<CustomerDetail>(customerId > 0 ? `customers/${customerId}` : null);
  const unpaid = (detail.data?.unpaid_sales ?? []).slice(0, 6);

  const save = async () => {
    const sale = unpaid.find((x) => x.id === forSale);
    const r = await app.recordPayment({
      customerId,
      amount: Number(amount),
      method,
      fromBank: banks.find((b) => b.id === from) ?? null,
      toBank: banks.find((b) => b.id === to) ?? null,
      reference,
      sale: sale ? { id: sale.id, sale_date: sale.sale_date } : null,
    });
    if (!r.ok) return setError(r.error);
    saved(r, 'تم تسجيل الدفعة', formatMoney(amount));
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
      {method === 'transfer' && !banks.length ? <Notice tone="warning" body="لا بنوك بعد. أضفها من «البنوك والمحافظ» بالإنترنت" /> : null}
      {method === 'transfer' && banks.length ? (
        <>
          <Text variant="label" className="mb-1.5">
            من ← إلى
          </Text>
          <View className="mb-1 flex-row flex-wrap items-center gap-2">
            <Text variant="muted" className="w-6">
              من
            </Text>
            {banks.map((b) => (
              <Chip key={b.id} label={b.name} on={from === b.id} onPress={() => setFrom(b.id)} />
            ))}
          </View>
          <View className="mb-4 flex-row flex-wrap items-center gap-2">
            <Text variant="muted" className="w-6">
              إلى
            </Text>
            {banks.map((b) => (
              <Chip key={b.id} label={b.name} on={to === b.id} onPress={() => setTo(b.id)} />
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
          <Chip key={s.id} label={`بيع ${formatDate(s.sale_date).slice(0, 5)}، ${s.remaining_amount}`} on={forSale === s.id} onPress={() => setForSale(s.id)} />
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

  const save = async () => {
    if (!customer) return setError('اختر الزبون');
    const r = await app.recordDeposit({ customerId: customer, kg: Number(kg), note });
    if (!r.ok) return setError(r.error);
    saved(r, 'تم تسجيل الإيداع', formatKg(kg));
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
  const fee = app.me?.prices?.flour_exchange_fee_per_kg ?? '0.00';
  const feeTotal = Math.round(toCents(fee) * (Number(kg) || 0));

  const save = async () => {
    const r = await app.recordFlourSale({ customerId, kg: Number(kg), paid: paid === 'paid' });
    if (!r.ok) return setError(r.error);
    saved(r, 'تم تسجيل البيع مقابل الطحين', formatKg(kg));
    onClose();
  };

  return (
    <BottomSheet open onClose={onClose} title="خبز مقابل طحين" aside={<Text variant="muted">رصيده {formatFlourBalance(bal / 100)}</Text>}>
      <Field label="الكمية" error={error}>
        <UnitInput label="الكمية" unit="كغ" value={kg} onChangeText={(v) => (setKg(v), setError(null))} invalid={!!error} />
      </Field>
      {n > 0 && after > 0 ? <Notice tone="warning" body={`الرصيد لا يكفي. سيُسجَّل البيع ويصبح ${formatFlourBalance(after / 100)}`} /> : null}
      <Notice body={`رسم التبديل ${kg || 0} × ${formatMoney(fee)} = ${formatMoney(feeTotal / 100)} على حسابه المالي`} />
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

  const save = async () => {
    const r = await app.recordAdjustment({ customerId, ledger, direction, amount: Number(amount), reason, corrects: Number(number) || null });
    if (!r.ok) return setError({ field: r.error.includes('سبب') ? 'reason' : 'amount', text: r.error });
    saved(r, 'تم تسجيل قيد التسوية');
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

function MovementSheet({ movementKey: key, onClose, open }: { movementKey: string; onClose: () => void; open: (r: SheetRequest) => void }) {
  const app = useApp();
  const m = app.state.movements.find((x) => movementKey(x) === key);
  if (!m) return null;
  const isMoney = m.ledger === 'money';
  const a = toCents(m.amount);
  const customer = m.customer_id != null ? app.customer(m.customer_id) : undefined;
  const balance = m.customer_id != null ? app.balances.get(m.customer_id) : undefined;
  const st = m.customer_id != null && balance ? statement(app.state.movements, m.customer_id, m.ledger, isMoney ? balance.money : balance.flour, null, null) : null;
  const after = st?.rows.find((r) => r.movement === m)?.balance;
  const afterText = after == null ? null : isMoney ? formatMoney(after / 100) : formatFlourBalance(after / 100);
  const canPrint = app.state.online && m.number != null;
  const who = customer?.name ?? 'مشترٍ عابر';

  return (
    <BottomSheet open onClose={onClose} title={m.description}>
      <View className="mb-4 flex-row items-center gap-3">
        <RoundIcon icon={TYPE_ICONS[m.type]} tone={a < 0 ? 'success' : 'neutral'} />
        <Text variant="muted" className="flex-1">
          {who}، {formatDate(m.date)} الساعة {formatTime(m.created_at)}
        </Text>
      </View>
      <Text className={cn('font-plex-semi mb-4 text-[32px] leading-[42px]', a < 0 && 'text-success')}>
        {m.amount_text}
        <Text className="text-muted-foreground text-[15px]"> {m.direction_label}</Text>
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
            ['طريقة الدفع', m.transfer ?? 'نقدًا'],
            ...(m.transfer_reference ? ([['رقم الحوالة', ltr(m.transfer_reference)]] as [string, string][]) : []),
          ]}
        />
      ) : null}
      <InfoList
        items={[
          ...(afterText ? ([['الرصيد بعد الحركة', afterText]] as [string, string][]) : []),
          ['سجّلها', m.recorded_by],
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
          onPress={() => open({ kind: 'print', receipt: fromMovement(m, app.me?.bakery?.name ?? '', who, isMoney ? { money: afterText ?? '' } : { flour: afterText ?? '' }) })}>
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
  const existing = customerId != null ? app.customer(customerId) : undefined;
  const [name, setName] = React.useState(existing?.name ?? '');
  const [phone, setPhone] = React.useState(existing?.mobile_number ?? '');
  const [notes, setNotes] = React.useState(existing?.notes ?? '');
  const [flour, setFlour] = React.useState('');
  const [error, setError] = React.useState<string | null>(null);
  const editing = !!existing;
  // Editing and deleting are online only; a customer still waiting for the server cannot be edited yet.
  const offlineEdit = editing && (!app.state.online || existing.id < 0);

  const save = async () => {
    if (!phone.trim()) return setError('اكتب رقم الجوال');
    if (editing) {
      const r = await app.online('PUT', `customers/${existing.id}`, { name, mobile_number: phone, notes: notes || null });
      if (!r.ok) return setError(r.error);
      toast({ kind: 'success', title: 'تم حفظ بيانات الزبون' });
    } else {
      const r = await app.addCustomer({ name, phone, notes, openingFlour: Number(flour) || 0 });
      if (!r.ok) return setError(r.error);
      saved(r, 'تمت إضافة الزبون', name);
    }
    onClose();
  };

  return (
    <BottomSheet open onClose={onClose} title={editing ? 'تعديل الزبون' : 'إضافة زبون'}>
      {offlineEdit ? <Notice tone="warning" title="يحتاج إنترنت" body="تعديل بيانات الزبون يتم بالإنترنت فقط" /> : null}
      <Field label="الاسم" error={error}>
        <TextField value={name} onChangeText={(v) => (setName(v), setError(null))} invalid={!!error} autoFocus={Platform.OS !== 'web'} />
      </Field>
      <Field label="رقم الجوال">
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
          disabled={offlineEdit}
          onPress={() =>
            confirm({
              title: `حذف الزبون «${existing.name}»؟`,
              body: 'تُحذف معه كل مبيعاته وحركاته نهائيًا، ولا يمكن التراجع.',
              action: 'حذف',
              destructive: true,
              onConfirm: async () => {
                const r = await app.online('DELETE', `customers/${existing.id}`);
                if (!r.ok) return toast({ kind: 'error', title: 'تعذّر حذف الزبون', body: r.error });
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
        onPress={async () => {
          const r = existing ? await app.online('PUT', `banks/${existing.id}`, { name }) : await app.online('POST', 'banks', { name });
          if (!r.ok) return setError(r.error);
          toast({ kind: 'success', title: existing ? 'تم تعديل البنك' : 'تمت إضافة البنك' });
          onClose();
        }}
      />
    </BottomSheet>
  );
}

// ---------------------------------------------------------------------------------------------

function RenewSheet({ bakery: b, onClose }: { bakery: ApiBakery; onClose: () => void }) {
  const app = useApp();
  const toast = useToast();
  const [months, setMonths] = React.useState(3);
  const [error, setError] = React.useState<string | null>(null);
  const ends = b.subscription.expires_at;
  const from = ends && ends > today() ? ends : today();
  const end = new Date(`${from}T12:00:00Z`);
  end.setUTCMonth(end.getUTCMonth() + months);
  const left = b.subscription.days_left;
  return (
    <BottomSheet open onClose={onClose} title="تجديد الاشتراك" aside={left != null && left >= 0 ? <Text className="text-warning text-[13px]">متبقٍ {formatDays(left)}</Text> : null}>
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
      {error ? <Notice tone="danger" body={error} /> : null}
      <SaveButton
        label={`تجديد ${months === 1 ? 'شهر' : months === 12 ? '12 شهرًا' : `${months} أشهر`}`}
        disabled={!app.state.online}
        onPress={async () => {
          const r = await app.online('POST', `admin/bakeries/${b.id}/renew`, { months });
          if (!r.ok) return setError(r.error);
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
        disabled={!app.state.online}
        onPress={async () => {
          const r = await app.online('POST', 'admin/bakeries', {
            name: f.name,
            address: f.address || null,
            subscription_months: f.months,
            owner_name: f.ownerName,
            owner_email: f.ownerEmail.trim().toLowerCase(),
            owner_password: f.password,
          });
          if (!r.ok) return setError(r.error);
          toast({ kind: 'success', title: 'تمت إضافة المخبز', body: f.name });
          onClose();
        }}
      />
    </BottomSheet>
  );
}


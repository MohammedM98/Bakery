import {
  ArrowLeftRight,
  Banknote,
  Info,
  Landmark,
  Scale,
  ShoppingBag,
  Store,
  Wheat,
  X,
  type LucideIcon,
} from 'lucide-react-native';
import * as React from 'react';
import { Platform, Pressable, View, type TextStyle, type ViewProps } from 'react-native';

import { solidStyle, Surface, Well } from '@/components/glass';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { Text } from '@/components/ui/text';
import { formatFlourBalance, formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { MovementType } from '@/api/types';
import { useTheme } from '@/state/theme';

// ---------------------------------------------------------------------------------------------
// Segmented control: a recessed well track, the selected option a solid white (slate in dark) chip.
// Used for the period pills and two-way switches (paid/unpaid, cash/transfer, ledger, direction).

export function Segmented<T extends string>({
  options,
  value,
  onChange,
  label,
  className,
}: {
  options: readonly (readonly [T, string])[];
  value: T;
  onChange: (v: T) => void;
  label: string;
  className?: string;
}) {
  const { theme } = useTheme();
  return (
    <Well radius={999} className={cn('flex-row p-1', className)} accessibilityRole="radiogroup" accessibilityLabel={label}>
      {options.map(([key, text]) => {
        const on = key === value;
        return (
          <Pressable
            key={key}
            onPress={() => onChange(key)}
            accessibilityRole="radio"
            accessibilityState={{ selected: on, checked: on }}
            className="min-h-10 flex-1 items-center justify-center rounded-full px-3"
            style={on ? { ...solidStyle(theme), borderWidth: 0 } : undefined}>
            <Text className={cn('text-[14px] leading-[22px]', on ? 'font-plex-semi text-foreground' : 'text-muted-foreground')}>{text}</Text>
          </Pressable>
        );
      })}
    </Well>
  );
}

/** Filter chip: the selected one is the dark pill, the others level-2 glass. 40pt high (44 with spacing). */
export function Chip({ label, on, onPress, count }: { label: string; on?: boolean; onPress?: () => void; count?: number }) {
  const inner = (
    <Text className={cn('text-[14px] leading-[22px]', on ? 'text-card dark:text-background' : 'text-muted-foreground')}>
      {label}
      {count != null ? `  ${count}` : ''}
    </Text>
  );
  return (
    <Pressable onPress={onPress} accessibilityRole="button" accessibilityState={{ selected: !!on }} className="active:opacity-80">
      {on ? (
        <View className="bg-foreground h-10 justify-center rounded-full px-4">{inner}</View>
      ) : (
        <Surface level="l2" radius={999} blur={false} className="h-10 justify-center px-4">
          {inner}
        </Surface>
      )}
    </Pressable>
  );
}

// ---------------------------------------------------------------------------------------------
// Lists: one level-1 card, rows separated by hairlines, a round icon in a well at the start.

export function ListCard({ children, className, ...props }: ViewProps) {
  return (
    <Surface level="l1" radius={22} className={cn('px-4 py-1', className)} {...props}>
      {children}
    </Surface>
  );
}

export const TYPE_ICONS: Record<MovementType | 'bank' | 'bakery', LucideIcon> = {
  sale: ShoppingBag,
  exchange_fee: ArrowLeftRight,
  flour_withdrawal: ArrowLeftRight,
  deposit: Wheat,
  payment: Banknote,
  adjustment: Scale,
  opening_balance: Scale,
  bank: Landmark,
  bakery: Store,
};

export type Tone = 'neutral' | 'success' | 'danger' | 'warning';

export function RoundIcon({ icon, tone = 'neutral', size = 40, flip }: { icon: LucideIcon; tone?: Tone; size?: number; flip?: boolean }) {
  const { theme } = useTheme();
  const bg = { neutral: undefined, success: theme.s.successSoft, danger: theme.s.dangerSoft, warning: theme.s.warningSoft }[tone];
  const fg = { neutral: 'text-muted-foreground', success: 'text-success', danger: 'text-destructive', warning: 'text-warning' }[tone];
  // flip: directional icons (sign out) mirror in RTL.
  const body = <Icon as={icon} size={18} strokeWidth={1.5} className={cn(fg, flip && '-scale-x-100')} />;
  return bg ? (
    <View style={{ width: size, height: size, borderRadius: size / 2, backgroundColor: bg }} className="items-center justify-center">
      {body}
    </View>
  ) : (
    <Well radius={size / 2} style={{ width: size, height: size }} className="items-center justify-center">
      {body}
    </Well>
  );
}

export function Avatar({ name, size = 40 }: { name: string; size?: number }) {
  const letters = name.replace(/^(أبو|أم)\s+/, '').slice(0, 2);
  return (
    <Well radius={size / 2} style={{ width: size, height: size }} className="items-center justify-center">
      <Text className="font-plex-semi text-[15px]">{letters}</Text>
    </Well>
  );
}

export function Row({
  start,
  title,
  subtitle,
  end,
  endSub,
  onPress,
  first,
  accessibilityLabel,
}: {
  start?: React.ReactNode;
  title: React.ReactNode;
  subtitle?: React.ReactNode;
  end?: React.ReactNode;
  endSub?: React.ReactNode;
  onPress?: () => void;
  first?: boolean;
  accessibilityLabel?: string;
}) {
  const { theme } = useTheme();
  const content = (
    <View
      className="min-h-16 flex-row items-center gap-3 py-3"
      style={first ? undefined : { borderTopWidth: 1, borderTopColor: theme.s.hairline }}>
      {start}
      <View className="min-w-0 flex-1">
        {typeof title === 'string' ? (
          <Text className="font-plex-medium" numberOfLines={1}>
            {title}
          </Text>
        ) : (
          title
        )}
        {typeof subtitle === 'string' ? (
          <Text variant="muted" numberOfLines={1}>
            {subtitle}
          </Text>
        ) : (
          subtitle
        )}
      </View>
      {end != null || endSub != null ? (
        <View className="items-end">
          {typeof end === 'string' ? <Text className="font-plex-medium">{end}</Text> : end}
          {typeof endSub === 'string' ? <Text variant="muted">{endSub}</Text> : endSub}
        </View>
      ) : null}
    </View>
  );
  return onPress ? (
    <Pressable onPress={onPress} accessibilityRole="button" accessibilityLabel={accessibilityLabel} className="active:opacity-70">
      {content}
    </Pressable>
  ) : (
    content
  );
}

// ---------------------------------------------------------------------------------------------
// Balances: money with its sign (+ = عليه, red; − = له, green), flour in words with the same colours.

/** `text`: the server's ready text (money_balance_text...) when there is one; the colour still comes from the number. */
export function MoneyText({ cents, className, plus, text }: { cents: number; className?: string; plus?: boolean; text?: string }) {
  return (
    <Text className={cn('font-plex-medium', cents > 0 && !plus ? 'text-destructive' : cents < 0 || plus ? 'text-success' : '', className)}>
      {text ?? formatMoney(cents / 100, { plus })}
    </Text>
  );
}

export function FlourText({ hundredths, className, text }: { hundredths: number; className?: string; text?: string }) {
  return (
    <Text className={cn(hundredths > 0 ? 'text-destructive' : hundredths < 0 ? 'text-success' : 'text-muted-foreground', className)}>
      {text ?? formatFlourBalance(hundredths / 100)}
    </Text>
  );
}

/** Small status badge (paid / unpaid / active...). */
export function Badge({ label, tone = 'neutral' }: { label: string; tone?: Tone }) {
  const { theme } = useTheme();
  const bg = { neutral: theme.s.well, success: theme.s.successSoft, danger: theme.s.dangerSoft, warning: theme.s.warningSoft }[tone];
  const fg = { neutral: 'text-muted-foreground', success: 'text-success', danger: 'text-destructive', warning: 'text-warning' }[tone];
  return (
    <View style={{ backgroundColor: bg }} className="self-start rounded-badge px-2 py-0.5">
      <Text className={cn('font-plex-medium text-[12px] leading-[18px]', fg)}>{label}</Text>
    </View>
  );
}

/** "ينتظر المزامنة": a small orange dot and muted text (one of the named orange places). */
export function PendingMark({ label = 'ينتظر المزامنة' }: { label?: string }) {
  return (
    <View className="flex-row items-center gap-1">
      <View className="bg-primary dark:bg-ring h-1.5 w-1.5 rounded-full" />
      <Text variant="muted">{label}</Text>
    </View>
  );
}

// ---------------------------------------------------------------------------------------------
// Forms

export function Field({
  label,
  error,
  hint,
  children,
  className,
}: {
  label: string;
  error?: string | null;
  hint?: string;
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <View className={cn('mb-4', className)}>
      <Text variant="label" className="mb-1.5">
        {label}
      </Text>
      {children}
      {error ? (
        <Text className="text-destructive mt-1 text-[13px]" accessibilityLiveRegion="polite">
          {error}
        </Text>
      ) : hint ? (
        <Text variant="muted" className="mt-1">
          {hint}
        </Text>
      ) : null}
    </View>
  );
}

/**
 * The number sits on the right and the unit on the left. Web: physical sides, because RN Web gives
 * inputs dir="auto" and digits turn the field LTR. Native: logical sides, because forceRTL swaps
 * left and right styles there.
 */
const UNIT_INPUT_STYLE = Platform.select<TextStyle>({
  web: { textAlign: 'right', paddingLeft: 64, paddingRight: 16 },
  default: { textAlign: 'right', writingDirection: 'ltr', paddingStart: 16, paddingEnd: 64 },
});

/** Emails, phone numbers and references: typed left to right, kept on the right like the other fields. */
export const LTR_INPUT = Platform.select<TextStyle>({
  web: { textAlign: 'right' },
  default: { textAlign: 'right', writingDirection: 'ltr' },
});

/** A numeric well with its unit on the end (شيكل / كغ), digits left to right. */
export function UnitInput({
  value,
  onChangeText,
  unit,
  label,
  invalid,
  autoFocus,
}: {
  value: string;
  onChangeText: (v: string) => void;
  unit: string;
  label: string;
  invalid?: boolean;
  autoFocus?: boolean;
}) {
  return (
    <View className="relative">
      <Input
        value={value}
        onChangeText={(t) => onChangeText(t.replace(/[^0-9.]/g, ''))}
        keyboardType="decimal-pad"
        inputMode="decimal"
        accessibilityLabel={label}
        autoFocus={autoFocus}
        className={cn('font-plex-semi text-[17px]', invalid && 'border-destructive')}
        style={UNIT_INPUT_STYLE}
        placeholder="0"
      />
      <Text variant="muted" className="absolute end-4 top-3.5" pointerEvents="none">
        {unit}
      </Text>
    </View>
  );
}

export function TextField({ invalid, className, ...props }: React.ComponentProps<typeof Input> & { invalid?: boolean }) {
  return <Input className={cn(invalid && 'border-destructive', className)} {...props} />;
}

/** A tinted note box: warning (amber), info (well), danger (soft red). */
export function Notice({
  tone = 'info',
  icon = Info,
  title,
  body,
  className,
}: {
  tone?: 'info' | 'warning' | 'danger';
  icon?: LucideIcon;
  title?: string;
  body: string;
  className?: string;
}) {
  const { theme } = useTheme();
  const bg = tone === 'warning' ? theme.s.warningSoft : tone === 'danger' ? theme.s.dangerSoft : theme.s.well;
  const fg = tone === 'warning' ? 'text-warning' : tone === 'danger' ? 'text-destructive' : 'text-muted-foreground';
  return (
    <View style={{ backgroundColor: bg }} className={cn('mb-4 flex-row gap-3 rounded-control p-4', className)}>
      <Icon as={icon} size={18} strokeWidth={1.5} className={cn('mt-0.5', fg)} />
      <View className="min-w-0 flex-1">
        {title ? <Text className={cn('font-plex-semi', fg)}>{title}</Text> : null}
        <Text className={cn('text-[13px] leading-[21px]', tone === 'info' ? 'text-muted-foreground' : fg)}>{body}</Text>
      </View>
    </View>
  );
}

/** Label / value pairs in a recessed well (movement details, settings offline). */
export function InfoList({ items, className }: { items: [string, React.ReactNode][]; className?: string }) {
  const { theme } = useTheme();
  return (
    <Well className={cn('mb-2 px-4', className)}>
      {items.map(([k, v], i) => (
        <View key={k} className="flex-row items-center justify-between gap-3 py-3" style={i ? { borderTopWidth: 1, borderTopColor: theme.s.hairline } : undefined}>
          <Text variant="muted" className="text-[14px]">
            {k}
          </Text>
          {typeof v === 'string' ? <Text className="font-plex-medium shrink text-end text-[14px]">{v}</Text> : v}
        </View>
      ))}
    </Well>
  );
}

/** Empty state with its first action (CLAUDE.md section 7). */
export function EmptyState({
  icon,
  title,
  body,
  action,
  onAction,
  actionIcon,
}: {
  icon: LucideIcon;
  title: string;
  body?: string;
  action?: string;
  onAction?: () => void;
  actionIcon?: LucideIcon;
}) {
  return (
    <View className="items-center px-4 py-10">
      <Well radius={32} style={{ width: 64, height: 64 }} className="mb-4 items-center justify-center">
        <Icon as={icon} size={26} strokeWidth={1.5} className="text-muted-foreground" />
      </Well>
      <Text variant="card" className="text-center">
        {title}
      </Text>
      {body ? (
        <Text variant="muted" className="mt-1 max-w-[300px] text-center text-[14px] leading-[22px]">
          {body}
        </Text>
      ) : null}
      {action ? (
        <Button variant="pill" className="mt-5 w-full max-w-[340px]" onPress={onAction}>
          {actionIcon ? <Icon as={actionIcon} size={18} /> : null}
          <Text>{action}</Text>
        </Button>
      ) : null}
    </View>
  );
}

/** Close (×) for sheets: 44pt target. */
export function CloseButton({ onPress }: { onPress: () => void }) {
  return (
    <Pressable onPress={onPress} accessibilityRole="button" accessibilityLabel="إغلاق" hitSlop={8} className="h-11 w-11 items-center justify-center rounded-full active:opacity-70">
      <Icon as={X} size={20} strokeWidth={1.5} className="text-muted-foreground" />
    </Pressable>
  );
}

/**
 * One row of a level-1 list card inside a FlatList: the card is drawn per item (rounded on the first
 * and last) so long lists stay virtualised and still read as one surface.
 */
export function ListItemFrame({ first, last, children }: { first: boolean; last: boolean; children: React.ReactNode }) {
  const { theme, solid } = useTheme();
  const L = theme.s.l1;
  return (
    <View
      style={{
        backgroundColor: solid ? L.fallback : L.fill,
        borderColor: theme.s.edge,
        borderLeftWidth: 1,
        borderRightWidth: 1,
        borderTopWidth: first ? 1 : 0,
        borderBottomWidth: last ? 1 : 0,
        borderTopLeftRadius: first ? 22 : 0,
        borderTopRightRadius: first ? 22 : 0,
        borderBottomLeftRadius: last ? 22 : 0,
        borderBottomRightRadius: last ? 22 : 0,
        paddingHorizontal: 16,
        paddingTop: first ? 4 : 0,
        paddingBottom: last ? 4 : 0,
        boxShadow: first ? `inset 0 1px 0 ${theme.s.edgeTop}` : undefined,
      }}>
      {children}
    </View>
  );
}

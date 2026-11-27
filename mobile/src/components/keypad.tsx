import { Delete } from 'lucide-react-native';
import * as React from 'react';
import { Pressable, View } from 'react-native';

import { solidStyle } from '@/components/glass';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { useTheme } from '@/state/theme';

const KEYS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '.', '0', 'del'] as const;

/** Appends a key to a decimal string: one dot, at most 2 decimals, at most 6 integer digits. */
export function pressKey(value: string, key: string): string {
  if (key === 'del') return value.slice(0, -1);
  if (key === '.') return value.includes('.') ? value : `${value || '0'}.`;
  const [int, dec] = value.split('.');
  if (dec !== undefined && dec.length >= 2) return value;
  if (dec === undefined && int.length >= 6) return value;
  return value === '0' ? key : value + key;
}

/**
 * The sale keypad: solid keys (not glass) inside the sheet, laid out left to right like a phone dial
 * even in RTL. 52pt keys, 8pt gaps.
 */
export function Keypad({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  const { theme } = useTheme();
  // Rows are `row-reverse`: under RTL (native forceRTL and web dir="rtl") that lays 1 2 3 left to right.
  const rows = [KEYS.slice(0, 3), KEYS.slice(3, 6), KEYS.slice(6, 9), KEYS.slice(9, 12)];
  return (
    <View className="mb-4 gap-2">
      {rows.map((row, r) => (
        <View key={r} className="gap-2" style={{ flexDirection: 'row-reverse' }}>
          {row.map((k) => (
            <Pressable
              key={k}
              onPress={() => onChange(pressKey(value, k))}
              accessibilityRole="button"
              accessibilityLabel={k === 'del' ? 'حذف رقم' : k === '.' ? 'فاصلة عشرية' : k}
              className="h-[52px] flex-1 items-center justify-center rounded-control active:opacity-70"
              style={solidStyle(theme)}>
              {k === 'del' ? <Icon as={Delete} size={22} strokeWidth={1.5} className="text-foreground" /> : <Text className="font-plex-medium text-[22px] leading-[30px]">{k}</Text>}
            </Pressable>
          ))}
        </View>
      ))}
    </View>
  );
}

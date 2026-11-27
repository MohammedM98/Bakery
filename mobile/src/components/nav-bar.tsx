import { router, type Href } from 'expo-router';
import type { BottomTabBarProps } from 'expo-router/tabs';
import { Ellipsis, History, House, Plus, Store, Users, type LucideIcon } from 'lucide-react-native';
import * as React from 'react';
import { Pressable, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Surface } from '@/components/glass';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { cn } from '@/lib/utils';
import { useTheme } from '@/state/theme';

export interface NavItem {
  /** Route name in the Tabs navigator, or 'fab' for the raised orange action. */
  key: string;
  label: string;
  icon: LucideIcon;
  href?: Href;
  onPress?: () => void;
}

export const NAV_ICONS = { home: House, customers: Users, plus: Plus, log: History, more: Ellipsis, store: Store };

/**
 * Floating glass nav bar (level 4), option B: the action in the middle is a raised orange square
 * (بيع for the bakery, إضافة for the platform owner). The active item is orange: icon, name and a dot.
 * Only items the user may open are passed in, so a staff member sees fewer.
 */
export function FloatingNav({ state, items }: BottomTabBarProps & { items: NavItem[] }) {
  const insets = useSafeAreaInsets();
  const { theme } = useTheme();
  const active = state.routes[state.index]?.name ?? '';
  return (
    <View pointerEvents="box-none" style={{ position: 'absolute', left: 16, right: 16, bottom: 16 + insets.bottom, alignItems: 'center' }}>
      <Surface level="l4" radius={26} className="h-[68px] w-full max-w-[520px] flex-row items-center justify-around px-1.5" role="navigation" aria-label="التنقل">
        {items.map((it) => {
          if (it.key === 'fab')
            return (
              <Pressable key="fab" onPress={it.onPress} accessibilityRole="button" accessibilityLabel={it.label} className="flex-1 items-center active:opacity-90">
                <View
                  className="bg-primary -mt-8 h-14 w-14 items-center justify-center rounded-tile"
                  style={{ borderWidth: 4, borderColor: theme.c.background, boxShadow: theme.s.fabShadow }}>
                  <Icon as={it.icon} size={26} strokeWidth={2} className="text-primary-foreground" />
                </View>
                <Text className="font-plex-medium text-[12px] leading-[18px]">{it.label}</Text>
              </Pressable>
            );
          const on = active === it.key || active.startsWith(`${it.key}/`);
          return (
            <Pressable
              key={it.key}
              onPress={() => (it.onPress ? it.onPress() : it.href ? router.navigate(it.href) : undefined)}
              accessibilityRole="tab"
              accessibilityState={{ selected: on }}
              accessibilityLabel={it.label}
              className="h-full flex-1 items-center justify-center gap-0.5">
              <Icon as={it.icon} size={20} strokeWidth={1.5} color={on ? theme.s.accentText : theme.c.mutedForeground} />
              <Text className={cn('font-plex-medium text-[12px] leading-[18px]')} style={{ color: on ? theme.s.accentText : theme.c.mutedForeground }}>
                {it.label}
              </Text>
              {on ? <View className="absolute bottom-1.5 h-[5px] w-[5px] rounded-full" style={{ backgroundColor: theme.s.accentText }} /> : null}
            </Pressable>
          );
        })}
      </Surface>
    </View>
  );
}

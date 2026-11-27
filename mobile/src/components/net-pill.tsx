import { router } from 'expo-router';
import * as React from 'react';
import { Pressable, View } from 'react-native';

import { Surface } from '@/components/glass';
import { Text } from '@/components/ui/text';
import { useApp } from '@/state/app';
import { useTheme } from '@/state/theme';

/**
 * Connection and sync status, on every bakery screen: green "متصل", amber "دون إنترنت", and the
 * number of operations waiting (orange dot, one of the named orange places). Opens the sync screen.
 */
export function NetPill() {
  const { state, pendingCount } = useApp();
  const { theme } = useTheme();
  const online = state.online;
  const dot = pendingCount > 0 ? theme.s.accentText : online ? theme.c.success : theme.c.warning;
  const text = online ? (pendingCount ? `ينتظر ${pendingCount}` : 'متصل') : pendingCount ? `دون إنترنت، ينتظر ${pendingCount}` : 'دون إنترنت';
  return (
    <Pressable
      onPress={() => router.push('/more/sync')}
      accessibilityRole="button"
      accessibilityLabel={`${text}. فتح المزامنة`}
      hitSlop={8}
      className="self-end active:opacity-80">
      {online ? (
        <Surface level="l2" radius={999} blur={false} className="h-7 flex-row items-center gap-1.5 px-3">
          <View className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: dot }} />
          <Text className="font-plex-medium text-[12px] leading-[18px]">{text}</Text>
        </Surface>
      ) : (
        <View className="h-7 flex-row items-center gap-1.5 rounded-full px-3" style={{ backgroundColor: theme.s.warningSoft }}>
          <View className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: dot }} />
          <Text className="text-warning font-plex-medium text-[12px] leading-[18px]">{text}</Text>
        </View>
      )}
    </Pressable>
  );
}

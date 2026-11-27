import * as React from 'react';
import { KeyboardAvoidingView, Modal, Platform, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { CloseButton } from '@/components/common';
import { Surface } from '@/components/glass';
import { Text } from '@/components/ui/text';
import { useTheme } from '@/state/theme';

/**
 * Bottom sheet (level 3): forms and details slide up over a scrim. The scrim closes it, so does the
 * × and the system back gesture (Modal onRequestClose). RN's Modal moves screen-reader focus inside.
 */
export function BottomSheet({
  open,
  onClose,
  title,
  aside,
  children,
}: {
  open: boolean;
  onClose: () => void;
  title: string;
  /** A small note or chip on the end of the title row (e.g. "عليه 120.00 شيكل"). */
  aside?: React.ReactNode;
  children: React.ReactNode;
}) {
  const { theme } = useTheme();
  const insets = useSafeAreaInsets();
  return (
    <Modal visible={open} transparent animationType="fade" onRequestClose={onClose} statusBarTranslucent>
      <View style={StyleSheet.absoluteFill}>
        <Pressable
          style={[StyleSheet.absoluteFill, { backgroundColor: theme.s.scrim }]}
          onPress={onClose}
          accessibilityRole="button"
          accessibilityLabel="إغلاق"
        />
        <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={{ flex: 1, justifyContent: 'flex-end' }} pointerEvents="box-none">
          <Surface
            level="l3"
            radius={28}
            style={{ borderBottomLeftRadius: 0, borderBottomRightRadius: 0, borderBottomWidth: 0, maxHeight: '92%' }}
            accessibilityViewIsModal
            role="dialog"
            aria-label={title}>
            <View className="w-full max-w-[560px] self-center">
              <View className="mb-2 mt-2 h-[5px] w-10 self-center rounded-full bg-foreground/10 dark:bg-white/15" />
              <View className="mb-2 flex-row items-center gap-3 px-5">
                <Text variant="card" className="flex-1" role="heading">
                  {title}
                </Text>
                {aside}
                <CloseButton onPress={onClose} />
              </View>
            </View>
            <ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={{ paddingHorizontal: 20, paddingBottom: 20 + insets.bottom }} showsVerticalScrollIndicator={false}>
              <View className="w-full max-w-[560px] self-center">{children}</View>
            </ScrollView>
          </Surface>
        </KeyboardAvoidingView>
      </View>
    </Modal>
  );
}

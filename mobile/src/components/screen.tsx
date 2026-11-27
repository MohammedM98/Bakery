import { router } from 'expo-router';
import { ChevronRight, ShieldOff } from 'lucide-react-native';
import * as React from 'react';
import { FlatList, Pressable, ScrollView, View, type ScrollViewProps } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { EmptyState } from '@/components/common';
import { ScreenBackground, Surface } from '@/components/glass';
import { NetPill } from '@/components/net-pill';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { cn } from '@/lib/utils';

/** Room under the floating nav bar (68 + 16 margin + breathing space) so nothing hides behind it. */
export const NAV_CLEARANCE = 124;

export function useScreenPadding(withNav = true) {
  const insets = useSafeAreaInsets();
  return { paddingTop: insets.top + 16, paddingBottom: (withNav ? NAV_CLEARANCE : 32) + insets.bottom, paddingHorizontal: 20 };
}

/** Level 0 background + safe areas + a scroll view with the screen edge of 20. */
export function Screen({
  children,
  withNav = true,
  scroll = true,
  net = true,
  contentClassName,
  ...props
}: ScrollViewProps & { withNav?: boolean; scroll?: boolean; net?: boolean; contentClassName?: string }) {
  const pad = useScreenPadding(withNav);
  return (
    <ScreenBackground>
      {scroll ? (
        <ScrollView contentContainerStyle={pad} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false} {...props}>
          <View className={cn('w-full max-w-[560px] self-center', contentClassName)}>
            {net ? <NetPill /> : null}
            {children}
          </View>
        </ScrollView>
      ) : (
        <View style={[{ flex: 1 }, pad]}>
          {net ? <NetPill /> : null}
          {children}
        </View>
      )}
    </ScreenBackground>
  );
}

/** Round glass icon button, 44pt. */
export function IconButton({
  icon,
  label,
  onPress,
  className,
  children,
}: {
  icon: React.ComponentProps<typeof Icon>['as'];
  label: string;
  onPress?: () => void;
  className?: string;
  children?: React.ReactNode;
}) {
  return (
    <Pressable onPress={onPress} accessibilityRole="button" accessibilityLabel={label} className="active:opacity-80">
      <Surface level="l2" radius={22} blur={false} style={{ width: 44, height: 44 }} className={cn('items-center justify-center', className)}>
        <Icon as={icon} size={20} strokeWidth={1.5} className="text-foreground" />
        {children}
      </Surface>
    </Pressable>
  );
}

/** Screen title row: optional back (a right-pointing chevron in RTL), title + subtitle, end slot. */
export function PageHeader({
  title,
  subtitle,
  back,
  end,
}: {
  title: string;
  subtitle?: string;
  back?: boolean;
  end?: React.ReactNode;
}) {
  return (
    <View className="mb-5 mt-2 flex-row items-center gap-3">
      {back ? <IconButton icon={ChevronRight} label="رجوع" onPress={() => (router.canGoBack() ? router.back() : router.replace('/'))} /> : null}
      <View className="min-w-0 flex-1">
        <Text variant="title" numberOfLines={1}>
          {title}
        </Text>
        {subtitle ? <Text variant="muted">{subtitle}</Text> : null}
      </View>
      {end ? <View className="flex-row items-center gap-2">{end}</View> : null}
    </View>
  );
}

/** Section title with an optional "عرض الكل" style link on the end. */
export function SectionTitle({ title, action, onAction }: { title: string; action?: string; onAction?: () => void }) {
  return (
    <View className="mb-3 mt-1 flex-row items-center justify-between">
      <Text variant="section">{title}</Text>
      {action ? (
        <Text onPress={onAction} accessibilityRole="link" className="text-muted-foreground px-1 py-2 text-[14px]">
          {action}
        </Text>
      ) : null}
    </View>
  );
}

/** A virtualised list screen (FlatList for long lists, ui-ux-pro-max React Native rule) on level 0. */
export function ListScreen<T>({
  header,
  data,
  renderItem,
  keyExtractor,
  empty,
  footer,
}: {
  header: React.ReactElement;
  data: T[];
  renderItem: (item: T, index: number) => React.ReactElement;
  keyExtractor: (item: T) => string;
  empty?: React.ReactElement;
  footer?: React.ReactElement;
}) {
  const pad = useScreenPadding(true);
  return (
    <ScreenBackground>
      <FlatList
        data={data}
        keyExtractor={keyExtractor}
        renderItem={({ item, index }) => <View className="w-full max-w-[560px] self-center">{renderItem(item, index)}</View>}
        ListHeaderComponent={
          <View className="w-full max-w-[560px] self-center">
            <NetPill />
            {header}
          </View>
        }
        ListEmptyComponent={empty}
        ListFooterComponent={footer}
        contentContainerStyle={pad}
        keyboardShouldPersistTaps="handled"
        showsVerticalScrollIndicator={false}
        initialNumToRender={20}
      />
    </ScreenBackground>
  );
}

/** Shown when a screen's permission is missing (the server answers 403 for the same request). */
export function NotAllowed() {
  return (
    <Screen>
      <EmptyState icon={ShieldOff} title="لا تملك صلاحية هذه الصفحة" body="اطلب من صاحب المخبز منحك الصلاحية" />
    </Screen>
  );
}

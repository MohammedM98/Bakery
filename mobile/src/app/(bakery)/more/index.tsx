import { router, type Href } from 'expo-router';
import { ChevronLeft, Landmark, LogOut, RefreshCw, Settings, Shield, ShoppingBag, User, type LucideIcon } from 'lucide-react-native';
import * as React from 'react';
import { View } from 'react-native';

import { Avatar, ListCard, RoundIcon, Row } from '@/components/common';
import { DevTools } from '@/components/dev-tools';
import { Screen } from '@/components/screen';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { useApp } from '@/state/app';
import { useTheme } from '@/state/theme';

interface Item {
  label: string;
  icon: LucideIcon;
  href?: Href;
  onPress?: () => void;
  online?: boolean;
  badge?: number;
  danger?: boolean;
}

/** Permissions-aware menu (option A): what needs the internet is shown disabled while offline. */
export default function More() {
  const app = useApp();
  const { theme } = useTheme();
  const work: Item[] = [
    ...(app.can('view-sales') ? [{ label: 'المبيعات', icon: ShoppingBag, href: '/more/sales' as Href }] : []),
    { label: 'المزامنة', icon: RefreshCw, href: '/more/sync', badge: app.pendingCount },
    ...(app.can('view-banks') ? [{ label: 'البنوك والمحافظ', icon: Landmark, href: '/more/banks' as Href }] : []),
  ];
  const admin: Item[] = [
    ...(app.can('manage-users') ? [{ label: 'المستخدمون', icon: Shield, href: '/more/users' as Href, online: true }] : []),
    ...(app.can('manage-settings') ? [{ label: 'الإعدادات والأسعار', icon: Settings, href: '/more/settings' as Href }] : []),
    { label: 'الملف الشخصي', icon: User, href: '/more/profile' },
    { label: 'تسجيل الخروج', icon: LogOut, danger: true, onPress: () => (app.logout(), router.replace('/login')) },
  ];

  const group = (title: string, items: Item[]) => (
    <>
      <Text variant="muted" className="mb-2 mt-1">
        {title}
      </Text>
      <ListCard className="mb-5">
        {items.map((it, i) => {
          const off = it.online && !app.state.online;
          return (
            <Row
              key={it.label}
              first={i === 0}
              onPress={off ? undefined : (it.onPress ?? (() => it.href && router.push(it.href)))}
              start={<RoundIcon icon={it.icon} tone={it.danger ? 'danger' : 'neutral'} flip={it.icon === LogOut} />}
              title={<Text className={it.danger ? 'text-destructive font-plex-medium' : off ? 'text-muted-foreground font-plex-medium' : 'font-plex-medium'}>{it.label}</Text>}
              end={
                off ? (
                  <Text variant="muted">يحتاج إنترنت</Text>
                ) : it.badge ? (
                  <View className="h-6 min-w-6 items-center justify-center rounded-full px-2" style={{ backgroundColor: theme.s.warningSoft }}>
                    <Text className="text-warning font-plex-semi text-[12px]">{it.badge}</Text>
                  </View>
                ) : it.danger ? null : (
                  <Icon as={ChevronLeft} size={18} strokeWidth={1.5} className="text-muted-foreground" />
                )
              }
            />
          );
        })}
      </ListCard>
    </>
  );

  return (
    <Screen>
      <View className="mb-5 mt-2 flex-row items-center gap-3">
        <Avatar name={app.user?.name ?? ''} size={52} />
        <View className="flex-1">
          <Text variant="title">{app.user?.name}</Text>
          <Text variant="muted">
            {app.user?.role === 'bakery_owner' ? 'صاحب' : 'موظف في'} {app.state.bakery.name}
          </Text>
        </View>
      </View>
      {group('العمل', work)}
      {group('الحساب', admin)}
      {__DEV__ || process.env.EXPO_PUBLIC_DEV_MENU === '1' ? <DevTools /> : null}
    </Screen>
  );
}

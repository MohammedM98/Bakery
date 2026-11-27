import { Redirect } from 'expo-router';
import { Tabs } from 'expo-router/tabs';
import * as React from 'react';

import { FloatingNav, NAV_ICONS, type NavItem } from '@/components/nav-bar';
import { useSheets } from '@/components/sheets';
import { useApp } from '@/state/app';

/**
 * The bakery app (owner and staff): /panel on the website. Same gates as the website's middleware:
 * signed in and a bakery role (the root layout shows "subscription expired" and "no permissions");
 * each screen then checks its own permission.
 */
export default function BakeryLayout() {
  const { user, can } = useApp();
  const sheets = useSheets();

  if (!user) return <Redirect href="/login" />;
  if (user.role === 'super_admin') return <Redirect href="/bakeries" />;

  const items: NavItem[] = [
    { key: 'index', href: '/', label: 'الرئيسية', icon: NAV_ICONS.home },
    ...(can('view-customers') ? [{ key: 'customers', href: '/customers', label: 'الزبائن', icon: NAV_ICONS.customers } as NavItem] : []),
    ...(can('record-sale') ? [{ key: 'fab', label: 'بيع', icon: NAV_ICONS.plus, onPress: () => sheets.open({ kind: 'sale' }) } as NavItem] : []),
    ...(can('view-sales-log') ? [{ key: 'log', href: '/log', label: 'السجل', icon: NAV_ICONS.log } as NavItem] : []),
    { key: 'more', href: '/more', label: 'المزيد', icon: NAV_ICONS.more },
  ];

  return (
    <Tabs
      screenOptions={{ headerShown: false, sceneStyle: { backgroundColor: 'transparent' } }}
      tabBar={(props) => <FloatingNav {...props} items={items} />}>
      <Tabs.Screen name="index" />
      <Tabs.Screen name="customers" />
      <Tabs.Screen name="log" />
      <Tabs.Screen name="more" />
    </Tabs>
  );
}

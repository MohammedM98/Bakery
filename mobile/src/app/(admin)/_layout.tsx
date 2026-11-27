import { Redirect } from 'expo-router';
import { Tabs } from 'expo-router/tabs';
import * as React from 'react';

import { FloatingNav, NAV_ICONS, type NavItem } from '@/components/nav-bar';
import { useSheets } from '@/components/sheets';
import { useApp } from '@/state/app';

/** Platform owner (/admin): three items, the raised action adds a bakery. Everything here is online only. */
export default function AdminLayout() {
  const { user } = useApp();
  const sheets = useSheets();
  if (!user) return <Redirect href="/login" />;
  if (user.role !== 'super_admin') return <Redirect href="/" />;
  const items: NavItem[] = [
    { key: 'bakeries', href: '/bakeries', label: 'المخابز', icon: NAV_ICONS.store },
    { key: 'fab', label: 'إضافة', icon: NAV_ICONS.plus, onPress: () => sheets.open({ kind: 'bakery' }) },
    { key: 'account', href: '/account', label: 'المزيد', icon: NAV_ICONS.more },
  ];
  return (
    <Tabs screenOptions={{ headerShown: false, sceneStyle: { backgroundColor: 'transparent' } }} tabBar={(props) => <FloatingNav {...props} items={items} />}>
      <Tabs.Screen name="bakeries" />
      <Tabs.Screen name="account" />
    </Tabs>
  );
}

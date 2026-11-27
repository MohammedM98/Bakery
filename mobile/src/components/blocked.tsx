import { Clock, LogOut, RefreshCw, ShieldOff, User } from 'lucide-react-native';
import * as React from 'react';

import { useLogout } from '@/components/confirm';
import { View } from 'react-native';

import { EmptyState, RoundIcon } from '@/components/common';
import { ProfileForm } from '@/components/profile-form';
import { Screen } from '@/components/screen';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { formatDate } from '@/lib/format';
import { useApp } from '@/state/app';

// Shown by the root layout in place of the navigator for a bakery user whose subscription ended or who
// has no permission at all. Signing out returns to the navigator, which opens the login.

/** Subscription ended (option A): full screen, no navigation; data is kept. */
export function ExpiredScreen() {
  const app = useApp();
  const logout = useLogout();
  return (
    <Screen withNav={false} net={false}>
      <View className="flex-1 items-center justify-center pt-24">
        <RoundIcon icon={Clock} tone="danger" size={64} />
        <Text variant="card" className="mt-4 text-center">
          انتهى اشتراك {app.me?.bakery?.name}
        </Text>
        <Text variant="muted" className="mb-6 mt-1 max-w-[300px] text-center text-[14px] leading-[22px]">
          {app.me?.subscription?.status === 'active' && app.me.subscription.expires_at ? `انتهى في ${formatDate(app.me.subscription.expires_at)}. ` : 'الاشتراك موقوف. '}تواصل مع مالك المنصة لتجديده. بياناتك محفوظة ولن تُحذف
        </Text>
        <Button className="mb-2 w-full" disabled={!app.state.online || app.state.syncing} onPress={() => app.syncNow()}>
          <Icon as={RefreshCw} size={18} />
          <Text>{app.state.syncing ? 'جارٍ التحقق' : 'تحقّق من التجديد'}</Text>
        </Button>
        <Button variant="glass" className="w-full" onPress={() => logout()}>
          <Text>تسجيل الخروج</Text>
        </Button>
      </View>
    </Screen>
  );
}

/** A staff member with no permission at all (website: panel.no-permissions). */
export function NoPermissionsScreen() {
  const logout = useLogout();
  const [profile, setProfile] = React.useState(false);
  if (profile) return <ProfileForm onBack={() => setProfile(false)} />;
  return (
    <Screen withNav={false} net={false}>
      <View className="flex-1 justify-center pt-16">
        <EmptyState icon={ShieldOff} title="لا توجد صلاحيات بعد" body="لم يحدّد صاحب المخبز ما تستطيع فعله في هذا الحساب. اطلب منه منحك الصلاحيات؛ تظهر هنا عند المزامنة التالية" />
        <Button variant="glass" className="mb-2 w-full" onPress={() => setProfile(true)}>
          <Icon as={User} size={18} />
          <Text>الملف الشخصي</Text>
        </Button>
        <Button variant="ghost" className="w-full" onPress={() => logout()}>
          <Icon as={LogOut} size={18} className="-scale-x-100" />
          <Text>تسجيل الخروج</Text>
        </Button>
      </View>
    </Screen>
  );
}

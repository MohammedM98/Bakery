import { router } from 'expo-router';
import { LogOut, ShieldOff, User } from 'lucide-react-native';
import * as React from 'react';
import { View } from 'react-native';

import { EmptyState } from '@/components/common';
import { Screen } from '@/components/screen';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { useApp } from '@/state/app';

/** A staff member with no permission at all (website: panel.no-permissions). */
export default function NoPermissions() {
  const app = useApp();
  return (
    <Screen withNav={false}>
      <View className="flex-1 justify-center pt-16">
        <EmptyState icon={ShieldOff} title="لا توجد صلاحيات بعد" body={`مرحبًا ${app.user?.name ?? ''}. لم يحدّد صاحب المخبز ما تستطيع فعله. اطلب منه منحك الصلاحيات، ثم ادخل من جديد`} />
        <Button variant="glass" className="mb-2 w-full" onPress={() => router.push('/more/profile')}>
          <Icon as={User} size={18} />
          <Text>الملف الشخصي</Text>
        </Button>
        <Button variant="ghost" className="w-full" onPress={() => (app.logout(), router.replace('/login'))}>
          <Icon as={LogOut} size={18} />
          <Text>تسجيل الخروج</Text>
        </Button>
      </View>
    </Screen>
  );
}

import { Redirect, router } from 'expo-router';
import { Clock, Phone } from 'lucide-react-native';
import * as React from 'react';
import { Linking, View } from 'react-native';

import { RoundIcon } from '@/components/common';
import { Screen } from '@/components/screen';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { formatDate } from '@/lib/format';
import { useApp } from '@/state/app';

/** Subscription ended (option A): full screen, no navigation; data is kept. */
export default function Expired() {
  const app = useApp();
  if (!app.user) return <Redirect href="/login" />;
  if (!app.state.subscriptionExpired) return <Redirect href="/" />;
  return (
    <Screen withNav={false} net={false}>
      <View className="flex-1 items-center justify-center pt-24">
        <RoundIcon icon={Clock} tone="danger" size={64} />
        <Text variant="card" className="mt-4 text-center">
          انتهى اشتراك {app.state.bakery.name}
        </Text>
        <Text variant="muted" className="mb-6 mt-1 max-w-[300px] text-center text-[14px] leading-[22px]">
          انتهى في {formatDate('2026-11-20')}. تواصل مع مالك المنصة لتجديده. بياناتك محفوظة ولن تُحذف
        </Text>
        <Button className="mb-2 w-full" onPress={() => Linking.openURL('tel:+970599000000').catch(() => {})}>
          <Icon as={Phone} size={18} />
          <Text>تواصل لتجديد الاشتراك</Text>
        </Button>
        <Button variant="glass" className="w-full" onPress={() => (app.logout(), router.replace('/login'))}>
          <Text>تسجيل الخروج</Text>
        </Button>
      </View>
    </Screen>
  );
}

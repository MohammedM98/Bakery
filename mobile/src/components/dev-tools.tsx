import { router } from 'expo-router';
import * as React from 'react';
import { View } from 'react-native';

import { Chip, ListCard, Row } from '@/components/common';
import { Switch } from '@/components/ui/switch';
import { Text } from '@/components/ui/text';
import { homeFor } from '@/lib/access';
import { MOCK_USERS, type MockUserKey } from '@/mock/data';
import { useApp } from '@/state/app';
import { useTheme } from '@/state/theme';

const USERS: [MockUserKey, string][] = [
  ['owner', 'صاحب المخبز'],
  ['staff', 'موظف بصلاحيات قليلة'],
  ['noPermissions', 'موظف بلا صلاحيات'],
  ['admin', 'مالك المنصة'],
];

/**
 * Development only (__DEV__ or EXPO_PUBLIC_DEV_MENU=1): switch the mock user to see the permission
 * model, and flip the mock connection, subscription and "new phone" states. Removed with the mocks.
 */
export function DevTools() {
  const app = useApp();
  const { preference, setPreference } = useTheme();
  const toggle = (label: string, value: boolean, set: (v: boolean) => void, first = false) => (
    <Row first={first} title={label} end={<Switch checked={value} onCheckedChange={set} accessibilityLabel={label} />} />
  );
  return (
    <View className="mb-5">
      <Text variant="muted" className="mb-2 mt-1">
        أدوات التجربة (بيانات تجريبية)
      </Text>
      <ListCard className="py-3">
        <Text variant="label" className="mb-2">
          المستخدم
        </Text>
        <View className="mb-2 flex-row flex-wrap gap-2">
          {USERS.map(([k, l]) => (
            <Chip
              key={k}
              label={l}
              on={app.state.userKey === k}
              onPress={() => {
                app.switchUser(k);
                router.replace(homeFor(MOCK_USERS[k]));
              }}
            />
          ))}
        </View>
        {toggle('متصل بالإنترنت', app.state.online, app.setOnline, true)}
        {toggle('الاشتراك منتهٍ', app.state.subscriptionExpired, app.setExpired)}
        {toggle('جهاز جديد بلا بيانات', !app.state.hasLocalData, (v) => app.setHasLocalData(!v))}
        {toggle('الوضع الداكن', preference === 'dark', (v) => setPreference(v ? 'dark' : 'light'))}
      </ListCard>
    </View>
  );
}

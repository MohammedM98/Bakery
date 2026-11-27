import * as React from 'react';
import { View } from 'react-native';

import { DevTools } from '@/components/dev-tools';
import { ProfileForm } from '@/components/profile-form';

/** The platform owner's "more": profile, theme, sign out (and the mock tools in development). */
export default function Account() {
  return (
    <ProfileForm back={false}>
      {__DEV__ || process.env.EXPO_PUBLIC_DEV_MENU === '1' ? (
        <View className="mt-6">
          <DevTools />
        </View>
      ) : null}
    </ProfileForm>
  );
}

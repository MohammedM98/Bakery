import '@/global.css';

import { IBMPlexSansArabic_400Regular } from '@expo-google-fonts/ibm-plex-sans-arabic/400Regular';
import { IBMPlexSansArabic_500Medium } from '@expo-google-fonts/ibm-plex-sans-arabic/500Medium';
import { IBMPlexSansArabic_600SemiBold } from '@expo-google-fonts/ibm-plex-sans-arabic/600SemiBold';
import { PortalHost } from '@rn-primitives/portal';
import { useFonts } from 'expo-font';
import { Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import * as React from 'react';
import { I18nManager, Platform, StyleSheet, View } from 'react-native';

import { ExpiredScreen, NoPermissionsScreen } from '@/components/blocked';
import { ConfirmProvider } from '@/components/confirm';
import { SheetHost } from '@/components/sheets';
import { ToastProvider } from '@/components/toast';
import { AppProvider, useApp } from '@/state/app';
import { ThemeProvider, useTheme } from '@/state/theme';

export { ErrorBoundary } from 'expo-router';

// Arabic only, always right to left (CLAUDE.md section 13). On iOS/Android forceRTL takes effect from
// the next launch; app.json `extra.supportsRTL/forcesRTL` makes the first launch RTL as well.
I18nManager.allowRTL(true);
I18nManager.forceRTL(true);
if (Platform.OS === 'web' && typeof document !== 'undefined') {
  document.documentElement.lang = 'ar';
  document.documentElement.dir = 'rtl';
}

SplashScreen.preventAutoHideAsync().catch(() => {});

function Shell() {
  const { theme } = useTheme();
  const { user, state } = useApp();
  // The website's `subscription.active` gate and its "no permissions" page, drawn over the navigator.
  // The navigator stays mounted (router calls keep working); a group layout that renders a plain screen
  // instead of its navigator loops in expo-router, so the gate is not inside (bakery)/_layout.
  const bakeryUser = user && user.role !== 'super_admin';
  // The saved copy and token are read first, so a signed-in phone never flashes the login screen.
  if (!state.ready) return null;
  return (
    <>
      <StatusBar style={theme.scheme === 'dark' ? 'light' : 'dark'} />
      <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: theme.c.background } }} />
      {bakeryUser && (state.subscriptionExpired || user.permissions.length === 0) ? (
        <View style={StyleSheet.absoluteFill}>{state.subscriptionExpired ? <ExpiredScreen /> : <NoPermissionsScreen />}</View>
      ) : null}
    </>
  );
}

export default function RootLayout() {
  const [loaded] = useFonts({ IBMPlexSansArabic_400Regular, IBMPlexSansArabic_500Medium, IBMPlexSansArabic_600SemiBold });
  React.useEffect(() => {
    if (loaded) SplashScreen.hideAsync().catch(() => {});
  }, [loaded]);
  if (!loaded) return null;

  return (
    <ThemeProvider>
      <AppProvider>
        <ToastProvider>
          <ConfirmProvider>
            <SheetHost>
              <Shell />
              <PortalHost />
            </SheetHost>
          </ConfirmProvider>
        </ToastProvider>
      </AppProvider>
    </ThemeProvider>
  );
}

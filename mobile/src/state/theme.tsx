import { useColorScheme as useNativewindScheme } from 'nativewind';
import * as React from 'react';
import { AccessibilityInfo, Platform, useColorScheme } from 'react-native';

import { makeTheme, type Scheme, type Theme } from '@/theme';

export type SchemePreference = 'system' | 'light' | 'dark';

interface ThemeModel {
  theme: Theme;
  preference: SchemePreference;
  setPreference: (p: SchemePreference) => void;
  /** Solid fallbacks instead of blur: the user's Reduce Transparency setting, or Android (see README). */
  solid: boolean;
}

const ThemeContext = React.createContext<ThemeModel | null>(null);

function urlScheme(): SchemePreference | null {
  if (Platform.OS !== 'web' || typeof window === 'undefined') return null;
  const t = new URLSearchParams(window.location.search).get('theme');
  return t === 'dark' || t === 'light' ? t : null;
}

export function ThemeProvider({ children }: { children: React.ReactNode }) {
  const system = useColorScheme();
  const { setColorScheme } = useNativewindScheme();
  const [preference, setPreference] = React.useState<SchemePreference>(() => urlScheme() ?? 'system');
  const [reduceTransparency, setReduceTransparency] = React.useState(false);

  React.useEffect(() => {
    AccessibilityInfo.isReduceTransparencyEnabled?.().then(setReduceTransparency).catch(() => {});
    const sub = AccessibilityInfo.addEventListener?.('reduceTransparencyChanged', setReduceTransparency);
    return () => sub?.remove();
  }, []);

  const scheme: Scheme = preference === 'system' ? (system === 'dark' ? 'dark' : 'light') : preference;
  React.useEffect(() => {
    setColorScheme(scheme);
  }, [scheme, setColorScheme]);

  const value = React.useMemo<ThemeModel>(
    () => ({
      theme: makeTheme(scheme),
      preference,
      setPreference,
      // Android: expo-blur needs a BlurTargetView around the blurred content; until that is wired on a
      // real device the prototype's "weak Android" fills are used there (README, "needs a device").
      solid: reduceTransparency || Platform.OS === 'android',
    }),
    [scheme, preference, reduceTransparency],
  );

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useTheme(): ThemeModel {
  const v = React.useContext(ThemeContext);
  if (!v) throw new Error('useTheme outside ThemeProvider');
  return v;
}

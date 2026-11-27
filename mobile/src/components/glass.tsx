import { BlurView } from 'expo-blur';
import { LinearGradient } from 'expo-linear-gradient';
import * as React from 'react';
import { StyleSheet, View, type StyleProp, type ViewProps, type ViewStyle } from 'react-native';
import Svg, { Circle, Defs, Ellipse, Pattern, RadialGradient, Rect, Stop } from 'react-native-svg';

import { useTheme } from '@/state/theme';
import type { Level } from '@/theme';

/**
 * The frosted surface levels (docs/design/app/prototype-frosted.html, "الأسطح والعمق"):
 *  l1 card: translucent fill + hairline edge + top sheen + soft shadow, never blurred (lists scroll)
 *  l2 raised: buttons and the top bar; l3 bottom sheet; l4 floating nav and toasts. 2–4 blur when allowed.
 * Text always sits on level 1 or higher. Inside glass use <Well> or <Solid>, never another glass layer.
 */
export function Surface({
  level = 'l1',
  radius,
  style,
  children,
  blur,
  ...props
}: ViewProps & { level?: Level; radius?: number; blur?: boolean }) {
  const { theme, solid } = useTheme();
  const L = theme.s[level];
  const r = radius ?? L.radius;
  const blurred = !solid && L.blur > 0 && blur !== false;
  return (
    <View
      style={[
        {
          borderRadius: r,
          boxShadow: `inset 0 1px 0 ${theme.s.edgeTop}, ${L.shadow}`,
          backgroundColor: blurred ? undefined : solid ? L.fallback : L.fill,
          borderWidth: 1,
          borderColor: theme.s.edge,
        },
        style,
      ]}
      {...props}>
      {blurred ? (
        <View style={[StyleSheet.absoluteFill, { borderRadius: r, overflow: 'hidden' }]} pointerEvents="none">
          <BlurView intensity={L.blur} tint={theme.scheme === 'dark' ? 'dark' : 'light'} style={StyleSheet.absoluteFill} />
          <View style={[StyleSheet.absoluteFill, { backgroundColor: L.fill }]} />
        </View>
      ) : null}
      {children}
    </View>
  );
}

/** A recessed well: fields, segmented tracks, icon circles. */
export function Well({ style, radius = 16, ...props }: ViewProps & { radius?: number }) {
  const { theme } = useTheme();
  return <View style={[{ backgroundColor: theme.s.well, boxShadow: theme.s.wellInset, borderRadius: radius }, style]} {...props} />;
}

/** A solid surface inside glass (keys, selected segment). */
export function solidStyle(theme: ReturnType<typeof useTheme>['theme']): ViewStyle {
  return { backgroundColor: theme.s.solid, boxShadow: theme.s.solidShadow, borderWidth: 1, borderColor: theme.s.edge };
}

/** Level 0: the calm cool background with three soft glows (white, slate glow, faint white). */
export function ScreenBackground({ style, children }: { style?: StyleProp<ViewStyle>; children?: React.ReactNode }) {
  const { theme } = useTheme();
  const c = theme.c;
  const glowA = theme.scheme === 'light' ? '#ffffff' : c.popover;
  return (
    <View style={[{ flex: 1, backgroundColor: c.background }, style]}>
      <Svg style={StyleSheet.absoluteFill} pointerEvents="none" preserveAspectRatio="none" viewBox="0 0 100 100">
        <Defs>
          <RadialGradient id="g1" cx="50%" cy="50%" r="50%">
            <Stop offset="0" stopColor={glowA} stopOpacity={theme.scheme === 'light' ? 1 : 0.9} />
            <Stop offset="1" stopColor={glowA} stopOpacity={0} />
          </RadialGradient>
          <RadialGradient id="g2" cx="50%" cy="50%" r="50%">
            <Stop offset="0" stopColor={c.backgroundGlow} stopOpacity={1} />
            <Stop offset="1" stopColor={c.backgroundGlow} stopOpacity={0} />
          </RadialGradient>
        </Defs>
        <Ellipse cx="90" cy="0" rx="90" ry="40" fill="url(#g1)" />
        <Ellipse cx="0" cy="55" rx="85" ry="45" fill="url(#g2)" />
        <Ellipse cx="70" cy="100" rx="70" ry="30" fill="url(#g1)" opacity={0.5} />
      </Svg>
      {children}
    </View>
  );
}

/** The one dark hero card per screen: slate gradient (website hero → sidebar), faint dot grain, sheen. */
export function Hero({ style, children, radius = 24, ...props }: ViewProps & { radius?: number }) {
  const { theme } = useTheme();
  return (
    <View
      style={[{ borderRadius: radius, boxShadow: theme.s.heroShadow, borderWidth: 1, borderColor: 'rgba(255,255,255,0.06)' }, style]}
      {...props}>
      <View style={[StyleSheet.absoluteFill, { borderRadius: radius, overflow: 'hidden' }]} pointerEvents="none">
        <LinearGradient colors={theme.s.heroGradient} start={{ x: 1, y: 0 }} end={{ x: 0, y: 1 }} style={StyleSheet.absoluteFill} />
        <Svg style={StyleSheet.absoluteFill}>
          <Defs>
            <Pattern id="dots" width="10" height="10" patternUnits="userSpaceOnUse">
              <Circle cx="1" cy="1" r="1" fill="#ffffff" fillOpacity={0.05} />
            </Pattern>
            <RadialGradient id="sheen" cx="90%" cy="0%" r="90%">
              <Stop offset="0" stopColor="#ffffff" stopOpacity={0.1} />
              <Stop offset="0.6" stopColor="#ffffff" stopOpacity={0} />
            </RadialGradient>
          </Defs>
          <Rect width="100%" height="100%" fill="url(#dots)" />
          <Rect width="100%" height="100%" fill="url(#sheen)" />
        </Svg>
      </View>
      {children}
    </View>
  );
}

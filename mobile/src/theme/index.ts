import palette from './palette';

export type Scheme = 'light' | 'dark';
export type Palette = typeof palette.light;

/** "#rrggbb" + alpha 0–1 → "rgba(r, g, b, a)". */
export function alpha(hex: string, a: number): string {
  const n = parseInt(hex.slice(1), 16);
  return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${a})`;
}

/**
 * The five surface levels of docs/design/app/prototype-frosted.html, coloured with the website tokens:
 * light glass is the card white, dark glass is the popover slate. `fallback` is the prototype's
 * "weak Android" column and is also used when the user turns on Reduce Transparency.
 */
function levels(p: Palette, scheme: Scheme) {
  const glass = scheme === 'light' ? p.card : p.popover;
  const ink = p.foreground;
  const lightMode = scheme === 'light';
  const sh = (a: number) => (lightMode ? alpha(p.sidebar, a) : `rgba(0, 0, 0, ${a})`);
  return {
    glass,
    edge: lightMode ? 'rgba(255, 255, 255, 0.75)' : 'rgba(255, 255, 255, 0.07)',
    edgeTop: lightMode ? 'rgba(255, 255, 255, 0.95)' : 'rgba(255, 255, 255, 0.10)',
    hairline: p.border,
    l1: {
      fill: alpha(glass, lightMode ? 0.62 : 0.66),
      fallback: alpha(glass, lightMode ? 0.92 : 0.94),
      blur: 0, // level 1 has no blur in lists (prototype, React Native notes)
      radius: 24,
      shadow: lightMode
        ? `0 1px 2px ${sh(0.04)}, 0 8px 24px -12px ${sh(0.12)}`
        : `0 1px 2px ${sh(0.3)}, 0 8px 24px -12px ${sh(0.5)}`,
    },
    l2: {
      fill: alpha(glass, lightMode ? 0.74 : 0.76),
      fallback: alpha(glass, lightMode ? 0.94 : 0.96),
      blur: 20,
      radius: 18,
      shadow: lightMode
        ? `0 2px 4px ${sh(0.05)}, 0 16px 32px -16px ${sh(0.18)}`
        : `0 2px 4px ${sh(0.3)}, 0 16px 32px -16px ${sh(0.6)}`,
    },
    l3: {
      fill: alpha(glass, lightMode ? 0.84 : 0.86),
      fallback: glass,
      blur: 60,
      radius: 28,
      shadow: lightMode
        ? `0 -2px 6px ${sh(0.04)}, 0 -16px 48px -16px ${sh(0.26)}`
        : `0 -16px 48px -16px ${sh(0.7)}`,
    },
    l4: {
      fill: alpha(glass, 0.88),
      fallback: alpha(glass, lightMode ? 0.96 : 0.98),
      blur: 80,
      radius: 26,
      shadow: lightMode
        ? `0 2px 6px ${sh(0.06)}, 0 16px 36px -14px ${sh(0.3)}`
        : `0 2px 6px ${sh(0.35)}, 0 16px 36px -14px ${sh(0.7)}`,
    },
    /** A recessed well inside glass: fields, segmented control tracks, icon circles. */
    well: lightMode ? alpha(ink, 0.045) : 'rgba(255, 255, 255, 0.05)',
    wellInset: lightMode ? `inset 0 1px 2px ${alpha(ink, 0.07)}` : 'inset 0 1px 2px rgba(0, 0, 0, 0.35)',
    /** A solid surface inside glass (keys, the selected segment): never a third translucent layer. */
    solid: lightMode ? p.card : p.input,
    solidShadow: lightMode ? `0 1px 2px ${alpha(ink, 0.08)}, 0 4px 10px -6px ${alpha(ink, 0.18)}` : 'none',
    scrim: alpha(p.sidebar, lightMode ? 0.34 : 0.55),
    chartLine: alpha(ink, lightMode ? 0.38 : 0.42),
    heroGradient: [p.hero, p.sidebar] as const,
    heroShadow: `0 2px 4px ${sh(lightMode ? 0.1 : 0.3)}, 0 18px 36px -16px ${sh(lightMode ? 0.45 : 0.7)}`,
    /** Orange text on glass: the website orange in light mode, the thin-accent orange in dark. */
    accentText: lightMode ? p.primary : p.ring,
    /** The dark pill button: slate ink with white text (inverted in dark mode). */
    pill: p.foreground,
    pillText: lightMode ? '#ffffff' : p.background,
    successSoft: alpha(p.success, lightMode ? 0.1 : 0.14),
    dangerSoft: alpha(p.destructive, lightMode ? 0.08 : 0.12),
    warningSoft: alpha(p.warning, lightMode ? 0.12 : 0.14),
  };
}

export function makeTheme(scheme: Scheme) {
  const c = palette[scheme];
  return { scheme, c, s: levels(c, scheme) };
}

export type Theme = ReturnType<typeof makeTheme>;
export type Level = 'l1' | 'l2' | 'l3' | 'l4';

/** IBM Plex Sans Arabic, loaded in app/_layout.tsx. Each weight is its own family on Android. */
export const fonts = {
  regular: 'IBMPlexSansArabic_400Regular',
  medium: 'IBMPlexSansArabic_500Medium',
  semibold: 'IBMPlexSansArabic_600SemiBold',
} as const;

/** Type scale from the prototype: size / weight / line height (Arabic needs 1.6 for body text). */
export const type = {
  display: { fontSize: 40, lineHeight: 48, fontFamily: fonts.semibold },
  hero: { fontSize: 28, lineHeight: 36, fontFamily: fonts.semibold },
  title: { fontSize: 24, lineHeight: 34, fontFamily: fonts.semibold },
  section: { fontSize: 20, lineHeight: 30, fontFamily: fonts.medium },
  card: { fontSize: 17, lineHeight: 26, fontFamily: fonts.semibold },
  body: { fontSize: 15, lineHeight: 24, fontFamily: fonts.regular },
  bodyMedium: { fontSize: 15, lineHeight: 24, fontFamily: fonts.medium },
  small: { fontSize: 13, lineHeight: 21, fontFamily: fonts.regular },
  smallMedium: { fontSize: 13, lineHeight: 21, fontFamily: fonts.medium },
  tiny: { fontSize: 12, lineHeight: 18, fontFamily: fonts.medium },
} as const;

/** 4px grid. Screen edge 20, between cards 12, between sections 20–24. */
export const space = { 1: 4, 2: 8, 3: 12, 4: 16, 5: 20, 6: 24, 8: 32, 10: 40, 12: 48 } as const;

/** Radii from the prototype: badges 8, small keys 12, buttons/fields/wells 16, tiles 20, cards 24, sheets 28. */
export const radius = { badge: 8, key: 12, control: 16, tile: 20, card: 24, sheet: 28, pill: 999 } as const;

/** Touch targets: 44pt minimum; primary buttons 48. */
export const touch = { min: 44, button: 48, buttonSm: 40 } as const;

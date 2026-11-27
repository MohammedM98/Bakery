const { hairlineWidth } = require('nativewind/theme');
const plugin = require('tailwindcss/plugin');
const palette = require('./src/theme/palette');

// One colour source (src/theme/palette.js = the website tokens): each token becomes a CSS variable
// in "r g b" form, light on :root and dark on .dark:root, so className colours switch with the scheme.
const kebab = (k) => k.replace(/[A-Z0-9]/g, (m) => `-${m.toLowerCase()}`);
const channels = (hex) => {
  const n = parseInt(hex.slice(1), 16);
  return `${(n >> 16) & 255} ${(n >> 8) & 255} ${n & 255}`;
};
const vars = (tokens) =>
  Object.fromEntries(Object.entries(tokens).map(([k, v]) => [`--${kebab(k)}`, channels(v)]));
const colors = Object.fromEntries(
  Object.keys(palette.light).map((k) => [kebab(k), `rgb(var(--${kebab(k)}) / <alpha-value>)`]),
);

/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: ['./src/**/*.{ts,tsx}'],
  presets: [require('nativewind/preset')],
  theme: {
    extend: {
      colors: {
        ...colors,
        'primary-foreground': colors['primary-foreground'],
        'muted-foreground': colors['muted-foreground'],
        'destructive-foreground': colors['primary-foreground'],
        'card-foreground': colors.foreground,
        'popover-foreground': colors.foreground,
        'secondary-foreground': colors.foreground,
        'accent-foreground': colors.foreground,
      },
      fontFamily: {
        plex: ['IBMPlexSansArabic_400Regular'],
        'plex-medium': ['IBMPlexSansArabic_500Medium'],
        'plex-semi': ['IBMPlexSansArabic_600SemiBold'],
      },
      borderRadius: {
        badge: '8px',
        key: '12px',
        control: '16px',
        tile: '20px',
        card: '24px',
        sheet: '28px',
      },
      borderWidth: { hairline: hairlineWidth() },
    },
  },
  future: { hoverOnlyWhenSupported: true },
  plugins: [
    require('tailwindcss-animate'),
    plugin(({ addBase }) => {
      addBase({ ':root': vars(palette.light), '.dark:root': vars(palette.dark) });
    }),
  ],
};

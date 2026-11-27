/**
 * The app's only colour source. Every value is the website token of the same name in
 * resources/css/app.css (:root and .dark), as CLAUDE.md section 13 requires. The frosted
 * prototype (docs/design/app/prototype-frosted.html) only adds the glass levels below, and
 * where its colours differ from the website (orange, ink, muted text, hero, success/danger,
 * wheat) the website token wins.
 *
 * Plain CommonJS so tailwind.config.js can read it too; src/theme/index.ts types it.
 */

const light = {
  background: '#e9edf2',
  backgroundGlow: '#dfe6ee',
  foreground: '#18202b',
  card: '#ffffff',
  popover: '#ffffff',
  primary: '#c0561b',
  primaryForeground: '#ffffff',
  secondary: '#f3f5f8',
  muted: '#f3f5f8',
  mutedForeground: '#677180',
  accent: '#eaeef4',
  destructive: '#bc3c2e',
  success: '#1a764e',
  warning: '#895d11',
  wheat: '#d9a84e',
  slate: '#7d8ca3',
  border: '#e5e9ef',
  input: '#dde3ea',
  ring: '#c0561b',
  hero: '#1c2532',
  heroForeground: '#ffffff',
  heroMuted: '#aeb8c6',
  chart1: '#c0561b',
  chart2: '#7d8ca3',
  chart3: '#e3e8ef',
  sidebar: '#151b24',
};

const dark = {
  background: '#0b0f14',
  backgroundGlow: '#111822',
  foreground: '#e8edf3',
  card: '#161c25',
  popover: '#1c2430',
  primary: '#c0561b', // the button keeps white text at 4.6:1
  primaryForeground: '#ffffff',
  secondary: '#1c2430',
  muted: '#1c2430',
  mutedForeground: '#8c97a6',
  accent: '#1f2833',
  destructive: '#ef7466',
  success: '#43c08a',
  warning: '#e6bb69',
  wheat: '#e6bb69',
  slate: '#8fa0b8',
  border: '#232c38',
  input: '#2a3442',
  ring: '#d9692c', // thin accents in dark mode: active item, current chart value, focus
  hero: '#1f2935',
  heroForeground: '#ffffff',
  heroMuted: '#aeb8c6',
  chart1: '#d9692c',
  chart2: '#8fa0b8',
  chart3: '#242e3b',
  sidebar: '#0c1016',
};

module.exports = { light, dark };

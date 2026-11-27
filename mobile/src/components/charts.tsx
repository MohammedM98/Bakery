import * as React from 'react';
import { View } from 'react-native';
import Svg, { Circle, Line, Path, Rect, Text as SvgText } from 'react-native-svg';

import { fonts } from '@/theme';
import { useTheme } from '@/state/theme';

/**
 * The calm line from the prototype: values are oldest → newest and drawn right to left, so the newest
 * point is on the LEFT (Arabic reading order). One orange dot on the current value with a floating
 * tag; a hairline drop under it. No axes.
 */
export function LineChart({ values, height = 72, tag, label }: { values: number[]; height?: number; tag: string; label: string }) {
  const { theme } = useTheme();
  const [w, setW] = React.useState(0);
  const pad = 8;
  const vals = values.length > 1 ? values : [0, ...values];
  const max = Math.max(...vals, 1) * 1.08;
  const min = Math.min(...vals) * 0.85;
  const span = max - min || 1;
  const X = vals.map((_, i) => pad + ((w - 2 * pad) * (vals.length - 1 - i)) / (vals.length - 1));
  const top = 32;
  const Y = vals.map((v) => top + (1 - (v - min) / span) * (height - top - 8));
  let d = '';
  if (w > 0) {
    d = `M${X[0]},${Y[0]}`;
    for (let i = 0; i < X.length - 1; i++) {
      const c = (X[i] + X[i + 1]) / 2;
      d += ` C${c},${Y[i]} ${c},${Y[i + 1]} ${X[i + 1]},${Y[i + 1]}`;
    }
  }
  const L = vals.length - 1;
  const lx = X[L];
  const ly = Y[L];
  const tw = tag.length * 7 + 18;
  const tx = Math.max(0, Math.min(lx - 6, w - tw));
  return (
    <View style={{ height }} onLayout={(e) => setW(e.nativeEvent.layout.width)} accessible accessibilityRole="image" accessibilityLabel={label}>
      {w > 0 ? (
        <Svg width={w} height={height}>
          <Path d={d} fill="none" stroke={theme.s.chartLine} strokeWidth={1.6} strokeLinecap="round" />
          <Line x1={lx} x2={lx} y1={ly} y2={height} stroke={theme.s.hairline} strokeWidth={1.5} />
          <Rect x={tx} y={ly - 30} width={tw} height={22} rx={8} fill={theme.s.solid} stroke={theme.s.hairline} />
          <SvgText x={tx + tw / 2} y={ly - 15} textAnchor="middle" fontSize={12} fontFamily={fonts.semibold} fill={theme.c.foreground}>
            {tag}
          </SvgText>
          <Circle cx={lx} cy={ly} r={4.5} fill={theme.s.accentText} stroke={theme.s.solid} strokeWidth={2} />
        </Svg>
      ) : null}
    </View>
  );
}

/** Columns for the sales log: newest on the left in orange with its value above, the rest calm wells. */
export function BarChart({ values, labels, label, format }: { values: number[]; labels: string[]; label: string; format: (v: number) => string }) {
  const { theme } = useTheme();
  const [w, setW] = React.useState(0);
  const H = 140;
  const top = 24;
  const bottom = 22;
  const max = Math.max(...values, 1) * 1.1;
  const n = values.length;
  const bw = Math.min(26, w / (n * 1.8));
  const X = values.map((_, i) => (w > 0 ? 16 + ((w - 32) * (n - 1 - i)) / Math.max(n - 1, 1) : 0));
  return (
    <View style={{ height: H }} onLayout={(e) => setW(e.nativeEvent.layout.width)} accessible accessibilityRole="image" accessibilityLabel={label}>
      {w > 0 ? (
        <Svg width={w} height={H}>
          {values.map((v, i) => {
            const h = Math.max(4, (v / max) * (H - top - bottom));
            const y = H - bottom - h;
            const hot = i === n - 1;
            return (
              <React.Fragment key={i}>
                <Rect x={X[i] - bw / 2} y={y} width={bw} height={h} rx={7} fill={hot ? theme.s.accentText : theme.c.chart3} />
                <SvgText x={X[i]} y={H - 6} textAnchor="middle" fontSize={11} fontFamily={fonts.regular} fill={theme.c.mutedForeground}>
                  {labels[i]}
                </SvgText>
                {hot ? (
                  <SvgText x={X[i]} y={y - 8} textAnchor="middle" fontSize={12} fontFamily={fonts.semibold} fill={theme.c.foreground}>
                    {format(v)}
                  </SvgText>
                ) : null}
              </React.Fragment>
            );
          })}
        </Svg>
      ) : null}
    </View>
  );
}

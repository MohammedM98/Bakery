import type { Tone } from '@/components/common';
import type { Subscription } from '@/api/types';

import { formatDays, formatShortDate } from './format';

/** The website's four subscription states (session 2): active, ending soon (≤ 7 days), expired, stopped. */
export function subscriptionStatus(s: Subscription): { label: string; tone: Tone; detail: string } {
  const days = s.days_left ?? 0;
  const end = s.expires_at ? formatShortDate(s.expires_at) : '';
  if (s.status !== 'active') return { label: 'موقوف', tone: 'neutral', detail: 'أوقفه مالك المنصة' };
  if (!s.expires_at) return { label: 'فعّال', tone: 'success', detail: 'بلا تاريخ انتهاء' };
  if (!s.active || days < 0) return { label: 'منتهٍ', tone: 'danger', detail: `انتهى ${end}` };
  if (days <= 7) return { label: 'ينتهي قريبًا', tone: 'warning', detail: `متبقٍ ${formatDays(days)}` };
  return { label: 'فعّال', tone: 'success', detail: `ينتهي ${end}، متبقٍ ${formatDays(days)}` };
}

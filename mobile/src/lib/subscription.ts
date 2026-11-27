import type { Tone } from '@/components/common';
import type { ApiBakery } from '@/mock/types';

import { formatDays, formatShortDate } from './format';

/** The website's four subscription states (session 2): active, ending soon (≤ 7 days), expired, stopped. */
export function subscriptionStatus(b: ApiBakery, today: string): { label: string; tone: Tone; detail: string; days: number } {
  const days = Math.round((Date.parse(b.subscription_ends_at) - Date.parse(today)) / 86400000);
  if (!b.is_active) return { label: 'موقوف', tone: 'neutral', detail: 'أوقفه مالك المنصة', days };
  if (days < 0) return { label: 'منتهٍ', tone: 'danger', detail: `انتهى ${formatShortDate(b.subscription_ends_at)}`, days };
  if (days <= 7) return { label: 'ينتهي قريبًا', tone: 'warning', detail: `متبقٍ ${formatDays(days)}`, days };
  return { label: 'فعّال', tone: 'success', detail: `ينتهي ${formatShortDate(b.subscription_ends_at)}، متبقٍ ${formatDays(days)}`, days };
}

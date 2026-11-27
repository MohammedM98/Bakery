/** The website's periods (SalesLogController::PERIODS); their bounds and labels come from the server. */
export type Period = 'day' | 'week' | 'month' | 'year';

export const PERIODS: readonly (readonly [Period, string])[] = [
  ['day', 'يوم'],
  ['week', 'أسبوع'],
  ['month', 'شهر'],
  ['year', 'سنة'],
];

export const CURRENT: Record<Period, string> = { day: 'اليوم', week: 'هذا الأسبوع', month: 'هذا الشهر', year: 'هذه السنة' };
/** "أمس" / "الأسبوع الماضي"... */
export const PREVIOUS: Record<Period, string> = { day: 'أمس', week: 'الأسبوع الماضي', month: 'الشهر الماضي', year: 'السنة الماضية' };

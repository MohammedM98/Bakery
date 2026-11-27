/**
 * The website's periods (SalesLogController::bounds/shift/label): day, week (Saturday → Friday, as in
 * Palestine), month, year. Dates are "Y-m-d" strings in local (bakery) time.
 */
export type Period = 'day' | 'week' | 'month' | 'year';

export const PERIODS: readonly (readonly [Period, string])[] = [
  ['day', 'يوم'],
  ['week', 'أسبوع'],
  ['month', 'شهر'],
  ['year', 'سنة'],
];

const MONTHS = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
const DAYS = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

const d = (s: string) => new Date(`${s}T12:00:00Z`);
const s = (x: Date) => x.toISOString().slice(0, 10);
const dm = (x: string) => `${x.slice(8, 10)}/${x.slice(5, 7)}`;

export function bounds(period: Period, date: string): [string, string] {
  const x = d(date);
  if (period === 'week') {
    const back = (x.getUTCDay() + 1) % 7; // days since Saturday
    const start = new Date(x);
    start.setUTCDate(x.getUTCDate() - back);
    const end = new Date(start);
    end.setUTCDate(start.getUTCDate() + 6);
    return [s(start), s(end)];
  }
  if (period === 'month') {
    const start = new Date(Date.UTC(x.getUTCFullYear(), x.getUTCMonth(), 1, 12));
    const end = new Date(Date.UTC(x.getUTCFullYear(), x.getUTCMonth() + 1, 0, 12));
    return [s(start), s(end)];
  }
  if (period === 'year') return [`${x.getUTCFullYear()}-01-01`, `${x.getUTCFullYear()}-12-31`];
  return [date, date];
}

export function shift(period: Period, start: string, n: number): string {
  const x = d(start);
  if (period === 'week') x.setUTCDate(x.getUTCDate() + 7 * n);
  else if (period === 'month') x.setUTCMonth(x.getUTCMonth() + n, 1);
  else if (period === 'year') x.setUTCFullYear(x.getUTCFullYear() + n);
  else x.setUTCDate(x.getUTCDate() + n);
  return s(x);
}

export function label(period: Period, start: string, end: string): string {
  const x = d(start);
  if (period === 'week') return `${dm(start)} – ${dm(end)}/${end.slice(0, 4)}`;
  if (period === 'month') return `${MONTHS[x.getUTCMonth()]} ${x.getUTCFullYear()}`;
  if (period === 'year') return String(x.getUTCFullYear());
  return `${DAYS[x.getUTCDay()]} ${dm(start)}/${start.slice(0, 4)}`;
}

/** Short label for a chart column: day of month, Saturday date, month number, year. */
export function tick(period: Period, start: string): string {
  if (period === 'month') return start.slice(5, 7);
  if (period === 'year') return start.slice(0, 4);
  return start.slice(8, 10);
}

/** "اليوم" / "هذا الأسبوع" / "هذا الشهر" / "هذه السنة". */
export const CURRENT: Record<Period, string> = { day: 'اليوم', week: 'هذا الأسبوع', month: 'هذا الشهر', year: 'هذه السنة' };
/** "أمس" / "الأسبوع الماضي"... */
export const PREVIOUS: Record<Period, string> = { day: 'أمس', week: 'الأسبوع الماضي', month: 'الشهر الماضي', year: 'السنة الماضية' };

export function inRange(date: string, [from, to]: [string, string]): boolean {
  return date >= from && date <= to;
}

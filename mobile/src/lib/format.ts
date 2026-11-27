/**
 * Copies of the website's display rules (resources/js/lib/format.ts, which mirrors PHP money()):
 * keep all three in step. tests/format.test.mjs repeats tests/Unit/MoneyFormatTest.php's cases.
 */

const moneyFormatter = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const kgFormatter = new Intl.NumberFormat('en-US', { maximumFractionDigits: 3 });

/**
 * "1,234.50 شيكل". A negative number (or one with `plus`) sits in a left-to-right isolate so the sign
 * stays on its left inside Arabic text: "-50.00 شيكل", "+90.00 شيكل".
 */
export function formatMoney(amount: number | string | null | undefined, { plus = false } = {}): string {
  const n = Number(amount ?? 0) || 0;
  const number = `${plus && n > 0 ? '+' : ''}${moneyFormatter.format(n)}`;
  return `${n < 0 || plus ? `⁦${number}⁩` : number} شيكل`;
}

/** The number alone, "1,234.50", for big figures that put the word شيكل in a smaller run. */
export function formatAmount(amount: number | string | null | undefined): string {
  const n = Number(amount ?? 0) || 0;
  return n < 0 ? `⁦${moneyFormatter.format(n)}⁩` : moneyFormatter.format(n);
}

/** "12.5 كغ" with Latin digits. */
export function formatKg(kg: number | string | null | undefined): string {
  return `${kgFormatter.format(Number(kg ?? 0) || 0)} كغ`;
}

/** Flour balance in words, never a sign: positive = "عليه 20 كغ", negative = "له 20 كغ", zero = "0 كغ". */
export function formatFlourBalance(kg: number | string | null | undefined): string {
  const n = Number(kg ?? 0) || 0;
  if (n === 0) return formatKg(0);
  return `${n > 0 ? 'عليه' : 'له'} ${formatKg(Math.abs(n))}`;
}

/** "2026-11-26" → "26/11/2026" with Latin digits. */
export function formatDate(date: string | null | undefined): string {
  if (!date) return '';
  const [y, m, d] = date.slice(0, 10).split('-');
  return `${d}/${m}/${y}`;
}

/** "2026-11-26" → "26/11". */
export function formatShortDate(date: string | null | undefined): string {
  return formatDate(date).slice(0, 5);
}

/** "2026-11-26T08:31:00" → "08:31". */
export function formatTime(iso: string | null | undefined): string {
  return iso ? iso.slice(11, 16) : '';
}

/** Arabic count of days with Latin digits: "يوم واحد", "يومان", "5 أيام", "12 يومًا". */
export function formatDays(days: number): string {
  const n = Math.abs(days);
  if (n === 1) return 'يوم واحد';
  if (n === 2) return 'يومان';
  if (n >= 3 && n <= 10) return `${n} أيام`;
  return `${n} يومًا`;
}

/** Decimal string → integer hundredths, so sums never use floats (CLAUDE.md section 6). */
export function toCents(value: string | number | null | undefined): number {
  return Math.round((Number(value ?? 0) || 0) * 100);
}

/** Integer hundredths → "12.50" (the server's decimal string). */
export function fromCents(cents: number): string {
  return (cents / 100).toFixed(2);
}

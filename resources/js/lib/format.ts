const moneyFormatter = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const kgFormatter = new Intl.NumberFormat('en-US', { maximumFractionDigits: 3 });

/** Mirrors PHP money(): number_format($amount, 2).' ₪' → "1,234.50 ₪". */
export function formatMoney(amount: number | string | null | undefined): string {
    return `${moneyFormatter.format(Number(amount ?? 0) || 0)} ₪`;
}

/** "12.5 كغ" with Latin digits. */
export function formatKg(kg: number | string | null | undefined): string {
    return `${kgFormatter.format(Number(kg ?? 0) || 0)} كغ`;
}

/** "2026-11-26" → "26/11/2026" with Latin digits. */
export function formatDate(date: string | null | undefined): string {
    if (!date) return '';
    const [y, m, d] = date.slice(0, 10).split('-');
    return `${d}/${m}/${y}`;
}

/** Arabic count of days with Latin digits: "يوم واحد", "يومان", "5 أيام", "12 يومًا". */
export function formatDays(days: number): string {
    const n = Math.abs(days);
    if (n === 1) return 'يوم واحد';
    if (n === 2) return 'يومان';
    if (n >= 3 && n <= 10) return `${n} أيام`;
    return `${n} يومًا`;
}

/** Today as "YYYY-MM-DD" in the device's time zone, for date inputs. */
export function today(): string {
    return new Date().toLocaleDateString('en-CA');
}

/** Random id sent with each new record so the server saves a double-submitted form once. */
export function newSubmissionId(): string {
    // randomUUID only exists on https/localhost; the fallback is unique enough per user.
    return crypto.randomUUID?.() ?? `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
}

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

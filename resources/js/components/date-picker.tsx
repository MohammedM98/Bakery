import { CalendarDays } from 'lucide-react';
import { useState } from 'react';
import { ar } from 'react-day-picker/locale';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';

/** "2026-11-27" ↔ a local Date, without the UTC shift `new Date("2026-11-27")` would add. */
const toDate = (value: string) => {
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y, m - 1, d);
};
const toValue = (date: Date) => [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');

/** Arabic calendar in a popover for a "YYYY-MM-DD" value; shows "27/11/2026" like the rest of the app. */
export function DatePicker({
    id,
    value,
    onChange,
    min,
    max,
    placeholder = 'اختر التاريخ',
    clearLabel,
    className,
    ...aria
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    min?: string;
    max?: string;
    placeholder?: string;
    /** Shows a button that empties the value (e.g. "بلا حد"). */
    clearLabel?: string;
    className?: string;
    'aria-invalid'?: boolean;
    'aria-describedby'?: string;
}) {
    const [open, setOpen] = useState(false);
    const selected = value ? toDate(value) : undefined;
    const disabled = [...(min ? [{ before: toDate(min) }] : []), ...(max ? [{ after: toDate(max) }] : [])];

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    className={cn('h-11 w-full justify-between bg-transparent px-3 font-normal', !value && 'text-muted-foreground', className)}
                    {...aria}
                >
                    <span className="tabular-nums">{value ? formatDate(value) : placeholder}</span>
                    <CalendarDays className="text-muted-foreground" aria-hidden />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0" align="start">
                <Calendar
                    mode="single"
                    dir="rtl"
                    locale={ar}
                    selected={selected}
                    defaultMonth={selected ?? (max ? toDate(max) : undefined)}
                    disabled={disabled}
                    className="[--cell-size:--spacing(10)]"
                    onSelect={(date) => {
                        if (!date) return;
                        onChange(toValue(date));
                        setOpen(false);
                    }}
                />
                {clearLabel && value && (
                    <div className="border-t p-2">
                        <Button
                            type="button"
                            variant="ghost"
                            className="h-10 w-full"
                            onClick={() => {
                                onChange('');
                                setOpen(false);
                            }}
                        >
                            {clearLabel}
                        </Button>
                    </div>
                )}
            </PopoverContent>
        </Popover>
    );
}

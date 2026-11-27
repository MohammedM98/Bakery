import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';

export type Period = 'day' | 'week' | 'month' | 'year';

export const PERIODS: [Period, string][] = [
    ['day', 'يوم'],
    ['week', 'أسبوع'],
    ['month', 'شهر'],
    ['year', 'سنة'],
];

/** Pill switch: the selected pill is white with a small orange line under it. `value` may match none (a custom range). */
export function Pills<T extends string>({
    options,
    value,
    onChange,
    label = 'الفترة',
    className,
}: {
    options: [T, string][];
    value: T | '';
    onChange: (value: T) => void;
    label?: string;
    className?: string;
}) {
    return (
        <ToggleGroup
            type="single"
            dir="rtl"
            value={value}
            onValueChange={(v) => v && onChange(v as T)}
            aria-label={label}
            spacing={1}
            className={cn('w-full flex-wrap rounded-[1.375rem] bg-muted p-1.5 sm:w-auto', className)}
        >
            {options.map(([option, text]) => (
                <ToggleGroupItem
                    key={option}
                    value={option}
                    className={cn(
                        'h-10 flex-1 flex-col gap-0.5 rounded-full px-5 text-sm font-normal text-muted-foreground hover:bg-transparent sm:flex-none',
                        'after:h-0.5 after:w-3.5 after:rounded-full after:bg-transparent',
                        'data-[state=on]:bg-card data-[state=on]:font-semibold data-[state=on]:text-foreground data-[state=on]:shadow-sm data-[state=on]:after:bg-primary',
                    )}
                >
                    {text}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}

/** يوم / أسبوع / شهر / سنة. */
export function PeriodPills({ value, onChange, className }: { value: Period; onChange: (period: Period) => void; className?: string }) {
    return <Pills options={PERIODS} value={value} onChange={onChange} className={className} />;
}

/** The same pill look for shadcn Tabs (TabsList / TabsTrigger). */
export const pillTabs = 'h-auto! w-full flex-wrap rounded-[1.375rem] bg-muted p-1.5 sm:w-auto sm:self-start';
export const pillTab = cn(
    'h-10 flex-col gap-0.5 rounded-full px-5 font-normal text-muted-foreground',
    'after:h-0.5 after:w-3.5 after:rounded-full after:bg-transparent',
    'data-active:bg-card data-active:font-semibold data-active:text-foreground data-active:after:bg-primary dark:data-active:border-transparent dark:data-active:bg-card',
);

import { Wheat } from 'lucide-react';
import { cn } from '@/lib/utils';

export function AppLogoIcon({ className }: { className?: string }) {
    return (
        <span
            className={cn(
                'flex size-11 shrink-0 items-center justify-center rounded-[14px] bg-hero text-hero-foreground',
                className,
            )}
        >
            <Wheat className="size-5" aria-hidden />
        </span>
    );
}

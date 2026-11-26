import { Wheat } from 'lucide-react';
import { cn } from '@/lib/utils';

export function AppLogoIcon({ className }: { className?: string }) {
    return (
        <span
            className={cn(
                'flex size-9 shrink-0 items-center justify-center rounded-xl bg-sidebar-primary text-sidebar-primary-foreground',
                className,
            )}
        >
            <Wheat className="size-5" aria-hidden />
        </span>
    );
}

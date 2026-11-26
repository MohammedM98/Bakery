import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';

export function ThemeToggle({ className }: { className?: string }) {
    const { appearance, setAppearance } = useAppearance();
    const dark = appearance === 'dark';

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon-lg"
            className={className}
            onClick={() => setAppearance(dark ? 'light' : 'dark')}
            aria-label={dark ? 'الوضع الفاتح' : 'الوضع الداكن'}
            title={dark ? 'الوضع الفاتح' : 'الوضع الداكن'}
        >
            {dark ? <Sun /> : <Moon />}
        </Button>
    );
}

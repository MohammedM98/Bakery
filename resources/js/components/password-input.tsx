import { Eye, EyeOff } from 'lucide-react';
import { useState, type ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/** A password field with a show / hide button (the field is LTR, so ps-* is its left side, where the button sits). */
export function PasswordInput({ className, ...props }: Omit<ComponentProps<typeof Input>, 'type'>) {
    const [shown, setShown] = useState(false);
    return (
        <div className="relative">
            <Input type={shown ? 'text' : 'password'} dir="ltr" className={cn('h-11 ps-11 text-end', className)} {...props} />
            <button
                type="button"
                onClick={() => setShown((s) => !s)}
                aria-label={shown ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'}
                className="absolute inset-y-0 end-0 flex w-11 items-center justify-center rounded-lg text-muted-foreground outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
            >
                {shown ? <EyeOff className="size-4" aria-hidden /> : <Eye className="size-4" aria-hidden />}
            </button>
        </div>
    );
}

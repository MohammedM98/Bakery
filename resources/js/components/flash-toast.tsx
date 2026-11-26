import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { Toaster } from '@/components/ui/sonner';
import { useAppearance } from '@/hooks/use-appearance';

/** Shows the server's flash('success' | 'error') messages as toasts. */
export function FlashToast() {
    const { flash } = usePage().props;
    const { appearance } = useAppearance();

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash]);

    return <Toaster position="top-center" theme={appearance} richColors={false} />;
}

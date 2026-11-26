import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { Toaster } from '@/components/ui/sonner';
import { useAppearance } from '@/hooks/use-appearance';
import type { Flash } from '@/types';

function show(flash: Flash) {
    if (flash.success) toast.success(flash.success);
    if (flash.error) toast.error(flash.error);
}

// Flash props live in history state, so only toast on the first page load and on
// fresh server responses — never when Back/Forward restores a page.
let initialShown = false;

/** Shows the server's flash('success' | 'error') messages as toasts. */
export function FlashToast() {
    const { flash } = usePage().props;
    const { appearance } = useAppearance();

    useEffect(() => {
        if (!initialShown) {
            initialShown = true;
            show(flash);
        }
        return router.on('success', (event) => show(event.detail.page.props.flash));
        // eslint-disable-next-line react-hooks/exhaustive-deps -- initial flash only
    }, []);

    return <Toaster position="top-center" theme={appearance} />;
}

import { LoaderCircle } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

/** A dialog that holds one form: title, fields, cancel and a submit button that is disabled while sending. */
export function FormDialog({
    open,
    onOpenChange,
    title,
    description,
    submitLabel,
    processing,
    onSubmit,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: ReactNode;
    submitLabel: string;
    processing: boolean;
    onSubmit: () => void;
    children: ReactNode;
}) {
    const submit = (e: FormEvent) => {
        e.preventDefault();
        onSubmit();
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90svh] overflow-y-auto">
                <form onSubmit={submit} className="grid gap-5" noValidate>
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        {description && <DialogDescription>{description}</DialogDescription>}
                    </DialogHeader>
                    {children}
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline" className="h-11">
                                إلغاء
                            </Button>
                        </DialogClose>
                        <Button type="submit" className="h-11" disabled={processing}>
                            {processing && <LoaderCircle className="animate-spin" />}
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** A dialog around a self-contained form (customer, staff, bakery) that has its own buttons. */
export function DialogShell({
    open,
    onOpenChange,
    title,
    description,
    wide = false,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: ReactNode;
    wide?: boolean;
    children: ReactNode;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className={cn('max-h-[90svh] overflow-y-auto', wide && 'sm:max-w-3xl')}>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && <DialogDescription>{description}</DialogDescription>}
                </DialogHeader>
                {children}
            </DialogContent>
        </Dialog>
    );
}

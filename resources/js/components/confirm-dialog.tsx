import type { ReactNode } from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';

interface ConfirmDialogProps {
    /** Omit and pass `open`/`onOpenChange` to open the dialog from elsewhere (e.g. a menu item). */
    trigger?: ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    title: string;
    description: ReactNode;
    confirmLabel?: string;
    processing?: boolean;
    /** 'default' for a positive confirmation such as receiving a payment. */
    variant?: 'destructive' | 'default';
    onConfirm: () => void;
    children?: ReactNode;
}

/** Every delete goes through this. `children` renders extra fields (e.g. a password) above the buttons. */
export function ConfirmDialog({
    trigger,
    open,
    onOpenChange,
    title,
    description,
    confirmLabel = 'حذف',
    processing = false,
    variant = 'destructive',
    onConfirm,
    children,
}: ConfirmDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            {trigger && <AlertDialogTrigger asChild>{trigger}</AlertDialogTrigger>}
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>{description}</AlertDialogDescription>
                </AlertDialogHeader>
                {children}
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>إلغاء</AlertDialogCancel>
                    <AlertDialogAction
                        variant={variant}
                        disabled={processing}
                        onClick={(e) => {
                            e.preventDefault(); // keep the dialog open until the server answers
                            onConfirm();
                        }}
                    >
                        {confirmLabel}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

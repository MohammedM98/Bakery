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
    trigger: ReactNode;
    title: string;
    description: ReactNode;
    confirmLabel?: string;
    processing?: boolean;
    onConfirm: () => void;
    children?: ReactNode;
}

/** Every delete goes through this. `children` renders extra fields (e.g. a password) above the buttons. */
export function ConfirmDialog({
    trigger,
    title,
    description,
    confirmLabel = 'حذف',
    processing = false,
    onConfirm,
    children,
}: ConfirmDialogProps) {
    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>{trigger}</AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>{description}</AlertDialogDescription>
                </AlertDialogHeader>
                {children}
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>إلغاء</AlertDialogCancel>
                    <AlertDialogAction
                        variant="destructive"
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

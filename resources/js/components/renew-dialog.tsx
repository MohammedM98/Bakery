import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { Field } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

const presets = [1, 3, 6, 12];

/** Renews from the current expiry date if it is still ahead, otherwise from today. */
export function RenewDialog({
    bakery,
    onOpenChange,
}: {
    bakery: { id: number; name: string } | null;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({ months: '1' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (!bakery) return;
        form.post(route('admin.bakeries.renew', bakery.id), {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                form.reset();
            },
        });
    };

    return (
        <Dialog
            open={bakery !== null}
            onOpenChange={(open) => {
                if (!open) form.reset(); // also clears errors
                onOpenChange(open);
            }}
        >
            <DialogContent>
                <form onSubmit={submit} className="grid gap-5" noValidate>
                    <DialogHeader>
                        <DialogTitle>تجديد اشتراك {bakery?.name}</DialogTitle>
                        <DialogDescription>
                            تُضاف المدة إلى تاريخ الانتهاء الحالي إن لم يكن قد مضى، وإلا تُحسب من اليوم. التجديد يفعّل الاشتراك.
                        </DialogDescription>
                    </DialogHeader>
                    <Field id="months" label="عدد الأشهر" error={form.errors.months}>
                        <div className="flex flex-wrap gap-2">
                            {presets.map((m) => (
                                <Button
                                    key={m}
                                    type="button"
                                    variant={form.data.months === String(m) ? 'default' : 'outline'}
                                    className="h-11 min-w-14"
                                    aria-pressed={form.data.months === String(m)}
                                    onClick={() => form.setData('months', String(m))}
                                >
                                    {m}
                                </Button>
                            ))}
                            <Input
                                id="months"
                                type="number"
                                inputMode="numeric"
                                min="1"
                                max="24"
                                dir="ltr"
                                className="h-11 w-24 text-center tabular-nums"
                                value={form.data.months}
                                onChange={(e) => form.setData('months', e.target.value)}
                                aria-invalid={!!form.errors.months}
                                aria-describedby="months-error"
                            />
                        </div>
                    </Field>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline" className="h-11">
                                إلغاء
                            </Button>
                        </DialogClose>
                        <Button type="submit" className="h-11" disabled={form.processing}>
                            {form.processing && <LoaderCircle className="animate-spin" />}
                            تجديد
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

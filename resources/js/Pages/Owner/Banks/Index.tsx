import { router, useForm } from '@inertiajs/react';
import { Landmark, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { FormDialog } from '@/components/form-dialog';
import { Field } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/Layouts/AppLayout';

interface Bank {
    id: number;
    name: string;
}

interface Props {
    banks: Bank[];
    canManage: boolean;
    canDelete: boolean;
}

/** Add (no bank) or rename (a bank) in one dialog. */
function BankDialog({ bank, open, onOpenChange }: { bank: Bank | null; open: boolean; onOpenChange: (open: boolean) => void }) {
    const form = useForm({ name: bank?.name ?? '' });
    const submit = () => {
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        };
        if (bank) form.put(route('panel.banks.update', bank.id), options);
        else form.post(route('panel.banks.store'), options);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={(next) => {
                if (!next) form.clearErrors();
                onOpenChange(next);
            }}
            title={bank ? 'تعديل الاسم' : 'إضافة بنك أو محفظة'}
            description={bank ? 'الاسم الجديد يظهر في الدفعات القادمة فقط؛ الدفعات المسجلة تبقى بالاسم القديم' : 'مثل: بنك فلسطين، جوال باي، بال باي'}
            submitLabel="حفظ"
            processing={form.processing}
            onSubmit={submit}
        >
            <Field id="bank_name" label="الاسم" error={form.errors.name}>
                <Input
                    id="bank_name"
                    autoFocus
                    required
                    maxLength={100}
                    className="h-11"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    aria-invalid={!!form.errors.name}
                    aria-describedby="bank_name-error"
                />
            </Field>
        </FormDialog>
    );
}

export default function Index({ banks, canManage, canDelete }: Props) {
    // undefined = closed, null = adding, a bank = renaming it.
    const [editing, setEditing] = useState<Bank | null | undefined>(undefined);
    const [deleting, setDeleting] = useState<Bank | null>(null);
    const [processing, setProcessing] = useState(false);

    const destroy = () => {
        if (!deleting) return;
        router.delete(route('panel.banks.destroy', deleting.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setDeleting(null),
        });
    };

    const addButton = canManage && (
        <Button className="h-11 w-full px-5 sm:w-auto" onClick={() => setEditing(null)}>
            <Plus />
            إضافة بنك أو محفظة
        </Button>
    );

    return (
        <AppLayout title="البنوك والمحافظ">
            <div className="grid gap-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-sm text-muted-foreground">قائمة واحدة تُختار منها جهتا التحويل (من ← إلى) عند تسجيل دفعة</p>
                    {banks.length > 0 && addButton}
                </div>

                {banks.length === 0 ? (
                    <EmptyState icon={Landmark} title="لا توجد بنوك أو محافظ بعد" description="أضف البنوك والمحافظ التي تستلم بها الدفعات بالتحويل" action={addButton || undefined} />
                ) : (
                    <Card className="py-0">
                        <ul className="divide-y">
                            {banks.map((bank) => (
                                <li key={bank.id} className="flex items-center justify-between gap-3 px-4 py-2 sm:px-6">
                                    <span className="font-medium">{bank.name}</span>
                                    <div className="flex gap-1">
                                        {canManage && (
                                            <Button variant="ghost" size="icon" className="size-11" aria-label={`تعديل ${bank.name}`} onClick={() => setEditing(bank)}>
                                                <Pencil />
                                            </Button>
                                        )}
                                        {canDelete && (
                                            <Button variant="ghost" size="icon" className="size-11 text-destructive hover:text-destructive" aria-label={`حذف ${bank.name}`} onClick={() => setDeleting(bank)}>
                                                <Trash2 />
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}
            </div>

            {/* Keyed so the form starts from the chosen bank's name each time. */}
            <BankDialog key={editing?.id ?? 'new'} bank={editing ?? null} open={editing !== undefined} onOpenChange={(o) => !o && setEditing(undefined)} />
            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(o) => !o && setDeleting(null)}
                title={`حذف "${deleting?.name ?? ''}"؟`}
                description="يُحذف من القائمة نهائيًا. الدفعات المسجلة به تبقى في كشوف الزبائن بنفس الاسم."
                processing={processing}
                onConfirm={destroy}
            />
        </AppLayout>
    );
}

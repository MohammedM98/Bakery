import { useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { InputError } from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/Layouts/AppLayout';

function Field({ id, label, error, children }: { id: string; label: string; error?: string; children: ReactNode }) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            <InputError id={`${id}-error`} message={error} />
        </div>
    );
}

function ProfileInformationForm() {
    const user = usePage().props.auth.user!;
    const form = useForm({ name: user.name, email: user.email });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.patch(route('profile.update'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="grid gap-5" noValidate>
            <Field id="name" label="الاسم" error={form.errors.name}>
                <Input
                    id="name"
                    className="h-11"
                    autoComplete="name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    aria-invalid={!!form.errors.name}
                    aria-describedby="name-error"
                />
            </Field>
            <Field id="email" label="البريد الإلكتروني" error={form.errors.email}>
                <Input
                    id="email"
                    type="email"
                    dir="ltr"
                    className="h-11 text-end"
                    autoComplete="username"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    aria-invalid={!!form.errors.email}
                    aria-describedby="email-error"
                />
            </Field>
            <div>
                <Button type="submit" className="h-11 px-6" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    حفظ
                </Button>
            </div>
        </form>
    );
}

function UpdatePasswordForm() {
    const form = useForm({ current_password: '', password: '', password_confirmation: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(route('password.update'), {
            errorBag: 'updatePassword',
            preserveScroll: true,
            onSuccess: () => form.reset(),
            onError: () => form.reset('password', 'password_confirmation'),
        });
    };

    const fields = [
        { id: 'current_password', label: 'كلمة المرور الحالية', autoComplete: 'current-password' },
        { id: 'password', label: 'كلمة المرور الجديدة', autoComplete: 'new-password' },
        { id: 'password_confirmation', label: 'تأكيد كلمة المرور الجديدة', autoComplete: 'new-password' },
    ] as const;

    return (
        <form onSubmit={submit} className="grid gap-5" noValidate>
            {fields.map((f) => (
                <Field key={f.id} id={f.id} label={f.label} error={form.errors[f.id]}>
                    <Input
                        id={f.id}
                        type="password"
                        dir="ltr"
                        className="h-11 text-end"
                        autoComplete={f.autoComplete}
                        value={form.data[f.id]}
                        onChange={(e) => form.setData(f.id, e.target.value)}
                        aria-invalid={!!form.errors[f.id]}
                        aria-describedby={`${f.id}-error`}
                    />
                </Field>
            ))}
            <div>
                <Button type="submit" className="h-11 px-6" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    تغيير كلمة المرور
                </Button>
            </div>
        </form>
    );
}

function DeleteAccountForm() {
    const form = useForm({ password: '' });

    const destroy = () => {
        form.delete(route('profile.destroy'), {
            errorBag: 'userDeletion',
            preserveScroll: true,
            onFinish: () => form.reset(),
        });
    };

    return (
        <ConfirmDialog
            trigger={
                <Button variant="destructive" className="h-11 px-6">
                    حذف الحساب
                </Button>
            }
            title="حذف الحساب نهائيًا؟"
            description="سيُحذف حسابك ولا يمكن التراجع. اكتب كلمة المرور للتأكيد."
            confirmLabel="حذف الحساب"
            processing={form.processing}
            onConfirm={destroy}
        >
            <Field id="delete_password" label="كلمة المرور" error={form.errors.password}>
                <Input
                    id="delete_password"
                    type="password"
                    dir="ltr"
                    className="h-11 text-end"
                    autoComplete="current-password"
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                    aria-invalid={!!form.errors.password}
                    aria-describedby="delete_password-error"
                />
            </Field>
        </ConfirmDialog>
    );
}

export default function Edit() {
    const isStaff = usePage().props.auth.user?.role === 'bakery_staff';
    return (
        <AppLayout title="الملف الشخصي">
            <div className="mx-auto grid max-w-2xl gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>بيانات الحساب</CardTitle>
                        <CardDescription>الاسم والبريد الذي تدخل به</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ProfileInformationForm />
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>كلمة المرور</CardTitle>
                        <CardDescription>استخدم كلمة مرور طويلة يصعب تخمينها</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <UpdatePasswordForm />
                    </CardContent>
                </Card>
                {/* Staff accounts are disabled by the bakery owner, not deleted by their holder. */}
                {!isStaff && (
                <Card>
                    <CardHeader>
                        <CardTitle>حذف الحساب</CardTitle>
                        <CardDescription>يُحذف الحساب نهائيًا ولا يمكن استعادته</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DeleteAccountForm />
                    </CardContent>
                </Card>
                )}
            </div>
        </AppLayout>
    );
}

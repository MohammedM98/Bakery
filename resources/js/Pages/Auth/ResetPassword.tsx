import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { InputError } from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import GuestLayout from '@/Layouts/GuestLayout';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const form = useForm({ token, email, password: '', password_confirmation: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('password.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
    };

    return (
        <GuestLayout title="تعيين كلمة مرور جديدة">
            <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
                <div className="grid gap-2">
                    <Label htmlFor="email">البريد الإلكتروني</Label>
                    <Input
                        id="email"
                        type="email"
                        dir="ltr"
                        className="h-11 text-start"
                        autoComplete="username"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        aria-invalid={!!form.errors.email}
                        aria-describedby="email-error"
                    />
                    <InputError id="email-error" message={form.errors.email} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="password">كلمة المرور الجديدة</Label>
                    <Input
                        id="password"
                        type="password"
                        dir="ltr"
                        className="h-11 text-start"
                        autoComplete="new-password"
                        autoFocus
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                        aria-invalid={!!form.errors.password}
                        aria-describedby="password-error"
                    />
                    <InputError id="password-error" message={form.errors.password} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">تأكيد كلمة المرور</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        dir="ltr"
                        className="h-11 text-start"
                        autoComplete="new-password"
                        value={form.data.password_confirmation}
                        onChange={(e) => form.setData('password_confirmation', e.target.value)}
                        aria-invalid={!!form.errors.password_confirmation}
                        aria-describedby="password_confirmation-error"
                    />
                    <InputError id="password_confirmation-error" message={form.errors.password_confirmation} />
                </div>
                <Button type="submit" className="h-11 text-base" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    حفظ كلمة المرور
                </Button>
            </form>
        </GuestLayout>
    );
}

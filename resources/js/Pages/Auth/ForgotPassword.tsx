import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { InputError } from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import GuestLayout from '@/Layouts/GuestLayout';

export default function ForgotPassword({ status }: { status: string | null }) {
    const form = useForm({ email: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('password.email'));
    };

    return (
        <GuestLayout title="نسيت كلمة المرور" description="اكتب بريدك وسنرسل لك رابطًا لتعيين كلمة مرور جديدة">
            {status && <p className="mb-6 rounded-xl bg-success/10 px-4 py-3 text-sm text-success">{status}</p>}
            <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
                <div className="grid gap-2">
                    <Label htmlFor="email">البريد الإلكتروني</Label>
                    <Input
                        id="email"
                        type="email"
                        dir="ltr"
                        className="h-11 text-end"
                        autoComplete="username"
                        autoFocus
                        required
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        aria-invalid={!!form.errors.email}
                        aria-describedby="email-error"
                    />
                    <InputError id="email-error" message={form.errors.email} />
                </div>
                <Button type="submit" className="h-11 text-base" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    إرسال رابط الاستعادة
                </Button>
                <Link href={route('login')} className="text-center text-sm text-primary underline-offset-4 hover:underline">
                    العودة لتسجيل الدخول
                </Link>
            </form>
        </GuestLayout>
    );
}

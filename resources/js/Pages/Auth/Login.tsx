import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { InputError } from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import GuestLayout from '@/Layouts/GuestLayout';

export default function Login({ status }: { status: string | null }) {
    const form = useForm({ email: '', password: '', remember: false });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('login'), { onFinish: () => form.reset('password') });
    };

    return (
        <GuestLayout title="تسجيل الدخول" description="ادخل ببريدك وكلمة المرور لإدارة مخبزك">
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
                <div className="grid gap-2">
                    <div className="flex items-center justify-between">
                        <Label htmlFor="password">كلمة المرور</Label>
                        <Link href={route('password.request')} className="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline">
                            نسيت كلمة المرور؟
                        </Link>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        dir="ltr"
                        className="h-11 text-end"
                        autoComplete="current-password"
                        required
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                        aria-invalid={!!form.errors.password}
                        aria-describedby="password-error"
                    />
                    <InputError id="password-error" message={form.errors.password} />
                </div>
                <div className="flex items-center gap-2">
                    <Checkbox
                        id="remember"
                        className="size-5"
                        checked={form.data.remember}
                        onCheckedChange={(checked) => form.setData('remember', checked === true)}
                    />
                    <Label htmlFor="remember" className="font-normal">
                        تذكّرني
                    </Label>
                </div>
                <Button type="submit" className="h-11 text-[15px] font-semibold" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    دخول
                </Button>
            </form>
        </GuestLayout>
    );
}

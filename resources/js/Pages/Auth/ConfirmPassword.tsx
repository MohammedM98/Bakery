import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { InputError } from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import GuestLayout from '@/Layouts/GuestLayout';

export default function ConfirmPassword() {
    const form = useForm({ password: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('password.confirm'), { onFinish: () => form.reset('password') });
    };

    return (
        <GuestLayout title="تأكيد كلمة المرور" description="هذه منطقة محمية. أكّد كلمة المرور للمتابعة.">
            <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
                <div className="grid gap-2">
                    <Label htmlFor="password">كلمة المرور</Label>
                    <Input
                        id="password"
                        type="password"
                        dir="ltr"
                        className="h-11 text-start"
                        autoComplete="current-password"
                        autoFocus
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                        aria-invalid={!!form.errors.password}
                        aria-describedby="password-error"
                    />
                    <InputError id="password-error" message={form.errors.password} />
                </div>
                <Button type="submit" className="h-11 text-base" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    تأكيد
                </Button>
            </form>
        </GuestLayout>
    );
}

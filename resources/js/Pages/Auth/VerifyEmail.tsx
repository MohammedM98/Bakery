import { router, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import GuestLayout from '@/Layouts/GuestLayout';

export default function VerifyEmail({ status }: { status: string | null }) {
    const form = useForm({});

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('verification.send'));
    };

    return (
        <GuestLayout
            title="تأكيد البريد الإلكتروني"
            description="أرسلنا رابط تأكيد إلى بريدك. افتحه للمتابعة، أو اطلب رابطًا جديدًا."
        >
            {status === 'verification-link-sent' && (
                <p className="mb-6 rounded-xl bg-success/10 px-4 py-3 text-sm text-success">
                    أُرسل رابط تأكيد جديد إلى بريدك الإلكتروني.
                </p>
            )}
            <form onSubmit={submit} className="flex flex-col gap-3">
                <Button type="submit" className="h-11 text-base" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    إعادة إرسال رابط التأكيد
                </Button>
                <Button type="button" variant="ghost" className="h-11" onClick={() => router.post(route('logout'))}>
                    تسجيل الخروج
                </Button>
            </form>
        </GuestLayout>
    );
}

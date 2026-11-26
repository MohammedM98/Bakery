import { router } from '@inertiajs/react';
import { CalendarX2, LogOut } from 'lucide-react';
import { Button } from '@/components/ui/button';
import GuestLayout from '@/Layouts/GuestLayout';

export default function SubscriptionExpired() {
    return (
        <GuestLayout
            title="الاشتراك غير مفعّل"
            description="اشتراك مخبزكم منتهٍ أو غير مفعّل حاليًا. يرجى التواصل مع مالك المنصة لتجديد الاشتراك للوصول إلى لوحة التحكم."
        >
            <div className="grid gap-4">
                <span className="flex size-12 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                    <CalendarX2 className="size-6" aria-hidden />
                </span>
                <Button className="h-11 w-full" onClick={() => router.post(route('logout'))}>
                    <LogOut />
                    تسجيل الخروج
                </Button>
            </div>
        </GuestLayout>
    );
}

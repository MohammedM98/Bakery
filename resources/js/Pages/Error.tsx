import { Head, Link } from '@inertiajs/react';
import { House } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { GuestFrame } from '@/Layouts/GuestLayout';

type ErrorStatus = 403 | 404 | 419 | 500 | 503;

interface ErrorPageProps {
    status: ErrorStatus;
    appName: string;
}

const messages: Record<ErrorStatus, { title: string; description: string }> = {
    403: { title: 'لا تملك صلاحية لهذه الصفحة', description: 'إن كنت تحتاج إليها فتواصل مع صاحب المخبز ليمنحك الصلاحية.' },
    404: { title: 'الصفحة غير موجودة', description: 'ربما حُذفت أو تغيّر رابطها. تأكد من الرابط أو عد إلى صفحتك الرئيسية.' },
    419: { title: 'انتهت صلاحية الصفحة', description: 'مرّ وقت طويل دون نشاط. أعد فتح الصفحة وحاول مرة أخرى.' },
    500: { title: 'حدث خطأ غير متوقع', description: 'لم نتمكن من إتمام طلبك. حاول مرة أخرى بعد قليل.' },
    503: { title: 'المنصة قيد الصيانة', description: 'نعمل على تحديث المنصة وستعود خلال دقائق.' },
};

/** Arabic page for HTTP errors, rendered from the exception handler in bootstrap/app.php. */
export default function Error({ status, appName }: ErrorPageProps) {
    const { title, description } = messages[status];

    return (
        <>
            <Head title={title} />
            <GuestFrame appName={appName}>
                <p className="text-5xl font-semibold text-primary tabular-nums">{status}</p>
                <h1 className="mt-4 text-[26px] leading-snug font-semibold">{title}</h1>
                <p className="mt-2 text-muted-foreground">{description}</p>
                <Button asChild className="mt-8 h-11 w-full text-[15px] font-semibold">
                    {/* /dashboard sends each role to its own home, and a guest to the login page. */}
                    <Link href={route('dashboard')}>
                        <House />
                        الصفحة الرئيسية
                    </Link>
                </Button>
            </GuestFrame>
        </>
    );
}

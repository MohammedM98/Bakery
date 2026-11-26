import { Head, Link } from '@inertiajs/react';
import { House } from 'lucide-react';
import { AppLogoIcon } from '@/components/app-logo';
import { ThemeToggle } from '@/components/theme-toggle';
import { Button } from '@/components/ui/button';

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
            <div className="flex min-h-svh flex-col px-4 py-6 sm:px-8">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2 font-semibold">
                        <AppLogoIcon />
                        <span>{appName}</span>
                    </div>
                    <ThemeToggle />
                </div>
                <main className="flex flex-1 items-center justify-center py-10">
                    <div className="w-full max-w-sm text-center">
                        <p className="text-5xl font-semibold text-primary tabular-nums">{status}</p>
                        <h1 className="mt-4 text-2xl font-semibold">{title}</h1>
                        <p className="mt-2 text-muted-foreground">{description}</p>
                        <Button asChild className="mt-8 h-11 w-full">
                            {/* /dashboard sends each role to its own home, and a guest to the login page. */}
                            <Link href={route('dashboard')}>
                                <House />
                                الصفحة الرئيسية
                            </Link>
                        </Button>
                    </div>
                </main>
            </div>
        </>
    );
}

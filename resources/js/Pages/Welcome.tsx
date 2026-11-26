import { Head, Link, usePage } from '@inertiajs/react';
import { AppLogoIcon } from '@/components/app-logo';
import { ThemeToggle } from '@/components/theme-toggle';
import { Button } from '@/components/ui/button';

const features = [
    ['المبيعات اليومية', 'سجّل البيع بالكيلو وتابع المقبوضات وغير المدفوع لكل يوم.'],
    ['رصيد الطحين', 'يودِع الزبون طحينه ويأخذ خبزًا على دفعات برسم تبديل تحدده أنت.'],
    ['حسابات العملاء', 'المبالغ المستحقة والدفعات الجزئية لكل عميل في صفحة واحدة.'],
    ['مخبز مستقل', 'لكل مخبز حسابه ولوحته وبياناته، باشتراك شهري يجدده مالك المنصة.'],
];

export default function Welcome() {
    const { auth, appName } = usePage().props;

    return (
        <>
            <Head title="نظام إدارة المخابز" />
            <div className="flex min-h-svh flex-col">
                <header className="mx-auto flex w-full max-w-5xl items-center justify-between px-4 py-5 sm:px-8">
                    <div className="flex items-center gap-2 font-semibold">
                        <AppLogoIcon />
                        <span>{appName}</span>
                    </div>
                    <ThemeToggle />
                </header>

                <main className="mx-auto grid w-full max-w-5xl flex-1 content-center gap-12 px-4 py-12 sm:px-8">
                    <section className="max-w-2xl">
                        <h1 className="text-4xl leading-tight font-semibold sm:text-5xl">نظام إدارة المخابز</h1>
                        <p className="mt-4 text-lg text-muted-foreground">
                            مبيعات اليوم، حسابات العملاء، ورصيد الطحين مقابل الخبز، من هاتفك وأنت أمام الفرن.
                        </p>
                        <Button asChild className="mt-8 h-12 px-8 text-base">
                            {auth.user ? <Link href={route('dashboard')}>الذهاب إلى لوحة التحكم</Link> : <Link href={route('login')}>تسجيل الدخول</Link>}
                        </Button>
                        {!auth.user && <p className="mt-3 text-sm text-muted-foreground">الحسابات ينشئها مالك المنصة لكل مخبز مشترك.</p>}
                    </section>

                    <ul className="grid gap-x-8 gap-y-6 border-t pt-8 sm:grid-cols-2">
                        {features.map(([title, text]) => (
                            <li key={title}>
                                <h2 className="font-semibold">{title}</h2>
                                <p className="mt-1 text-sm text-muted-foreground">{text}</p>
                            </li>
                        ))}
                    </ul>
                </main>

                <footer className="py-6 text-center text-sm text-muted-foreground">
                    © {new Date().getFullYear()} {appName}
                </footer>
            </div>
        </>
    );
}

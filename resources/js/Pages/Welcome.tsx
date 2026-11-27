import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { GuestFrame } from '@/Layouts/GuestLayout';

const features = [
    ['المبيعات اليومية', 'سجّل البيع بالكيلو وتابع المقبوضات وغير المدفوع لكل يوم.'],
    ['رصيد الطحين', 'يودِع الزبون طحينه ويأخذ خبزًا على دفعات برسم تبديل تحدده أنت.'],
    ['حسابات العملاء', 'المبالغ المستحقة والدفعات الجزئية لكل عميل في صفحة واحدة.'],
    ['مخبز مستقل', 'لكل مخبز حسابه ولوحته وبياناته، باشتراك شهري يجدده مالك المنصة.'],
];

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="نظام إدارة المخابز" />
            <GuestFrame>
                <h1 className="text-4xl leading-tight font-semibold">نظام إدارة المخابز</h1>
                <p className="mt-4 text-muted-foreground">مبيعات اليوم، حسابات العملاء، ورصيد الطحين مقابل الخبز، من هاتفك وأنت أمام الفرن.</p>
                <Button asChild className="mt-8 h-11 w-full text-[15px] font-semibold">
                    {auth.user ? <Link href={route('dashboard')}>الذهاب إلى لوحة التحكم</Link> : <Link href={route('login')}>تسجيل الدخول</Link>}
                </Button>
                <ul className="mt-10 grid gap-5 border-t pt-8">
                    {features.map(([title, text]) => (
                        <li key={title}>
                            <h2 className="font-semibold">{title}</h2>
                            <p className="mt-1 text-sm text-muted-foreground">{text}</p>
                        </li>
                    ))}
                </ul>
            </GuestFrame>
        </>
    );
}

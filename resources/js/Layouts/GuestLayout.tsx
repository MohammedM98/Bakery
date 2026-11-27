import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AppLogoIcon } from '@/components/app-logo';
import { FlashToast } from '@/components/flash-toast';
import { ThemeToggle } from '@/components/theme-toggle';

interface GuestLayoutProps {
    title: string;
    description?: string;
    children: ReactNode;
}

/** The app's calm background and rounded frame: the form beside a dark slate panel on desktop, one column on the phone. */
export function GuestFrame({ children, appName: name }: { children: ReactNode; /** For the error page, which may render without shared props. */ appName?: string }) {
    const props = usePage().props;
    const appName = name ?? props.appName;

    return (
        <div className="flex min-h-svh md:p-7">
            <div className="mx-auto grid w-full max-w-[1120px] flex-1 overflow-clip bg-frame md:rounded-[2rem] md:shadow-frame lg:grid-cols-[1fr_1.05fr]">
                <div className="flex flex-col px-4 py-5 sm:px-8 md:py-7">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3 font-semibold lg:invisible">
                            <AppLogoIcon />
                            <span>{appName}</span>
                        </div>
                        <ThemeToggle className="size-[42px] rounded-xl border-border bg-card" />
                    </div>
                    <div className="flex flex-1 items-center justify-center py-10">
                        <div className="w-full max-w-sm">{children}</div>
                    </div>
                </div>
                <aside className="relative m-3 hidden flex-col justify-between overflow-hidden rounded-[1.5rem] bg-sidebar p-10 text-sidebar-foreground after:pointer-events-none after:absolute after:-start-16 after:-bottom-20 after:size-[360px] after:rounded-full after:border-[44px] after:border-white/[0.035] lg:flex">
                    <div className="flex items-center gap-3 text-lg font-semibold text-sidebar-accent-foreground">
                        <AppLogoIcon className="bg-sidebar-accent" />
                        <span>{appName}</span>
                    </div>
                    <div className="relative z-10 max-w-md">
                        <p className="text-3xl leading-snug font-semibold text-sidebar-accent-foreground">مبيعات اليوم، أرصدة الزبائن، وحساب الطحين في مكان واحد</p>
                        <p className="mt-4">سجّل البيع من هاتفك وأنت أمام الفرن</p>
                    </div>
                    <p className="relative z-10 text-sm">الحسابات ينشئها مالك المنصة. تواصل معه إن لم يكن لديك حساب.</p>
                </aside>
            </div>
            {props.flash && <FlashToast />}
        </div>
    );
}

export default function GuestLayout({ title, description, children }: GuestLayoutProps) {
    return (
        <>
            <Head title={title} />
            <GuestFrame>
                <h1 className="text-[26px] leading-tight font-semibold">{title}</h1>
                {description && <p className="mt-2 text-sm text-muted-foreground">{description}</p>}
                <div className="mt-8">{children}</div>
            </GuestFrame>
        </>
    );
}

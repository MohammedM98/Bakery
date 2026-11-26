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

export default function GuestLayout({ title, description, children }: GuestLayoutProps) {
    const { appName } = usePage().props;

    return (
        <div className="grid min-h-svh lg:grid-cols-2">
            <Head title={title} />
            <div className="flex flex-col px-4 py-6 sm:px-8">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2 font-bold lg:invisible">
                        <AppLogoIcon />
                        <span>{appName}</span>
                    </div>
                    <ThemeToggle />
                </div>
                <div className="flex flex-1 items-center justify-center py-10">
                    <div className="w-full max-w-sm">
                        <h1 className="text-2xl font-bold">{title}</h1>
                        {description && <p className="mt-2 text-sm text-muted-foreground">{description}</p>}
                        <div className="mt-8">{children}</div>
                    </div>
                </div>
            </div>
            <aside className="hidden flex-col justify-between bg-sidebar p-10 text-sidebar-foreground lg:flex">
                <div className="flex items-center gap-3 text-lg font-bold">
                    <AppLogoIcon />
                    <span>{appName}</span>
                </div>
                <div className="max-w-md">
                    <p className="text-3xl leading-snug font-bold text-sidebar-accent-foreground">
                        مبيعات اليوم، أرصدة الزبائن، وحساب الطحين في مكان واحد.
                    </p>
                    <p className="mt-4 text-sidebar-foreground/70">سجّل البيع من هاتفك وأنت أمام الفرن.</p>
                </div>
                <p className="text-sm text-sidebar-foreground/60">الحسابات ينشئها مالك المنصة. تواصل معه إن لم يكن لديك حساب.</p>
            </aside>
            <FlashToast />
        </div>
    );
}

import { Head, Link, router, usePage } from '@inertiajs/react';
import { Building2, History, Landmark, LayoutDashboard, LogOut, Menu, Receipt, Settings, ShieldCheck, ShoppingBasket, UserRound, Users, type LucideIcon } from 'lucide-react';
import type { CSSProperties, ReactNode } from 'react';
import { AppLogoIcon } from '@/components/app-logo';
import { FlashToast } from '@/components/flash-toast';
import { ThemeToggle } from '@/components/theme-toggle';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
    useSidebar,
} from '@/components/ui/sidebar';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useCan } from '@/lib/can';
import { cn } from '@/lib/utils';
import type { Permission, Role } from '@/types';

interface NavItem {
    label: string;
    route: string;
    active: string;
    icon: LucideIcon;
    /** Shown only to whoever holds it. */
    permission?: Permission;
}

const panel: NavItem[] = [
    { label: 'لوحة التحكم', route: 'panel.dashboard', active: 'panel.dashboard', icon: LayoutDashboard, permission: 'view-dashboard' },
    { label: 'العملاء', route: 'panel.customers.index', active: 'panel.customers.*', icon: Users, permission: 'view-customers' },
    { label: 'المبيعات', route: 'panel.sales.index', active: 'panel.sales.*', icon: Receipt, permission: 'view-sales' },
    { label: 'تسجيل بيع', route: 'panel.sales.create', active: 'panel.sales.create', icon: ShoppingBasket, permission: 'record-sale' },
    { label: 'سجل المبيعات', route: 'panel.sales-log', active: 'panel.sales-log', icon: History, permission: 'view-sales-log' },
    { label: 'البنوك والمحافظ', route: 'panel.banks.index', active: 'panel.banks.*', icon: Landmark, permission: 'view-banks' },
    { label: 'المستخدمون', route: 'panel.users.index', active: 'panel.users.*', icon: ShieldCheck, permission: 'manage-users' },
];

const settings: NavItem = { label: 'الإعدادات', route: 'panel.settings.edit', active: 'panel.settings.*', icon: Settings, permission: 'manage-settings' };

const navigation: Record<Role, NavItem[]> = {
    super_admin: [{ label: 'المخابز والاشتراكات', route: 'admin.bakeries.index', active: 'admin.bakeries.*', icon: Building2 }],
    bakery_owner: panel,
    bakery_staff: panel,
};

const roleLabels: Record<Role, string> = {
    super_admin: 'مالك المنصة',
    bakery_owner: 'صاحب المخبز',
    bakery_staff: 'موظف المخبز',
};

/** Icon button in the rail; in the mobile sheet the label shows beside the icon. */
const railButton = cn(
    'h-11 gap-3 rounded-[14px] px-3 text-sm [&_svg]:size-5',
    'md:size-11! md:justify-center md:p-0!',
    'data-active:shadow-[inset_-3px_0_0_var(--sidebar-primary)]',
);

function NavLink({ item }: { item: NavItem }) {
    const { setOpenMobile } = useSidebar();
    return (
        <SidebarMenuItem className="md:flex md:justify-center">
            <SidebarMenuButton asChild isActive={route().current(item.active)} tooltip={{ children: item.label, side: 'left' }} className={railButton}>
                <Link href={route(item.route)} onClick={() => setOpenMobile(false)}>
                    <item.icon />
                    <span>{item.label}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}

/** Mobile: the main items at the bottom of the screen, and "المزيد" for the whole menu. */
function BottomBar({ items }: { items: NavItem[] }) {
    const { setOpenMobile } = useSidebar();
    const cell = 'flex size-11 items-center justify-center rounded-[14px] text-sidebar-foreground outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring [&_svg]:size-5';
    return (
        <nav
            aria-label="القائمة السفلية"
            className="fixed inset-x-0 bottom-0 z-20 flex items-center justify-around bg-sidebar px-1.5 pt-2 pb-[calc(0.5rem+env(safe-area-inset-bottom))] md:hidden"
        >
            {items.slice(0, 4).map((item) => {
                const active = route().current(item.active);
                return (
                    <Link
                        key={item.route}
                        href={route(item.route)}
                        aria-label={item.label}
                        aria-current={active ? 'page' : undefined}
                        className={cn(cell, active && 'bg-sidebar-accent text-sidebar-accent-foreground shadow-[inset_-3px_0_0_var(--sidebar-primary)]')}
                    >
                        <item.icon aria-hidden />
                    </Link>
                );
            })}
            <button type="button" className={cell} aria-label="المزيد" onClick={() => setOpenMobile(true)}>
                <Menu aria-hidden />
            </button>
        </nav>
    );
}

interface AppLayoutProps {
    title: string;
    /** Under the title; defaults to the bakery's name. */
    subtitle?: string;
    /** Beside the title: the period pills on pages that use them. */
    toolbar?: ReactNode;
    children: ReactNode;
}

export default function AppLayout({ title, subtitle, toolbar, children }: AppLayoutProps) {
    const { auth, appName } = usePage().props;
    const user = auth.user!;
    const can = useCan();
    const allowed = (item: NavItem) => !item.permission || can(item.permission);
    const items = (navigation[user.role] ?? []).filter(allowed)
        // "تسجيل بيع" only for those who cannot open the sales list, where the button already is.
        .filter((item) => item.route !== 'panel.sales.create' || !can('view-sales'));
    const footer = user.role === 'super_admin' ? [] : [settings].filter(allowed);

    return (
        <TooltipProvider delayDuration={0}>
            {/* Desktop: the app sits in a large rounded frame over the calm background; the rail stays an icon strip. */}
            <SidebarProvider
                open={false}
                style={{ '--sidebar-width-icon': '76px' } as CSSProperties}
                className="md:mx-auto md:my-7 md:min-h-[calc(100svh-3.5rem)] md:w-[calc(100%-3.5rem)] md:max-w-[1320px] md:overflow-clip md:rounded-[2rem] md:bg-sidebar md:shadow-frame"
            >
                <Head title={title} />
                <Sidebar side="right" collapsible="icon" className="md:sticky md:top-7 md:h-[calc(100svh-3.5rem)] md:border-0">
                    <SidebarHeader className="items-center pt-5 pb-4 max-md:items-stretch">
                        <Link
                            href={route('dashboard')}
                            className="flex items-center gap-3 rounded-[14px] outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring"
                            aria-label={auth.bakery?.name ?? appName}
                        >
                            <AppLogoIcon />
                            <span className="flex min-w-0 flex-col leading-tight md:hidden">
                                <span className="truncate font-semibold text-sidebar-accent-foreground">{auth.bakery?.name ?? appName}</span>
                                {auth.bakery && <span className="truncate text-xs">{appName}</span>}
                            </span>
                        </Link>
                    </SidebarHeader>
                    <SidebarContent>
                        <SidebarGroup className="md:px-0">
                            <SidebarGroupContent>
                                <SidebarMenu className="gap-2">
                                    {items.map((item) => (
                                        <NavLink key={item.route} item={item} />
                                    ))}
                                </SidebarMenu>
                            </SidebarGroupContent>
                        </SidebarGroup>
                    </SidebarContent>
                    <SidebarFooter className="pb-5 md:px-0">
                        <SidebarMenu className="gap-2">
                            {footer.map((item) => (
                                <NavLink key={item.route} item={item} />
                            ))}
                            <SidebarMenuItem className="md:hidden">
                                <SidebarMenuButton className={railButton} onClick={() => router.post(route('logout'))}>
                                    <LogOut />
                                    <span>تسجيل الخروج</span>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarFooter>
                </Sidebar>

                <SidebarInset className="min-w-0 bg-frame">
                    <div className="flex flex-1 flex-col px-4 pt-[18px] pb-24 md:px-8 md:pt-[26px] md:pb-8">
                        <header className="mb-[26px] flex flex-wrap items-center gap-x-5 gap-y-3.5 md:flex-nowrap">
                            <div className="min-w-0">
                                <h1 className="truncate text-[22px] leading-tight font-semibold md:text-[26px]">{title}</h1>
                                <p className="mt-0.5 truncate text-sm text-muted-foreground">{subtitle ?? auth.bakery?.name ?? appName}</p>
                            </div>
                            {toolbar && <div className="order-3 w-full md:order-none md:ms-6 md:w-auto">{toolbar}</div>}
                            <div className="ms-auto flex items-center gap-2.5">
                                <ThemeToggle className="size-[42px] rounded-xl border bg-card" />
                                <DropdownMenu>
                                    <DropdownMenuTrigger
                                        className="flex items-center gap-2.5 rounded-xl ps-1.5 text-start outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        aria-label="قائمة المستخدم"
                                    >
                                        <Avatar className="size-10">
                                            <AvatarFallback className="bg-accent font-semibold text-accent-foreground">{user.name.charAt(0)}</AvatarFallback>
                                        </Avatar>
                                        <span className="hidden max-w-40 leading-tight sm:block">
                                            <span className="block truncate font-medium">{user.name}</span>
                                            <span className="block truncate text-xs text-muted-foreground">{roleLabels[user.role]}</span>
                                        </span>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="w-56">
                                        <DropdownMenuLabel className="flex flex-col">
                                            <span className="truncate">{user.name}</span>
                                            <span className="truncate text-xs font-normal text-muted-foreground" dir="ltr">
                                                {user.email}
                                            </span>
                                        </DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem asChild>
                                            <Link href={route('profile.edit')}>
                                                <UserRound />
                                                الملف الشخصي
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem onSelect={() => router.post(route('logout'))}>
                                            <LogOut />
                                            تسجيل الخروج
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </header>
                        <div className="flex-1">{children}</div>
                    </div>
                </SidebarInset>
                <BottomBar items={[...items, ...footer]} />
                <FlashToast />
            </SidebarProvider>
        </TooltipProvider>
    );
}

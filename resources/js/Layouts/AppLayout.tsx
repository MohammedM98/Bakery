import { Head, Link, router, usePage } from '@inertiajs/react';
import { Building2, ChevronDown, History, Landmark, LayoutDashboard, LogOut, Receipt, Settings, ShieldCheck, ShoppingBasket, UserRound, Users, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { AppLogoIcon } from '@/components/app-logo';
import { FlashToast } from '@/components/flash-toast';
import { ThemeToggle } from '@/components/theme-toggle';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
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
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useCan } from '@/lib/can';
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
    { label: 'الإعدادات', route: 'panel.settings.edit', active: 'panel.settings.*', icon: Settings, permission: 'manage-settings' },
];

const navigation: Record<Role, NavItem[]> = {
    super_admin: [{ label: 'المخابز والاشتراكات', route: 'admin.bakeries.index', active: 'admin.bakeries.*', icon: Building2 }],
    bakery_owner: panel,
    bakery_staff: panel,
};

export default function AppLayout({ title, children }: { title: string; children: ReactNode }) {
    const { auth, appName } = usePage().props;
    const user = auth.user!;
    const can = useCan();
    const items = (navigation[user.role] ?? []).filter((item) => !item.permission || can(item.permission))
        // "تسجيل بيع" only for those who cannot open the sales list, where the button already is.
        .filter((item) => item.route !== 'panel.sales.create' || !can('view-sales'));

    return (
        <TooltipProvider delayDuration={0}>
        <SidebarProvider defaultOpen={!document.cookie.includes('sidebar_state=false')}>
            <Head title={title} />
            <Sidebar side="right" collapsible="icon">
                <SidebarHeader>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton size="lg" asChild tooltip={appName}>
                                <Link href={route('dashboard')}>
                                    <AppLogoIcon />
                                    <span className="flex min-w-0 flex-col leading-tight">
                                        <span className="truncate font-semibold">{auth.bakery?.name ?? appName}</span>
                                        {auth.bakery && <span className="truncate text-xs text-sidebar-foreground/70">{appName}</span>}
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarHeader>
                <SidebarContent>
                    <SidebarGroup>
                        <SidebarGroupContent>
                            <SidebarMenu className="gap-1">
                                {items.map((item) => (
                                    <SidebarMenuItem key={item.route}>
                                        <SidebarMenuButton
                                            asChild
                                            size="lg"
                                            isActive={route().current(item.active)}
                                            tooltip={item.label}
                                        >
                                            <Link href={route(item.route)}>
                                                <item.icon />
                                                <span>{item.label}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                ))}
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>
                </SidebarContent>
                <SidebarFooter>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild size="lg" isActive={route().current('profile.edit')} tooltip="الملف الشخصي">
                                <Link href={route('profile.edit')}>
                                    <UserRound />
                                    <span>الملف الشخصي</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarFooter>
            </Sidebar>

            <SidebarInset>
                <header className="sticky top-0 z-10 flex h-16 items-center gap-2 border-b bg-background/95 px-4 backdrop-blur md:px-8">
                    <SidebarTrigger className="size-10" aria-label="القائمة" />
                    <h1 className="min-w-0 flex-1 truncate text-lg font-semibold">{title}</h1>
                    <ThemeToggle />
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="h-10 gap-2 px-2" aria-label="قائمة المستخدم">
                                <Avatar className="size-8">
                                    <AvatarFallback className="bg-secondary font-semibold text-secondary-foreground">
                                        {user.name.charAt(0)}
                                    </AvatarFallback>
                                </Avatar>
                                <span className="hidden max-w-40 truncate sm:inline">{user.name}</span>
                                <ChevronDown className="hidden text-muted-foreground sm:block" />
                            </Button>
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
                </header>
                <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-6 md:px-8 md:py-8">{children}</main>
            </SidebarInset>
            <FlashToast />
        </SidebarProvider>
        </TooltipProvider>
    );
}

import { Head, Link, router, usePage } from '@inertiajs/react';
import { Building2, ChevronDown, LayoutDashboard, LogOut, Receipt, Settings, UserRound, Users, type LucideIcon } from 'lucide-react';
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
import type { Role } from '@/types';

interface NavItem {
    label: string;
    route: string;
    active: string;
    icon: LucideIcon;
}

const navigation: Record<Role, NavItem[]> = {
    super_admin: [{ label: 'المخابز والاشتراكات', route: 'admin.bakeries.index', active: 'admin.bakeries.*', icon: Building2 }],
    bakery_owner: [
        { label: 'لوحة التحكم', route: 'panel.dashboard', active: 'panel.dashboard', icon: LayoutDashboard },
        { label: 'العملاء', route: 'panel.customers.index', active: 'panel.customers.*', icon: Users },
        { label: 'المبيعات', route: 'panel.sales.index', active: 'panel.sales.*', icon: Receipt },
        { label: 'الإعدادات', route: 'panel.settings.edit', active: 'panel.settings.*', icon: Settings },
    ],
};

export default function AppLayout({ title, children }: { title: string; children: ReactNode }) {
    const { auth, appName } = usePage().props;
    const user = auth.user!;
    const items = navigation[user.role] ?? [];

    return (
        <SidebarProvider>
            <Head title={title} />
            <Sidebar side="right" collapsible="icon">
                <SidebarHeader>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton size="lg" asChild tooltip={appName}>
                                <a href={route('dashboard')}>
                                    <AppLogoIcon />
                                    <span className="flex min-w-0 flex-col leading-tight">
                                        <span className="truncate font-bold">{auth.bakery?.name ?? appName}</span>
                                        {auth.bakery && <span className="truncate text-xs text-sidebar-foreground/70">{appName}</span>}
                                    </span>
                                </a>
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
                                            {/* shortcut: these pages are still Blade/Livewire, so a full page load; switch to <Link> when each is converted. */}
                                            <a href={route(item.route)}>
                                                <item.icon />
                                                <span>{item.label}</span>
                                            </a>
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
                    <h1 className="min-w-0 flex-1 truncate text-lg font-bold">{title}</h1>
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
    );
}

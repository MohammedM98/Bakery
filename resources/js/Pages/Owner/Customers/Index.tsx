import { Link, router } from '@inertiajs/react';
import { LoaderCircle, Pencil, Plus, Search, Users } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { KgText } from '@/components/kg-text';
import { PaginationLinks } from '@/components/pagination-links';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/Layouts/AppLayout';
import type { Paginated } from '@/types';

interface CustomerRow {
    id: number;
    name: string;
    mobile_number: string;
    flour_balance_kg: string;
}

interface Props {
    customers: Paginated<CustomerRow>;
    search: string;
}

function EditButton({ customer }: { customer: CustomerRow }) {
    return (
        <Button variant="ghost" size="icon" className="size-11 shrink-0" asChild>
            <Link href={route('panel.customers.edit', customer.id)} aria-label={`تعديل ${customer.name}`}>
                <Pencil />
            </Link>
        </Button>
    );
}

// shortcut: the customer page is still Livewire (session 3), so a full page load; switch to <Link> when it is converted.
function CustomerLink({ customer, className }: { customer: CustomerRow; className?: string }) {
    return (
        <a href={route('panel.customers.show', customer.id)} className={className}>
            {customer.name}
        </a>
    );
}

export default function Index({ customers, search }: Props) {
    const [query, setQuery] = useState(search);
    const [searching, setSearching] = useState(false);
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const timer = setTimeout(() => {
            router.get(route('panel.customers.index'), query.trim() ? { search: query.trim() } : {}, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['customers', 'search'],
                onStart: () => setSearching(true),
                onFinish: () => setSearching(false),
            });
        }, 350);
        return () => clearTimeout(timer);
    }, [query]);

    const addButton = (
        <Button asChild className="h-11 px-5">
            <Link href={route('panel.customers.create')}>
                <Plus />
                إضافة عميل
            </Link>
        </Button>
    );

    const noCustomersAtAll = customers.total === 0 && search === '';

    return (
        <AppLayout title="العملاء">
            <div className="grid gap-6">
                {noCustomersAtAll ? (
                    <EmptyState
                        icon={Users}
                        title="لا يوجد عملاء بعد"
                        description="أضف عملاءك لتسجّل عليهم المبيعات وتتابع أرصدتهم من القمح والمال."
                        action={addButton}
                    />
                ) : (
                    <>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="relative w-full sm:max-w-sm">
                                <label htmlFor="search" className="sr-only">
                                    بحث في العملاء
                                </label>
                                <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden />
                                <Input
                                    id="search"
                                    type="search"
                                    className="h-11 ps-9 pe-9"
                                    placeholder="ابحث بالاسم أو رقم الجوال"
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                />
                                {searching && (
                                    <LoaderCircle className="absolute end-3 top-1/2 size-4 -translate-y-1/2 animate-spin text-muted-foreground" aria-hidden />
                                )}
                            </div>
                            {addButton}
                        </div>

                        {customers.data.length === 0 ? (
                            <EmptyState
                                icon={Search}
                                title={`لا يوجد عملاء مطابقون لـ "${search}"`}
                                description="جرّب جزءًا من الاسم أو آخر أرقام الجوال."
                                action={
                                    <Button variant="outline" className="h-11" onClick={() => setQuery('')}>
                                        مسح البحث
                                    </Button>
                                }
                            />
                        ) : (
                            <>
                                <ul className="grid gap-3 md:hidden">
                                    {customers.data.map((c) => (
                                        <li key={c.id}>
                                            <Card className="flex-row items-center gap-3 p-4">
                                                <div className="min-w-0 flex-1">
                                                    <CustomerLink customer={c} className="block truncate font-semibold" />
                                                    <span dir="ltr" className="block text-end text-sm text-muted-foreground tabular-nums">
                                                        {c.mobile_number}
                                                    </span>
                                                </div>
                                                <div className="text-end">
                                                    <span className="block text-xs text-muted-foreground">رصيد القمح</span>
                                                    <KgText kg={c.flour_balance_kg} className="font-semibold" />
                                                </div>
                                                <EditButton customer={c} />
                                            </Card>
                                        </li>
                                    ))}
                                </ul>

                                <Card className="hidden py-0 md:block">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="ps-6">الاسم</TableHead>
                                                <TableHead>رقم الجوال</TableHead>
                                                <TableHead>رصيد القمح</TableHead>
                                                <TableHead className="w-16 pe-6">
                                                    <span className="sr-only">تعديل</span>
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {customers.data.map((c) => (
                                                <TableRow key={c.id}>
                                                    <TableCell className="ps-6">
                                                        <CustomerLink customer={c} className="font-medium underline-offset-4 hover:underline" />
                                                    </TableCell>
                                                    <TableCell>
                                                        <span dir="ltr" className="tabular-nums">
                                                            {c.mobile_number}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell>
                                                        <KgText kg={c.flour_balance_kg} />
                                                    </TableCell>
                                                    <TableCell className="pe-6">
                                                        <EditButton customer={c} />
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </Card>

                                <PaginationLinks page={customers} />
                            </>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}

import { Link, router } from '@inertiajs/react';
import { Plus, Receipt, SearchX, Wheat } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { Field } from '@/components/form-field';
import { PaginationLinks } from '@/components/pagination-links';
import { CashSaleDialog, SaleList } from '@/components/sales';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useCan } from '@/lib/can';
import AppLayout from '@/Layouts/AppLayout';
import type { Paginated, SaleRow } from '@/types';

interface Filters {
    type: 'cash' | 'flour_exchange';
    status: '' | 'paid' | 'unpaid';
    date: string;
}

interface Props {
    sales: Paginated<SaleRow>;
    filters: Filters;
    pricePerKg: string;
}

export default function Index({ sales, filters, pricePerKg }: Props) {
    const [recording, setRecording] = useState(false);
    const can = useCan();
    const isCash = filters.type === 'cash';
    const filtered = filters.status !== '' || filters.date !== '';

    const visit = (next: Partial<Filters>) => {
        const params = { ...filters, ...next };
        router.get(
            route('panel.sales.index'),
            Object.fromEntries(Object.entries(params).filter(([key, value]) => value !== '' && !(key === 'type' && value === 'cash'))),
            { preserveState: true, preserveScroll: true, replace: true, only: ['sales', 'filters', 'flash'] }, // flash too, or the last toast replays
        );
    };

    const recordButton = can('record-sale') && (
        <Button className="h-11 w-full px-5 sm:w-auto" onClick={() => setRecording(true)}>
            <Plus />
            تسجيل عملية بيع
        </Button>
    );

    return (
        <AppLayout title="المبيعات">
            <div className="grid gap-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Tabs value={filters.type} onValueChange={(type) => visit({ type: type as Filters['type'] })} dir="rtl">
                        <TabsList className="h-11 w-full sm:w-auto group-data-horizontal/tabs:h-11">
                            <TabsTrigger value="cash" className="px-4">
                                البيع النقدي
                            </TabsTrigger>
                            <TabsTrigger value="flour_exchange" className="px-4">
                                البيع مقابل الطحين
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
                    {isCash && recordButton}
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="w-40">
                        <Field id="status" label="الحالة">
                            <Select value={filters.status || 'all'} onValueChange={(v) => visit({ status: v === 'all' ? '' : (v as Filters['status']) })} dir="rtl">
                                <SelectTrigger id="status" className="w-full data-[size=default]:h-11">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">الكل</SelectItem>
                                    <SelectItem value="paid">مدفوع</SelectItem>
                                    <SelectItem value="unpaid">غير مدفوع</SelectItem>
                                </SelectContent>
                            </Select>
                        </Field>
                    </div>
                    <div className="w-44">
                        <Field id="date" label="التاريخ">
                            <Input id="date" type="date" className="h-11" value={filters.date} onChange={(e) => visit({ date: e.target.value })} />
                        </Field>
                    </div>
                    {filtered && (
                        <Button variant="ghost" className="h-11" onClick={() => visit({ status: '', date: '' })}>
                            مسح التصفية
                        </Button>
                    )}
                </div>

                {sales.data.length > 0 ? (
                    <>
                        <SaleList sales={sales.data} />
                        <PaginationLinks page={sales} />
                    </>
                ) : filtered ? (
                    <EmptyState
                        icon={SearchX}
                        title="لا توجد عمليات مطابقة للتصفية"
                        action={
                            <Button variant="outline" className="h-11" onClick={() => visit({ status: '', date: '' })}>
                                مسح التصفية
                            </Button>
                        }
                    />
                ) : isCash ? (
                    <EmptyState icon={Receipt} title="لا توجد عمليات بيع بعد" description="سجّل أول عملية بيع نقدي بالكيلو" action={recordButton} />
                ) : (
                    <EmptyState
                        icon={Wheat}
                        title="لا توجد عمليات بيع مقابل الطحين"
                        description="يُسجَّل تسليم الخبز مقابل الطحين من صفحة العميل، على رصيده من الطحين"
                        action={
                            can('view-customers') && (
                                <Button variant="outline" asChild className="h-11">
                                    <Link href={route('panel.customers.index')}>الذهاب إلى العملاء</Link>
                                </Button>
                            )
                        }
                    />
                )}
            </div>

            <CashSaleDialog open={recording} onOpenChange={setRecording} pricePerKg={pricePerKg} />
        </AppLayout>
    );
}

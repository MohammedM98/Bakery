import { Link, router } from '@inertiajs/react';
import { Plus, Receipt, SearchX, Wheat } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { Pills } from '@/components/period-pills';
import { DatePicker } from '@/components/date-picker';
import { Field } from '@/components/form-field';
import { PaginationLinks } from '@/components/pagination-links';
import { CashSaleDialog, SaleList } from '@/components/sales';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
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
                    <Pills
                        label="نوع البيع"
                        options={[
                            ['cash', 'البيع النقدي'],
                            ['flour_exchange', 'البيع مقابل الطحين'],
                        ]}
                        value={filters.type}
                        onChange={(type) => visit({ type: type as Filters['type'] })}
                    />
                    {isCash && recordButton}
                </div>

                <div className="grid grid-cols-2 items-end gap-3 sm:flex sm:flex-wrap">
                    <div className="sm:w-40">
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
                    <div className="sm:w-44">
                        <Field id="date" label="التاريخ">
                            <DatePicker id="date" value={filters.date} onChange={(date) => visit({ date })} placeholder="كل الأيام" clearLabel="كل الأيام" />
                        </Field>
                    </div>
                    {filtered && (
                        <Button variant="ghost" className="col-span-2 h-11 sm:col-auto" onClick={() => visit({ status: '', date: '' })}>
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

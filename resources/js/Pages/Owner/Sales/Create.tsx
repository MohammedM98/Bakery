import { Link } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { CashSaleFields, useCashSaleForm } from '@/components/sales';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/Layouts/AppLayout';

/** Stays on the page after saving, with the form cleared for the next sale. */
export default function Create({ pricePerKg }: { pricePerKg: string }) {
    const { form, submit } = useCashSaleForm();

    return (
        <AppLayout title="تسجيل عملية بيع">
            <Card className="mx-auto max-w-2xl">
                <CardContent>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            submit();
                        }}
                        className="grid gap-5"
                        noValidate
                    >
                        <CashSaleFields form={form} pricePerKg={pricePerKg} />
                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button variant="outline" asChild className="h-11">
                                <Link href={route('panel.sales.index')}>إلغاء</Link>
                            </Button>
                            <Button type="submit" className="h-11 px-6" disabled={form.processing}>
                                {form.processing && <LoaderCircle className="animate-spin" />}
                                حفظ العملية
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </AppLayout>
    );
}

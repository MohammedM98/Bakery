import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { CustomerForm, type CustomerFields } from '@/components/customer-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/Layouts/AppLayout';

export default function Edit({ customer }: { customer: CustomerFields }) {
    const [deleting, setDeleting] = useState(false);

    const destroy = () =>
        router.delete(route('panel.customers.destroy', customer.id), {
            onStart: () => setDeleting(true),
            onFinish: () => setDeleting(false),
        });

    return (
        <AppLayout title={`تعديل ${customer.name}`}>
            <div className="mx-auto grid max-w-2xl gap-6">
                <Card>
                    <CardContent>
                        <CustomerForm customer={customer} />
                    </CardContent>
                </Card>

                <Card className="border-destructive/30">
                    <CardHeader>
                        <CardTitle className="text-destructive">حذف العميل</CardTitle>
                        <CardDescription>يُحذف العميل مع كل سجلاته: المبيعات والدفعات واستلام القمح</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ConfirmDialog
                            trigger={
                                <Button variant="destructive" className="h-11 px-6">
                                    حذف العميل نهائيًا
                                </Button>
                            }
                            title={`حذف "${customer.name}" نهائيًا؟`}
                            description="تُحذف كل مبيعات العميل ودفعاته واستلامات القمح الخاصة به. لا يمكن التراجع عن هذا الإجراء."
                            confirmLabel="حذف نهائيًا"
                            processing={deleting}
                            onConfirm={destroy}
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

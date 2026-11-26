import { CustomerForm } from '@/components/customer-form';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/Layouts/AppLayout';

export default function Create() {
    return (
        <AppLayout title="إضافة عميل">
            <Card className="mx-auto max-w-2xl">
                <CardContent>
                    <CustomerForm />
                </CardContent>
            </Card>
        </AppLayout>
    );
}

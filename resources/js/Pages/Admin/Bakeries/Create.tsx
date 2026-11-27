import { BakeryCreateForm } from '@/components/bakery-form';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/Layouts/AppLayout';

export default function Create() {
    return (
        <AppLayout title="إضافة مخبز">
            <Card className="mx-auto max-w-2xl">
                <CardContent>
                    <BakeryCreateForm />
                </CardContent>
            </Card>
        </AppLayout>
    );
}

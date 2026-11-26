import { StaffForm, type PermissionGroup } from '@/components/staff-form';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/Layouts/AppLayout';

export default function Create({ groups }: { groups: PermissionGroup[] }) {
    return (
        <AppLayout title="إضافة مستخدم">
            <Card className="mx-auto w-full max-w-3xl">
                <CardContent>
                    <StaffForm groups={groups} />
                </CardContent>
            </Card>
        </AppLayout>
    );
}

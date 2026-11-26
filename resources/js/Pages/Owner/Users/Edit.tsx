import { StaffForm, type PermissionGroup, type StaffFields } from '@/components/staff-form';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/Layouts/AppLayout';

export default function Edit({ user, groups }: { user: StaffFields; groups: PermissionGroup[] }) {
    return (
        <AppLayout title={`تعديل ${user.name}`}>
            <Card className="mx-auto w-full max-w-3xl">
                <CardContent>
                    <StaffForm user={user} groups={groups} />
                </CardContent>
            </Card>
        </AppLayout>
    );
}

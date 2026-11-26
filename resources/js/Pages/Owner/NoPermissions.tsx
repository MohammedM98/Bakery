import { ShieldOff } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import AppLayout from '@/Layouts/AppLayout';

/** Where a staff member lands when the owner has not given them any page to open. */
export default function NoPermissions() {
    return (
        <AppLayout title="لا توجد صلاحيات">
            <EmptyState icon={ShieldOff} title="لا توجد صلاحيات" description="لم يحدّد لك صاحب المخبز أي صلاحية بعد. تواصل معه ليمنحك ما تحتاجه." />
        </AppLayout>
    );
}

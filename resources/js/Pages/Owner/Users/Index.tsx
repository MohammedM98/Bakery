import { router } from '@inertiajs/react';
import { Pencil, Plus, Power, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DialogShell } from '@/components/form-dialog';
import { StaffForm, type PermissionGroup, type StaffFields } from '@/components/staff-form';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';
import AppLayout from '@/Layouts/AppLayout';

interface StaffRow extends StaffFields {
    permissions_count: number;
}

function StatusBadge({ user }: { user: StaffRow }) {
    return <Badge className={cn(user.is_active ? 'bg-success/15 text-success' : 'bg-muted text-muted-foreground')}>{user.is_active ? 'فعّال' : 'موقوف'}</Badge>;
}

function Actions({ user, onStatus, onEdit }: { user: StaffRow; onStatus: () => void; onEdit: () => void }) {
    return (
        <div className="flex shrink-0 gap-1">
            <Button variant="ghost" size="icon" className="size-11" aria-label={`تعديل ${user.name} وصلاحياته`} onClick={onEdit}>
                <Pencil />
            </Button>
            <Button variant="ghost" size="icon" className="size-11 text-muted-foreground" onClick={onStatus} aria-label={user.is_active ? `إيقاف ${user.name}` : `تفعيل ${user.name}`}>
                <Power />
            </Button>
        </div>
    );
}

export default function Index({ users, permissionsTotal, groups }: { users: StaffRow[]; permissionsTotal: number; groups: PermissionGroup[] }) {
    const [target, setTarget] = useState<StaffRow | null>(null);
    // null = closed, 'new' = add, a row = edit.
    const [editing, setEditing] = useState<StaffRow | 'new' | null>(null);
    const [processing, setProcessing] = useState(false);
    const count = (u: StaffRow) => (u.permissions_count === 0 ? 'بلا صلاحيات' : `${u.permissions_count} من ${permissionsTotal} صلاحية`);

    const setStatus = () => {
        if (!target) return;
        router.post(route('panel.users.status', target.id), { active: !target.is_active }, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setTarget(null),
        });
    };

    const addButton = (
        <Button className="h-11 px-5" onClick={() => setEditing('new')}>
            <Plus />
            إضافة مستخدم
        </Button>
    );

    return (
        <AppLayout title="المستخدمون">
            <div className="grid gap-6">
                {users.length === 0 ? (
                    <EmptyState
                        icon={ShieldCheck}
                        title="لا يوجد موظفون بعد"
                        description="أضف موظفيك ليسجّلوا المبيعات بأسمائهم، وحدّد لكل واحد ما يستطيع فعله"
                        action={addButton}
                    />
                ) : (
                    <>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-sm text-muted-foreground">كل موظف يرى ويفعل ما تحدّده له فقط، وتُنسب إليه كل حركة يسجّلها</p>
                            {addButton}
                        </div>

                        <ul className="grid gap-3 md:hidden">
                            {users.map((u) => (
                                <li key={u.id}>
                                    <Card className="flex-row items-center gap-3 p-4">
                                        <div className="grid min-w-0 flex-1 gap-1">
                                            <div className="flex items-center gap-2">
                                                <span className="truncate font-semibold">{u.name}</span>
                                                <StatusBadge user={u} />
                                            </div>
                                            <span dir="ltr" className="truncate text-end text-sm text-muted-foreground">
                                                {u.email}
                                            </span>
                                            <span className="text-sm text-muted-foreground tabular-nums">{count(u)}</span>
                                        </div>
                                        <Actions user={u} onStatus={() => setTarget(u)} onEdit={() => setEditing(u)} />
                                    </Card>
                                </li>
                            ))}
                        </ul>

                        <Card className="hidden py-0 md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="ps-6">الاسم</TableHead>
                                        <TableHead>البريد الإلكتروني</TableHead>
                                        <TableHead>الصلاحيات</TableHead>
                                        <TableHead>الحالة</TableHead>
                                        <TableHead className="w-28 pe-6">
                                            <span className="sr-only">إجراءات</span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {users.map((u) => (
                                        <TableRow key={u.id} className={cn(!u.is_active && 'text-muted-foreground')}>
                                            <TableCell className="ps-6 font-medium">{u.name}</TableCell>
                                            <TableCell>
                                                <span dir="ltr">{u.email}</span>
                                            </TableCell>
                                            <TableCell>{count(u)}</TableCell>
                                            <TableCell>
                                                <StatusBadge user={u} />
                                            </TableCell>
                                            <TableCell className="pe-6">
                                                <Actions user={u} onStatus={() => setTarget(u)} onEdit={() => setEditing(u)} />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Card>
                    </>
                )}
            </div>

            <ConfirmDialog
                open={target !== null}
                onOpenChange={(open) => !open && setTarget(null)}
                title={target?.is_active ? `إيقاف حساب «${target.name}»؟` : `تفعيل حساب «${target?.name}»؟`}
                description={
                    target?.is_active
                        ? 'لن يستطيع الدخول، ويُخرَج من النظام فورًا إن كان داخلًا. حركاته السابقة تبقى باسمه، ويمكنك تفعيله لاحقًا.'
                        : 'يستطيع الدخول من جديد بنفس البريد وكلمة المرور وبنفس صلاحياته.'
                }
                confirmLabel={target?.is_active ? 'إيقاف الحساب' : 'تفعيل الحساب'}
                variant={target?.is_active ? 'destructive' : 'default'}
                processing={processing}
                onConfirm={setStatus}
            />
            <DialogShell
                wide
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
                title={editing === 'new' || !editing ? 'إضافة مستخدم' : `تعديل ${editing.name}`}
            >
                <StaffForm key={editing === 'new' ? 'new' : editing?.id} user={editing === 'new' ? undefined : (editing ?? undefined)} groups={groups} onDone={() => setEditing(null)} />
            </DialogShell>
        </AppLayout>
    );
}

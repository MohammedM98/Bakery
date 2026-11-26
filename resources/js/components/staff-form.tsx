import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { ComponentProps, FormEvent } from 'react';
import { Field } from '@/components/form-field';
import { InputError } from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import type { Permission } from '@/types';

/** User::PERMISSIONS from the server, in its groups. */
export interface PermissionGroup {
    label: string;
    permissions: { key: Permission; label: string }[];
}

export interface StaffFields {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    permissions: Permission[];
}

/** Add when `user` is absent (password required), edit otherwise (an empty password keeps the current one). */
export function StaffForm({ user, groups }: { user?: StaffFields; groups: PermissionGroup[] }) {
    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        password: '',
        permissions: user?.permissions ?? ([] as Permission[]),
    });

    const toggle = (key: Permission, on: boolean) =>
        form.setData('permissions', on ? [...form.data.permissions, key] : form.data.permissions.filter((p) => p !== key));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onError: () => form.reset('password') };
        if (user) form.put(route('panel.users.update', user.id), options);
        else form.post(route('panel.users.store'), options);
    };

    const input = (key: 'name' | 'email' | 'password', props: ComponentProps<typeof Input> = {}) => (
        <Input
            id={key}
            className="h-11"
            value={form.data[key]}
            onChange={(e) => form.setData(key, e.target.value)}
            aria-invalid={!!form.errors[key]}
            aria-describedby={`${key}-error`}
            {...props}
        />
    );

    // permissions.N errors (an unknown key) are shown once under the list.
    const permissionsError = form.errors.permissions ?? Object.entries(form.errors).find(([k]) => k.startsWith('permissions.'))?.[1];

    return (
        <form onSubmit={submit} className="grid gap-6" noValidate>
            <div className="grid gap-5">
                <Field id="name" label="الاسم" error={form.errors.name}>
                    {input('name', { autoFocus: !user, autoComplete: 'off' })}
                </Field>
                <Field id="email" label="البريد الإلكتروني" hint="يدخل به الموظف إلى النظام" error={form.errors.email}>
                    {input('email', { type: 'email', dir: 'ltr', autoComplete: 'off', className: 'h-11 text-end' })}
                </Field>
                <Field
                    id="password"
                    label={user ? 'كلمة مرور جديدة (اختياري)' : 'كلمة المرور'}
                    hint={user ? 'اتركها فارغة لتبقى كلمة المرور الحالية' : '8 أحرف على الأقل'}
                    error={form.errors.password}
                >
                    {input('password', { type: 'password', dir: 'ltr', autoComplete: 'new-password', className: 'h-11 text-end' })}
                </Field>
            </div>

            <fieldset className="grid gap-4" aria-describedby="permissions-error">
                <legend className="mb-1 font-semibold">الصلاحيات</legend>
                <p className="-mt-2 text-sm text-muted-foreground">
                    لا يرى الموظف ولا يفعل إلا ما تحدّده هنا. حذف الزبائن والبنوك وإدارة المستخدمين والأسعار تبقى لك وحدك.
                </p>
                <div className="grid gap-3 sm:grid-cols-2">
                    {groups.map((group) => (
                        <div key={group.label} role="group" aria-labelledby={`group-${group.label}`} className="rounded-xl border p-3">
                            <p id={`group-${group.label}`} className="px-1 pb-1 text-sm font-medium text-muted-foreground">
                                {group.label}
                            </p>
                            {group.permissions.map((p) => (
                                // The whole row is the label: a 44px touch target.
                                <label key={p.key} className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-1 hover:bg-accent/50">
                                    <Checkbox checked={form.data.permissions.includes(p.key)} onCheckedChange={(on) => toggle(p.key, on === true)} />
                                    <span className="text-sm">{p.label}</span>
                                </label>
                            ))}
                        </div>
                    ))}
                </div>
                <InputError id="permissions-error" message={permissionsError} />
            </fieldset>

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button type="button" variant="outline" className="h-11 px-6" asChild>
                    <Link href={route('panel.users.index')}>إلغاء</Link>
                </Button>
                <Button type="submit" className="h-11 px-6" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    {user ? 'حفظ التعديلات' : 'إضافة المستخدم'}
                </Button>
            </div>
        </form>
    );
}

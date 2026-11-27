import { router, useLocalSearchParams } from 'expo-router';
import { Eye, EyeOff } from 'lucide-react-native';
import * as React from 'react';
import { Pressable, View } from 'react-native';

import { Field, ListCard, Notice, TextField } from '@/components/common';
import { useConfirm } from '@/components/confirm';
import { NotAllowed, PageHeader, Screen } from '@/components/screen';
import { useToast } from '@/components/toast';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { PERMISSION_GROUPS, type StaffPermission } from '@/mock/types';
import { useApp } from '@/state/app';
import { useTheme } from '@/state/theme';

/** Staff form (option A): name, email, password and the permission checkboxes in their groups. */
export default function StaffForm() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const app = useApp();
  const toast = useToast();
  const confirm = useConfirm();
  const { theme } = useTheme();
  const existing = app.state.staff.find((s) => String(s.id) === id);
  const [name, setName] = React.useState(existing?.name ?? '');
  const [email, setEmail] = React.useState(existing?.email ?? '');
  const [password, setPassword] = React.useState('');
  const [show, setShow] = React.useState(false);
  const [perms, setPerms] = React.useState<StaffPermission[]>(existing?.permissions ?? []);
  const [error, setError] = React.useState<string | null>(null);
  if (!app.can('manage-users')) return <NotAllowed />;
  const online = app.state.online;
  const toggle = (k: StaffPermission) => setPerms((p) => (p.includes(k) ? p.filter((x) => x !== k) : [...p, k]));
  const actionsOnly = perms.length > 0 && !perms.includes('view-customers') && perms.some((p) => ['record-payment', 'record-deposit', 'record-flour-sale', 'record-adjustment'].includes(p));

  const save = () => {
    const r = app.saveStaff(existing?.id ?? null, { name, email, password, permissions: perms });
    if (!r.ok) return setError(r.error);
    toast({ kind: 'success', title: existing ? 'تم حفظ الموظف' : 'تمت إضافة الموظف', body: name });
    router.back();
  };

  return (
    <Screen>
      <PageHeader back title={existing ? existing.name : 'موظف جديد'} />
      {!online ? <Notice tone="warning" title="يحتاج إنترنت" body="الحفظ متاح عند الاتصال" /> : null}
      {error ? <Notice tone="danger" body={error} /> : null}
      <Field label="الاسم">
        <TextField value={name} onChangeText={(v) => (setName(v), setError(null))} />
      </Field>
      <Field label="البريد الإلكتروني">
        <TextField value={email} onChangeText={(v) => (setEmail(v), setError(null))} keyboardType="email-address" autoCapitalize="none" style={{ writingDirection: 'ltr', textAlign: 'right' }} />
      </Field>
      <Field label={existing ? 'كلمة مرور جديدة (اختياري)' : 'كلمة المرور'} hint="8 أحرف على الأقل">
        <View className="relative">
          <TextField value={password} onChangeText={(v) => (setPassword(v), setError(null))} secureTextEntry={!show} className="pe-12" />
          <Pressable onPress={() => setShow((s) => !s)} accessibilityRole="button" accessibilityLabel={show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'} className="absolute end-1 top-[3px] h-11 w-11 items-center justify-center">
            <Icon as={show ? EyeOff : Eye} size={20} strokeWidth={1.5} className="text-muted-foreground" />
          </Pressable>
        </View>
      </Field>

      <Text variant="section" className="mb-1 mt-2">
        الصلاحيات
      </Text>
      <Text variant="muted" className="mb-3">
        الموظف الجديد بلا أي صلاحية حتى تحدّدها
      </Text>
      {PERMISSION_GROUPS.map((g) => (
        <View key={g.label} className="mb-3">
          <Text variant="muted" className="mb-1.5">
            {g.label}
          </Text>
          <ListCard>
            {g.items.map(([key, label], i) => (
              <Pressable
                key={key}
                onPress={() => toggle(key)}
                accessibilityRole="checkbox"
                accessibilityState={{ checked: perms.includes(key) }}
                className="min-h-12 flex-row items-center gap-3 py-2"
                style={i ? { borderTopWidth: 1, borderTopColor: theme.s.hairline } : undefined}>
                <Checkbox checked={perms.includes(key)} onCheckedChange={() => toggle(key)} accessibilityLabel={label} />
                <Text className="flex-1">{label}</Text>
              </Pressable>
            ))}
          </ListCard>
        </View>
      ))}
      {actionsOnly ? <Notice tone="warning" body="الدفعة والإيداع والتسوية تُسجَّل من صفحة الزبون؛ أضف «عرض الزبائن» حتى يصل إليها الموظف" /> : null}
      <Notice body="صاحب المخبز وحده يحذف الزبائن والبنوك ويغيّر الأسعار ويدير المستخدمين" />

      <Button onPress={save} disabled={!online} className="w-full">
        <Text>{existing ? 'حفظ التعديل' : 'إضافة الموظف'}</Text>
      </Button>
      {existing ? (
        <Button
          variant={existing.is_active ? 'danger' : 'glass'}
          className="mt-2 w-full"
          disabled={!online}
          onPress={() =>
            existing.is_active
              ? confirm({
                  title: `إيقاف حساب ${existing.name}؟`,
                  body: 'لن يستطيع الدخول، وتبقى حركاته السابقة باسمه.',
                  action: 'إيقاف',
                  destructive: true,
                  onConfirm: () => {
                    app.setStaffActive(existing.id, false);
                    toast({ kind: 'success', title: 'تم إيقاف الحساب' });
                  },
                })
              : (app.setStaffActive(existing.id, true), toast({ kind: 'success', title: 'تم تفعيل الحساب' }))
          }>
          <Text>{existing.is_active ? 'إيقاف الحساب' : 'تفعيل الحساب'}</Text>
        </Button>
      ) : null}
    </Screen>
  );
}

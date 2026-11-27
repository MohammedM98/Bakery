import { router } from 'expo-router';
import { LogOut } from 'lucide-react-native';
import * as React from 'react';

import { useLogout } from '@/components/confirm';

import { Field, Notice, Segmented, TextField, LTR_INPUT } from '@/components/common';
import { PageHeader, Screen } from '@/components/screen';
import { useToast } from '@/components/toast';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { useApp } from '@/state/app';
import { useTheme, type SchemePreference } from '@/state/theme';

/** Profile (name, email, password) for every role; changes need the internet. */
export function ProfileForm({ back = true, onBack, children }: { back?: boolean; onBack?: () => void; children?: React.ReactNode }) {
  const app = useApp();
  const logout = useLogout();
  const toast = useToast();
  const { preference, setPreference } = useTheme();
  const [name, setName] = React.useState(app.user?.name ?? '');
  const [email, setEmail] = React.useState(app.user?.email ?? '');
  const [current, setCurrent] = React.useState('');
  const [next, setNext] = React.useState('');
  const [error, setError] = React.useState<{ field: 'profile' | 'password'; text: string } | null>(null);
  const online = app.state.online;
  const saveProfile = async () => {
    const r = await app.online('PUT', 'profile', { name, email: email.trim().toLowerCase() });
    if (!r.ok) return setError({ field: 'profile', text: r.error });
    setError(null);
    toast({ kind: 'success', title: 'تم حفظ بيانات الحساب' });
  };
  const savePassword = async () => {
    const r = await app.changePassword(current, next);
    if (!r.ok) return setError({ field: 'password', text: r.error });
    setError(null);
    setCurrent('');
    setNext('');
    toast({ kind: 'success', title: 'تم تغيير كلمة المرور', body: 'خرجت الأجهزة الأخرى من الحساب' });
  };
  return (
    <Screen net={app.user?.role !== 'super_admin' && !onBack} withNav={!onBack}>
      <PageHeader back={back} onBack={onBack} title="الملف الشخصي" />
      {!online ? <Notice tone="warning" title="يحتاج إنترنت" body="تعديل الملف الشخصي متاح عند الاتصال" /> : null}
      <Field label="الاسم" error={error?.field === 'profile' ? error.text : null}>
        <TextField value={name} onChangeText={setName} />
      </Field>
      <Field label="البريد الإلكتروني">
        <TextField value={email} onChangeText={setEmail} keyboardType="email-address" autoCapitalize="none" style={LTR_INPUT} />
      </Field>
      <Button className="mb-6 w-full" disabled={!online || !name.trim()} onPress={saveProfile}>
        <Text>حفظ</Text>
      </Button>
      <Text variant="section" className="mb-3">
        كلمة المرور
      </Text>
      <Field label="كلمة المرور الحالية" error={error?.field === 'password' ? error.text : null}>
        <TextField value={current} onChangeText={setCurrent} secureTextEntry />
      </Field>
      <Field label="كلمة المرور الجديدة" hint="8 أحرف على الأقل">
        <TextField value={next} onChangeText={setNext} secureTextEntry />
      </Field>
      <Button variant="glass" className="mb-6 w-full" disabled={!online || !current || next.length < 8} onPress={savePassword}>
        <Text>تغيير كلمة المرور</Text>
      </Button>
      <Field label="المظهر">
        <Segmented<SchemePreference>
          label="المظهر"
          value={preference}
          onChange={setPreference}
          options={[
            ['system', 'حسب الجهاز'],
            ['light', 'فاتح'],
            ['dark', 'داكن'],
          ]}
        />
      </Field>
      <Button variant="danger" className="w-full" onPress={() => logout(() => !onBack && router.replace('/login'))}>
        <Icon as={LogOut} size={18} className="-scale-x-100" />
        <Text>تسجيل الخروج</Text>
      </Button>
      {children}
    </Screen>
  );
}

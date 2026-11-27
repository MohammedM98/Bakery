import { router } from 'expo-router';
import { LogOut } from 'lucide-react-native';
import * as React from 'react';

import { Field, Notice, Segmented, TextField } from '@/components/common';
import { PageHeader, Screen } from '@/components/screen';
import { useToast } from '@/components/toast';
import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { useApp } from '@/state/app';
import { useTheme, type SchemePreference } from '@/state/theme';

/** Profile (name, email, password) for every role; changes need the internet. */
export function ProfileForm({ back = true, children }: { back?: boolean; children?: React.ReactNode }) {
  const app = useApp();
  const toast = useToast();
  const { preference, setPreference } = useTheme();
  const [name, setName] = React.useState(app.user?.name ?? '');
  const [email, setEmail] = React.useState(app.user?.email ?? '');
  const [current, setCurrent] = React.useState('');
  const [next, setNext] = React.useState('');
  const online = app.state.online;
  return (
    <Screen net={app.user?.role !== 'super_admin'}>
      <PageHeader back={back} title="الملف الشخصي" />
      {!online ? <Notice tone="warning" title="يحتاج إنترنت" body="تعديل الملف الشخصي متاح عند الاتصال" /> : null}
      <Field label="الاسم">
        <TextField value={name} onChangeText={setName} />
      </Field>
      <Field label="البريد الإلكتروني">
        <TextField value={email} onChangeText={setEmail} keyboardType="email-address" autoCapitalize="none" style={{ writingDirection: 'ltr', textAlign: 'right' }} />
      </Field>
      <Button className="mb-6 w-full" disabled={!online || !name.trim()} onPress={() => toast({ kind: 'success', title: 'تم حفظ الملف الشخصي' })}>
        <Text>حفظ</Text>
      </Button>
      <Text variant="section" className="mb-3">
        كلمة المرور
      </Text>
      <Field label="كلمة المرور الحالية">
        <TextField value={current} onChangeText={setCurrent} secureTextEntry />
      </Field>
      <Field label="كلمة المرور الجديدة" hint="8 أحرف على الأقل">
        <TextField value={next} onChangeText={setNext} secureTextEntry />
      </Field>
      <Button variant="glass" className="mb-6 w-full" disabled={!online || !current || next.length < 8} onPress={() => (setCurrent(''), setNext(''), toast({ kind: 'success', title: 'تم تغيير كلمة المرور' }))}>
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
      <Button variant="danger" className="w-full" onPress={() => (app.logout(), router.replace('/login'))}>
        <Icon as={LogOut} size={18} />
        <Text>تسجيل الخروج</Text>
      </Button>
      {children}
    </Screen>
  );
}

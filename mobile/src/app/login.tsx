import { Redirect, router } from 'expo-router';
import { Eye, EyeOff, Mail, Wheat } from 'lucide-react-native';
import * as React from 'react';
import { Pressable, View } from 'react-native';

import { Field, Notice, TextField, LTR_INPUT } from '@/components/common';
import { Surface } from '@/components/glass';
import { Screen } from '@/components/screen';
import { useToast } from '@/components/toast';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { homeFor } from '@/lib/access';
import { MOCK_USERS } from '@/mock/data';
import { useApp } from '@/state/app';

/** Login (frosted prototype): one glass card, the one orange button. No self sign-up. */
export default function Login() {
  const app = useApp();
  const toast = useToast();
  const [email, setEmail] = React.useState('');
  const [password, setPassword] = React.useState('');
  const [remember, setRemember] = React.useState(true);
  const [show, setShow] = React.useState(false);
  const [busy, setBusy] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);
  if (app.user) return <Redirect href={homeFor(app.user)} />;

  const submit = () => {
    if (busy) return;
    setBusy(true);
    const r = app.login(email, password);
    setBusy(false);
    if (!r.ok) return setError(r.error);
    const key = Object.values(MOCK_USERS).find((u) => u.email === email.trim().toLowerCase());
    router.replace(homeFor(key ?? null));
  };

  return (
    <Screen withNav={false} net={false}>
      <View className="mb-7 mt-10 items-center">
        <Surface level="l2" radius={22} className="mb-4 h-16 w-16 items-center justify-center">
          <Icon as={Wheat} size={30} strokeWidth={1.5} className="text-wheat" />
        </Surface>
        <Text variant="title">تسجيل الدخول</Text>
        <Text variant="muted" className="mt-1 text-[14px]">
          ادخل ببريدك وكلمة المرور لإدارة مخبزك
        </Text>
      </View>
      <Surface level="l1" radius={24} className="p-5">
        {error ? <Notice tone="danger" body={error} /> : null}
        <Field label="البريد الإلكتروني">
          <View className="relative">
            <TextField
              value={email}
              onChangeText={(v) => (setEmail(v), setError(null))}
              keyboardType="email-address"
              autoCapitalize="none"
              autoComplete="email"
              textContentType="username"
              placeholder="owner@bakery.ps"
              className="px-11"
              style={LTR_INPUT}
              invalid={!!error}
            />
            <View className="absolute inset-y-0 start-4 justify-center" pointerEvents="none">
              <Icon as={Mail} size={20} strokeWidth={1.5} className="text-muted-foreground" />
            </View>
          </View>
        </Field>
        <Field label="كلمة المرور">
          <View className="relative">
            <TextField
              value={password}
              onChangeText={(v) => (setPassword(v), setError(null))}
              secureTextEntry={!show}
              autoComplete="current-password"
              textContentType="password"
              className="px-12"
              onSubmitEditing={submit}
              invalid={!!error}
            />
            <View className="absolute inset-y-0 start-1 justify-center">
              <Pressable onPress={() => setShow((s) => !s)} accessibilityRole="button" accessibilityLabel={show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'} className="h-11 w-11 items-center justify-center">
                <Icon as={show ? EyeOff : Eye} size={20} strokeWidth={1.5} className="text-muted-foreground" />
              </Pressable>
            </View>
          </View>
        </Field>
        <View className="mb-5 mt-1 flex-row items-center justify-between">
          <Pressable onPress={() => setRemember((r) => !r)} accessibilityRole="checkbox" accessibilityState={{ checked: remember }} className="h-11 flex-row items-center gap-2">
            <Checkbox checked={remember} onCheckedChange={setRemember} accessibilityLabel="تذكّرني" />
            <Text className="text-[14px]">تذكّرني</Text>
          </Pressable>
          <Text onPress={() => toast({ kind: 'success', title: 'إن كان البريد مسجّلًا فستصلك رسالة', body: 'استعادة كلمة المرور تحتاج إنترنت' })} accessibilityRole="link" className="text-muted-foreground py-3 text-[14px]">
            نسيت كلمة المرور؟
          </Text>
        </View>
        <Button onPress={submit} disabled={busy || !email || !password} className="w-full">
          <Text>{busy ? 'جارٍ الدخول' : 'دخول'}</Text>
        </Button>
      </Surface>
      <Text variant="muted" className="mt-5 text-center">
        الحسابات ينشئها مالك المنصة. تواصل معه إن لم يكن لديك حساب
      </Text>
      {__DEV__ || process.env.EXPO_PUBLIC_DEV_MENU === '1' ? (
        <Text variant="muted" className="mt-3 text-center text-[12px] leading-[20px]">
          {'حسابات التجربة، وكلمة المرور password\nowner@bakery.test\nsamer@alnoor.ps\nadmin@bakery.test'}
        </Text>
      ) : null}
    </Screen>
  );
}

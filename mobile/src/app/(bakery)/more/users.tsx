import { router } from 'expo-router';
import { Plus, Shield, WifiOff } from 'lucide-react-native';
import * as React from 'react';

import { Avatar, Badge, EmptyState, ListCard, Notice, Row } from '@/components/common';
import { IconButton, NotAllowed, PageHeader, Screen } from '@/components/screen';
import type { ApiStaff } from '@/api/types';
import { useApp, useRemote } from '@/state/app';

function permissionCount(n: number) {
  if (n === 0) return 'بلا صلاحيات';
  if (n === 1) return 'صلاحية واحدة';
  if (n === 2) return 'صلاحيتان';
  return n <= 10 ? `${n} صلاحيات` : `${n} صلاحية`;
}

/** The owner's staff: add, edit permissions, stop. Online only. */
export default function Users() {
  const app = useApp();
  const remote = useRemote<{ users: ApiStaff[] }>(app.can('manage-users') ? 'users' : null);
  if (!app.can('manage-users')) return <NotAllowed />;
  const online = app.state.online;
  const staff = remote.data?.users ?? [];
  return (
    <Screen>
      <PageHeader back title="المستخدمون" end={online ? <IconButton icon={Plus} label="إضافة موظف" onPress={() => router.push('/more/staff/new')} /> : null} />
      {!online ? <Notice tone="warning" icon={WifiOff} title="يحتاج إنترنت" body="إدارة الموظفين وصلاحياتهم تتم بالإنترنت فقط" /> : null}
      {staff.length ? (
        <ListCard>
          {staff.map((s, i) => (
            <Row
              key={s.id}
              first={i === 0}
              onPress={online ? () => router.push(`/more/staff/${s.id}`) : undefined}
              start={<Avatar name={s.name} />}
              title={s.name}
              subtitle={permissionCount(s.permissions.length)}
              end={<Badge label={s.is_active ? 'فعّال' : 'موقوف'} tone={s.is_active ? 'success' : 'neutral'} />}
            />
          ))}
        </ListCard>
      ) : (
        <EmptyState icon={Shield} title="لا موظفين بعد" body="أضف موظفًا وحدّد ما يستطيع فعله" action={online ? 'إضافة موظف' : undefined} actionIcon={Plus} onAction={() => router.push('/more/staff/new')} />
      )}
    </Screen>
  );
}

import { Landmark, Pencil, Plus, Trash2 } from 'lucide-react-native';
import * as React from 'react';
import { Pressable, View } from 'react-native';

import { EmptyState, ListCard, RoundIcon, Row } from '@/components/common';
import { useConfirm } from '@/components/confirm';
import { IconButton, NotAllowed, PageHeader, Screen } from '@/components/screen';
import { useSheets } from '@/components/sheets';
import { useToast } from '@/components/toast';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';
import { useApp } from '@/state/app';

/** Banks and wallets: one list for "from" and "to". Adding, renaming and deleting need the internet. */
export default function Banks() {
  const app = useApp();
  const sheets = useSheets();
  const confirm = useConfirm();
  const toast = useToast();
  if (!app.can('view-banks')) return <NotAllowed />;
  const online = app.state.online;
  const canManage = app.can('manage-banks') && online;
  const canDelete = app.can('delete-banks') && online;

  return (
    <Screen>
      <PageHeader back title="البنوك والمحافظ" end={canManage ? <IconButton icon={Plus} label="إضافة بنك أو محفظة" onPress={() => sheets.open({ kind: 'bank' })} /> : null} />
      {app.state.banks.length ? (
        <ListCard>
          {app.state.banks.map((b, i) => (
            <Row
              key={b.id}
              first={i === 0}
              start={<RoundIcon icon={Landmark} />}
              title={b.name}
              end={
                <View className="flex-row">
                  {canManage ? (
                    <Pressable onPress={() => sheets.open({ kind: 'bank', bankId: b.id })} accessibilityRole="button" accessibilityLabel={`تعديل ${b.name}`} className="h-11 w-11 items-center justify-center">
                      <Icon as={Pencil} size={18} strokeWidth={1.5} className="text-muted-foreground" />
                    </Pressable>
                  ) : null}
                  {canDelete ? (
                    <Pressable
                      onPress={() =>
                        confirm({
                          title: `حذف «${b.name}»؟`,
                          body: 'الحذف نهائي. الدفعات المسجّلة تبقى بالاسم في الكشوف.',
                          action: 'حذف',
                          destructive: true,
                          onConfirm: () => {
                            app.deleteBank(b.id);
                            toast({ kind: 'success', title: 'تم حذف البنك' });
                          },
                        })
                      }
                      accessibilityRole="button"
                      accessibilityLabel={`حذف ${b.name}`}
                      className="h-11 w-11 items-center justify-center">
                      <Icon as={Trash2} size={18} strokeWidth={1.5} className="text-muted-foreground" />
                    </Pressable>
                  ) : null}
                </View>
              }
            />
          ))}
        </ListCard>
      ) : (
        <EmptyState icon={Landmark} title="لا بنوك بعد" body="أضف البنوك والمحافظ التي تصلك عليها التحويلات" action={canManage ? 'إضافة بنك أو محفظة' : undefined} actionIcon={Plus} onAction={() => sheets.open({ kind: 'bank' })} />
      )}
      <Text variant="muted" className="mt-3 text-center">
        {online ? 'تظهر هذه الأسماء في «من ← إلى» عند تسجيل دفعة بتحويل' : 'الإضافة والتعديل والحذف تحتاج إنترنت'}
      </Text>
    </Screen>
  );
}

import { TriangleAlert } from 'lucide-react-native';
import * as React from 'react';
import { Platform, View } from 'react-native';

import { RoundIcon } from '@/components/common';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Text } from '@/components/ui/text';

/** Every delete (and stopping a subscription) asks first: a centred card over the scrim. */
interface Ask {
  title: string;
  body: string;
  action: string;
  destructive?: boolean;
  onConfirm: () => void;
}

const ConfirmContext = React.createContext<(a: Ask) => void>(() => {});
export const useConfirm = () => React.useContext(ConfirmContext);

function initial(): Ask | null {
  if (Platform.OS !== 'web' || typeof window === 'undefined') return null;
  if (new URLSearchParams(window.location.search).get('confirm') !== 'demo') return null;
  return {
    title: 'حذف الزبون «أم خالد»؟',
    body: 'تُحذف معه كل مبيعاته وحركاته نهائيًا، ولا يمكن التراجع.',
    action: 'حذف',
    destructive: true,
    onConfirm: () => {},
  };
}

export function ConfirmProvider({ children }: { children: React.ReactNode }) {
  const [ask, setAsk] = React.useState<Ask | null>(initial);
  return (
    <ConfirmContext.Provider value={setAsk}>
      {children}
      <AlertDialog open={!!ask} onOpenChange={(o) => !o && setAsk(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            {ask?.destructive ? (
              <View className="mb-1 items-center">
                <RoundIcon icon={TriangleAlert} tone="danger" size={52} />
              </View>
            ) : null}
            <AlertDialogTitle>{ask?.title}</AlertDialogTitle>
            <AlertDialogDescription>{ask?.body}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>
              <Text>إلغاء</Text>
            </AlertDialogCancel>
            <AlertDialogAction
              className={ask?.destructive ? 'bg-destructive' : undefined}
              onPress={() => {
                ask?.onConfirm();
                setAsk(null);
              }}>
              <Text className="text-primary-foreground">{ask?.action}</Text>
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </ConfirmContext.Provider>
  );
}

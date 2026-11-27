import { Check, TriangleAlert, WifiOff, X, type LucideIcon } from 'lucide-react-native';
import * as React from 'react';
import { Platform, Pressable, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { RoundIcon, type Tone } from '@/components/common';
import { Surface } from '@/components/glass';
import { Icon } from '@/components/ui/icon';
import { Text } from '@/components/ui/text';

/**
 * Floating messages at the top (level 4). Success fades after 4 s; an error stays until closed
 * (ui-ux-pro-max: never report a failed save only in a toast that vanishes), and forms also show it inline.
 */
type ToastKind = 'success' | 'error' | 'offline';
interface Toast {
  id: number;
  kind: ToastKind;
  title: string;
  body?: string;
  action?: { label: string; onPress: () => void };
}

const ToastContext = React.createContext<(t: Omit<Toast, 'id'>) => void>(() => {});

export function useToast() {
  return React.useContext(ToastContext);
}

const ICON: Record<ToastKind, [LucideIcon, Tone]> = {
  success: [Check, 'success'],
  error: [TriangleAlert, 'danger'],
  offline: [WifiOff, 'warning'],
};

function initial(): Toast[] {
  // Web screenshots: ?toast=demo shows the four kinds of message from the prototype.
  if (Platform.OS !== 'web' || typeof window === 'undefined') return [];
  if (new URLSearchParams(window.location.search).get('toast') !== 'demo') return [];
  return [
    { id: -1, kind: 'success', title: 'تم تسجيل البيع', body: '12 كغ، 72.00 شيكل', action: { label: 'عرض', onPress: () => {} } },
    { id: -2, kind: 'error', title: 'تعذّر حفظ الدفعة', body: 'المبلغ يجب أن يكون أكبر من صفر' },
    { id: -3, kind: 'offline', title: 'حُفظ دون إنترنت', body: 'سيُرسل عند الاتصال' },
  ];
}

export function ToastProvider({ children }: { children: React.ReactNode }) {
  const [toasts, setToasts] = React.useState<Toast[]>(initial);
  const seq = React.useRef(0);
  const insets = useSafeAreaInsets();

  const dismiss = React.useCallback((id: number) => setToasts((ts) => ts.filter((t) => t.id !== id)), []);
  const show = React.useCallback(
    (t: Omit<Toast, 'id'>) => {
      const id = ++seq.current;
      setToasts((ts) => [{ ...t, id }, ...ts].slice(0, 3));
      if (t.kind !== 'error') setTimeout(() => dismiss(id), 4000);
    },
    [dismiss],
  );

  return (
    <ToastContext.Provider value={show}>
      {children}
      <View pointerEvents="box-none" style={{ position: 'absolute', top: insets.top + 8, left: 16, right: 16, gap: 8, alignItems: 'center' }}>
        {toasts.map((t) => {
          const [icon, tone] = ICON[t.kind];
          return (
            <Surface key={t.id} level="l4" radius={20} className="w-full max-w-[520px] flex-row items-center gap-3 px-4 py-3" accessibilityLiveRegion="polite" role={t.kind === 'error' ? 'alert' : 'status'}>
              <RoundIcon icon={icon} tone={tone} size={36} />
              <View className="min-w-0 flex-1">
                <Text className="font-plex-semi">{t.title}</Text>
                {t.body ? <Text variant="muted">{t.body}</Text> : null}
              </View>
              {t.action ? (
                <Pressable
                  onPress={() => {
                    t.action?.onPress();
                    dismiss(t.id);
                  }}
                  accessibilityRole="button"
                  className="h-11 justify-center px-2">
                  <Text className="font-plex-semi text-[14px]">{t.action.label}</Text>
                </Pressable>
              ) : (
                <Pressable onPress={() => dismiss(t.id)} accessibilityRole="button" accessibilityLabel="إغلاق الرسالة" className="h-11 w-11 items-center justify-center">
                  <Icon as={X} size={18} strokeWidth={1.5} className="text-muted-foreground" />
                </Pressable>
              )}
            </Surface>
          );
        })}
      </View>
    </ToastContext.Provider>
  );
}

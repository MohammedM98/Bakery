import { cn } from '@/lib/utils';
import * as SwitchPrimitives from '@rn-primitives/switch';
import { Platform } from 'react-native';

function Switch({
  className,
  ...props
}: React.ComponentProps<typeof SwitchPrimitives.Root>) {
  return (
    <SwitchPrimitives.Root
      className={cn(
        'flex h-7 w-12 shrink-0 flex-row items-center rounded-full border border-transparent shadow-sm shadow-black/5',
        Platform.select({
          web: 'focus-visible:border-ring focus-visible:ring-ring/50 peer inline-flex outline-none transition-all focus-visible:ring-[3px] disabled:cursor-not-allowed',
        }),
        props.checked ? 'bg-foreground' : 'bg-input dark:bg-input/80',
        props.disabled && 'opacity-50',
        className
      )}
      {...props}>
      <SwitchPrimitives.Thumb
        className={cn(
          'bg-card size-6 rounded-full shadow-sm shadow-black/20 transition-transform',
          Platform.select({
            web: 'pointer-events-none block ring-0',
          }),
          // The app is always right-to-left: off sits at the start (right), on slides to the left.
          props.checked ? 'dark:bg-background -translate-x-5' : 'dark:bg-foreground translate-x-0'
        )}
      />
    </SwitchPrimitives.Root>
  );
}

export { Switch };

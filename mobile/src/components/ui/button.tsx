import { TextClassContext } from '@/components/ui/text';
import { cn } from '@/lib/utils';
import { cva, type VariantProps } from 'class-variance-authority';
import { Platform, Pressable } from 'react-native';

// React Native Reusables `Button`, restyled to the frosted prototype's buttons with the website colours:
//  - default: the one orange primary per screen, white text (website rule: 4.6:1 on #c0561b)
//  - pill: dark slate pill for strong neutral actions (inverted in dark mode)
//  - glass: level-2 glass for secondary actions
//  - danger: soft red for delete
//  - ghost: text only
// 48pt high (sm 40pt), radius 16, pressed = 1pt down and slightly dimmer.
const buttonVariants = cva(
  cn(
    'group shrink-0 flex-row items-center justify-center gap-2 rounded-control active:translate-y-px active:opacity-90',
    Platform.select({
      web: 'focus-visible:ring-ring whitespace-nowrap outline-none transition-[opacity,transform] duration-100 focus-visible:ring-2 focus-visible:ring-offset-2 disabled:pointer-events-none',
    }),
  ),
  {
    variants: {
      variant: {
        default: 'bg-primary shadow-sm shadow-black/10',
        pill: 'bg-foreground rounded-full',
        glass: 'bg-card/75 dark:bg-popover/75 border border-white/75 dark:border-white/[0.07] shadow-sm shadow-black/5',
        danger: 'bg-destructive/10',
        ghost: 'active:bg-foreground/5',
      },
      size: {
        default: 'h-12 px-5',
        sm: 'h-10 px-4',
        icon: 'h-11 w-11 rounded-full',
      },
    },
    defaultVariants: { variant: 'default', size: 'default' },
  },
);

const buttonTextVariants = cva('font-plex-medium text-[15px] leading-6 text-foreground', {
  variants: {
    variant: {
      default: 'font-plex-semi text-primary-foreground',
      pill: 'text-white dark:text-background',
      glass: '',
      danger: 'text-destructive',
      ghost: 'text-muted-foreground',
    },
    size: { default: '', sm: 'text-[14px]', icon: '' },
  },
  defaultVariants: { variant: 'default', size: 'default' },
});

type ButtonProps = React.ComponentProps<typeof Pressable> &
  React.RefAttributes<typeof Pressable> &
  VariantProps<typeof buttonVariants>;

function Button({ className, variant, size, ...props }: ButtonProps) {
  return (
    <TextClassContext.Provider value={buttonTextVariants({ variant, size })}>
      <Pressable
        className={cn(props.disabled && 'opacity-50', buttonVariants({ variant, size }), className)}
        role="button"
        {...props}
      />
    </TextClassContext.Provider>
  );
}

export { Button, buttonTextVariants, buttonVariants };
export type { ButtonProps };

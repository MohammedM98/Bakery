import { cn } from '@/lib/utils';
import { Slot } from '@rn-primitives/slot';
import { cva, type VariantProps } from 'class-variance-authority';
import * as React from 'react';
import { Platform, Text as RNText, type Role } from 'react-native';

// React Native Reusables `Text`, restyled to the app's type scale (src/theme `type`):
// IBM Plex Sans Arabic, one family per weight (Android ignores fontWeight on custom fonts),
// and Arabic line heights of about 1.6.
const textVariants = cva(
  cn('text-foreground font-plex text-[15px] leading-6 tabular-nums', Platform.select({ web: 'select-text' })),
  {
    variants: {
      variant: {
        default: '',
        display: 'font-plex-semi text-[40px] leading-[48px]',
        title: 'font-plex-semi text-[24px] leading-[34px]',
        section: 'font-plex-medium text-[20px] leading-[30px]',
        card: 'font-plex-semi text-[17px] leading-[26px]',
        medium: 'font-plex-medium',
        small: 'text-[13px] leading-[21px]',
        muted: 'text-muted-foreground text-[13px] leading-[21px]',
        label: 'font-plex-medium text-[14px] leading-[22px]',
        tiny: 'font-plex-medium text-[12px] leading-[18px]',
      },
    },
    defaultVariants: { variant: 'default' },
  },
);

type TextVariantProps = VariantProps<typeof textVariants>;
type TextVariant = NonNullable<TextVariantProps['variant']>;

const ROLE: Partial<Record<TextVariant, Role>> = { title: 'heading', section: 'heading', card: 'heading' };

const TextClassContext = React.createContext<string | undefined>(undefined);

function Text({
  className,
  asChild = false,
  variant = 'default',
  ...props
}: React.ComponentProps<typeof RNText> &
  React.RefAttributes<typeof RNText> &
  TextVariantProps & {
    asChild?: boolean;
  }) {
  const textClass = React.useContext(TextClassContext);
  const Component = asChild ? Slot : RNText;
  return (
    <Component
      className={cn(textVariants({ variant }), textClass, className)}
      role={variant ? ROLE[variant] : undefined}
      {...props}
    />
  );
}

export { Text, TextClassContext, textVariants };

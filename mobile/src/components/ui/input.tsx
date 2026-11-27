import { cn } from '@/lib/utils';
import { Platform, TextInput } from 'react-native';

function Input({ className, ...props }: React.ComponentProps<typeof TextInput> & React.RefAttributes<TextInput>) {
  return (
    <TextInput
      className={cn(
        // Restyled: a recessed well (frosted prototype), 50pt high, radius 16, IBM Plex 15.
        'bg-foreground/[0.045] dark:bg-white/5 border-transparent text-foreground font-plex flex h-[50px] w-full min-w-0 flex-row items-center rounded-control border px-4 text-[15px]',
        props.editable === false &&
        cn(
          'opacity-50',
          Platform.select({ web: 'disabled:pointer-events-none disabled:cursor-not-allowed' })
        ),
        Platform.select({
          web: cn(
            'placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground outline-none transition-[color,box-shadow]',
            'focus-visible:border-ring focus-visible:ring-ring/30 focus-visible:ring-[3px]',
            'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive'
          ),
          native: 'placeholder:text-muted-foreground focus:border-ring',
        }),
        className
      )}
      {...props}
    />
  );
}

export { Input };

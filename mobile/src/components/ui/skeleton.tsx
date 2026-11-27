import { cn } from '@/lib/utils';
import { View } from 'react-native';

function Skeleton({
  className,
  ...props
}: React.ComponentProps<typeof View> & React.RefAttributes<View>) {
  return <View className={cn('bg-foreground/[0.06] dark:bg-white/[0.06] animate-pulse rounded-control', className)} {...props} />;
}

export { Skeleton };

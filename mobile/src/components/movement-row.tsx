import * as React from 'react';

import type { MovementType } from '@/api/types';
import { RoundIcon, Row, TYPE_ICONS } from '@/components/common';
import { Text } from '@/components/ui/text';
import { cn } from '@/lib/utils';

/** One movement line as the server describes it (dashboard "آخر الحركات", sales log): who, what, how much. */
export function LineRow({
  type,
  title,
  subtitle,
  amount,
  positive,
  first,
  onPress,
}: {
  type: MovementType;
  title: string;
  subtitle: string;
  amount: string;
  /** Money in (a payment): green. */
  positive?: boolean;
  first?: boolean;
  onPress?: () => void;
}) {
  return (
    <Row
      first={first}
      onPress={onPress}
      accessibilityLabel={`${title}، ${subtitle}، ${amount}`}
      start={<RoundIcon icon={TYPE_ICONS[type]} />}
      title={title}
      subtitle={subtitle}
      end={<Text className={cn('font-plex-medium', positive && 'text-success')}>{amount}</Text>}
    />
  );
}

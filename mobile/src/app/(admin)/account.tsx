import * as React from 'react';

import { ProfileForm } from '@/components/profile-form';

/** The platform owner's "more": profile, theme, sign out. */
export default function Account() {
  return <ProfileForm back={false} />;
}

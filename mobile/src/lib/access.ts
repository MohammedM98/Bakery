import type { Href } from 'expo-router';

import type { SessionUser } from '@/api/types';

/**
 * Where a user lands (the website's HomeRedirectController): the platform owner on the bakeries list;
 * a bakery user on the home screen (the dashboard, or quick actions for staff without "view dashboard").
 */
export function homeFor(user: Pick<SessionUser, 'role'> | null): Href {
  if (!user) return '/login';
  if (user.role === 'super_admin') return '/bakeries';
  // The root layout draws "subscription expired" and "no permissions" over the app.
  return '/';
}

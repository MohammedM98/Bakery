import type { Href } from 'expo-router';

import type { ApiUser } from '@/mock/types';

/**
 * Where a user lands (the website's HomeRedirectController): the platform owner on the bakeries list;
 * a bakery user on the home screen (the dashboard, or quick actions for staff without "view dashboard"),
 * or on "no permissions" when nothing at all was granted.
 */
export function homeFor(user: ApiUser | null, expired: boolean): Href {
  if (!user) return '/login';
  if (user.role === 'super_admin') return '/bakeries';
  if (expired) return '/expired';
  if (user.permissions.length === 0) return '/no-permissions';
  return '/';
}

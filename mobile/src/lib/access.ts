import type { Href } from 'expo-router';

import type { ApiUser } from '@/mock/types';

/**
 * Where a user lands (the website's HomeRedirectController): the platform owner on the bakeries list;
 * a bakery user on the home screen (the dashboard, or quick actions for staff without "view dashboard").
 */
export function homeFor(user: ApiUser | null): Href {
  if (!user) return '/login';
  if (user.role === 'super_admin') return '/bakeries';
  // The root layout draws "subscription expired" and "no permissions" over the app.
  return '/';
}
